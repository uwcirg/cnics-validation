#!/usr/bin/env bash
set -euo pipefail

# One-off upgrade for EXISTING scans databases (spec 012). Not for mci.
#
# Before spec 012, every deployment's database was created with the MI
# `reviews` and `event_derived_datas` tables, scans included. Spec 012 gives
# each study its own study-specific tables (constitution Principle IV), and
# scans has its own `reviews` + `review_regions` (init/studies/scans.sql).
# New databases get them automatically from init/04-study-schema.sh. This
# script upgrades a database that was created earlier:
#
#   1. If `reviews` still has the MI shape (it has an `mci` column), rename it
#      to `reviews_mi_pre012`. Likewise `event_derived_datas` (has `outcome`)
#      becomes `event_derived_datas_mi_pre012`. Tables are RENAMED, never
#      dropped, so anything entered through the old MI form is kept. Drop them
#      yourself once you've confirmed they hold nothing you need.
#   2. Apply init/studies/scans.sql (CREATE TABLE IF NOT EXISTS).
#
# Idempotent: a second run renames nothing and creates nothing.
#
# Run it inside the mariadb container, where init/ is mounted at
# /docker-entrypoint-initdb.d (see specs/012-dexa-review-form/quickstart.md):
#
#   docker compose exec -T mariadb bash -s < init/migrations/012-scans-reviews.sh
#
# Then restart the backend: it caches the result of its schema check.

SCANS_SQL='/docker-entrypoint-initdb.d/studies/scans.sql'

DB="${MYSQL_DATABASE:?MYSQL_DATABASE must be set (run this inside the mariadb container)}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
if [[ -z "$ROOT_PW" ]]; then
  echo "[012-scans-reviews] ERROR: MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD is not set" >&2
  exit 1
fi
if [[ "$(printf '%s' "${STUDY_TYPE:-}" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')" != "scans" ]]; then
  echo "[012-scans-reviews] ERROR: STUDY_TYPE is '${STUDY_TYPE:-}', not 'scans'. This migration is for scans databases only." >&2
  exit 1
fi

sql() {
  MYSQL_PWD="$ROOT_PW" mariadb -uroot --batch --skip-column-names "$DB" "$@"
}

has_column() {  # has_column <table> <column>
  [[ "$(sql -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$1' AND COLUMN_NAME = '$2'")" != "0" ]]
}

for pair in "reviews:mci" "event_derived_datas:outcome"; do
  table="${pair%%:*}"
  column="${pair##*:}"
  if has_column "$table" "$column"; then
    sql -e "RENAME TABLE \`$table\` TO \`${table}_mi_pre012\`"
    echo "[012-scans-reviews] Renamed MI-shaped \`$table\` to \`${table}_mi_pre012\`."
  fi
done

sql < "$SCANS_SQL"
echo "[012-scans-reviews] Applied $SCANS_SQL. Done; restart the backend."

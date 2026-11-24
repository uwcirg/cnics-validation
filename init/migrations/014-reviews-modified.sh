#!/usr/bin/env bash
set -euo pipefail

# Upgrade for EXISTING databases of ANY study (spec 014).
#
# Spec 014 lets a reviewer edit their own submitted review, and records when
# they last did so in a new nullable `reviews.modified` column. New databases
# get the column from init/studies/<study>.sql. This script adds it to a
# database created earlier:
#
#   - If `reviews` exists and lacks `modified`, add it (nullable, so every
#     existing review reads as "never edited").
#   - If `reviews` already has `modified`, change nothing.
#   - If there is no `reviews` table, change nothing.
#
# Unlike 012-scans-reviews.sh, there is no STUDY_TYPE restriction: every
# study's `reviews` table gains the same column (constitution Principle IV).
# Idempotent: a second run changes nothing.
#
# For a scans database created before spec 012, run 012-scans-reviews.sh
# FIRST, so this script adds the column to the scans-shaped table.
#
# Run it inside the mariadb container, BEFORE deploying the spec 014 backend
# (see specs/014-packet-replace-review-edit/quickstart.md §1):
#
#   docker compose exec -T mariadb bash -s < init/migrations/014-reviews-modified.sh
#
# Then restart the backend: it caches the result of its schema check.

DB="${MYSQL_DATABASE:?MYSQL_DATABASE must be set (run this inside the mariadb container)}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
if [[ -z "$ROOT_PW" ]]; then
  echo "[014-reviews-modified] ERROR: MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD is not set" >&2
  exit 1
fi

sql() {
  MYSQL_PWD="$ROOT_PW" mariadb -uroot --batch --skip-column-names "$DB" "$@"
}

has_table() {  # has_table <table>
  [[ "$(sql -e "SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$1'")" != "0" ]]
}

has_column() {  # has_column <table> <column>
  [[ "$(sql -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$1' AND COLUMN_NAME = '$2'")" != "0" ]]
}

if ! has_table reviews; then
  echo "[014-reviews-modified] No \`reviews\` table in $DB; nothing to do."
  exit 0
fi

if has_column reviews modified; then
  echo "[014-reviews-modified] reviews.modified already present."
else
  sql -e "ALTER TABLE \`reviews\` ADD COLUMN IF NOT EXISTS \`modified\` datetime NULL DEFAULT NULL"
  echo "[014-reviews-modified] Added reviews.modified."
fi
echo "[014-reviews-modified] Done; restart the backend."

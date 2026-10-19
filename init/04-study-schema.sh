#!/usr/bin/env bash
set -euo pipefail

# Apply this deployment's study-specific schema (constitution Principle IV).
#
# Study-specific tables (`reviews`, and for some studies `event_derived_datas`)
# take their shape from one study's data needs, so each study defines them in
# its own init/studies/<study>.sql. They cannot live at the top level of
# init/: the mariadb entrypoint runs every top-level file there in every
# deployment, regardless of STUDY_TYPE. This script is the one top-level
# step that applies exactly one study file — the one for this deployment —
# so a database never holds another study's tables.
#
# Inputs (environment, from docker-compose.yaml's mariadb service):
#   STUDY_TYPE      — the deployment's study (default mci).
#   MYSQL_DATABASE  — schema to write to (set by the mariadb entrypoint).
#   MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD — set by the mariadb image.
#
# Fallback: if init/studies/<STUDY_TYPE>.sql does not exist, or its first line
# is exactly `-- DORMANT` (scaffolding not yet usable), mci.sql is applied
# instead and a warning is printed. This matches the backend's study-module
# resolver (flask_backend/models/studies/__init__.py), which falls back to
# `mci` under the same conditions.
#
# Runs once, on first DB initialization, after 02-schema.sql (which creates
# the shared tables) and before 05-add-indexes.sql. Existing databases are not
# touched; see init/migrations/ for one-off upgrade scripts.

STUDIES_DIR='/docker-entrypoint-initdb.d/studies'

DB="${MYSQL_DATABASE:?MYSQL_DATABASE must be set by the mariadb entrypoint}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"

if [[ -z "$ROOT_PW" ]]; then
  echo "[04-study-schema] ERROR: MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD is not set" >&2
  exit 1
fi

study="$(printf '%s' "${STUDY_TYPE:-}" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"
study="${study:-mci}"
if [[ ! "$study" =~ ^[a-z0-9_-]+$ ]]; then
  echo "[04-study-schema] ERROR: STUDY_TYPE='$study' is not a valid study name" >&2
  exit 1
fi
file="$STUDIES_DIR/$study.sql"

if [[ ! -f "$file" ]]; then
  echo "[04-study-schema] WARNING: no schema file for STUDY_TYPE='$study' ($file); applying mci.sql instead." >&2
  file="$STUDIES_DIR/mci.sql"
elif [[ "$(head -n 1 "$file" | tr -d '\r')" == '-- DORMANT' ]]; then
  echo "[04-study-schema] WARNING: $file is marked -- DORMANT; applying mci.sql instead." >&2
  file="$STUDIES_DIR/mci.sql"
fi

MYSQL_PWD="$ROOT_PW" mariadb --protocol=socket -uroot "$DB" < "$file"
echo "[04-study-schema] Applied $file for STUDY_TYPE='$study'."

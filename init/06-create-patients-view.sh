#!/bin/bash
set -euo pipefail

# Create the `patients_view` view, which exposes a single read surface
# over (a) the locally-owned `uw_patients2` table and (b) a FEDERATED
# proxy of `cnics_data.Patient` — defined in another MariaDB or MySQL
# instance reachable at $CNICS_DATA_DB_HOST. The FederatedX engine
# plugin is loaded from mariadb/conf.d/custom.cnf at server start.
#
# Env vars consumed:
#   MYSQL_DATABASE         — set by the mariadb image entrypoint from
#                            docker-compose.yaml; the schema we write to.
#   MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD — set by the mariadb image
#                            entrypoint from docker-compose.yaml's
#                            MYSQL_ROOT_PASSWORD (sourced from .env's
#                            DB_ROOT_PASSWORD); used to authenticate the
#                            local mariadb client during init.
#   CNICS_DATA_DB_HOST / _PORT / _NAME / _USER / _PASSWORD / _TABLE —
#                            passed through docker-compose.yaml's
#                            `mariadb.environment` block from .env;
#                            used to build the FEDERATED CONNECTION
#                            string. If _HOST is empty, the view is
#                            defined over uw_patients2 only.
#
# This script is run by the mariadb docker entrypoint exactly once on
# first DB initialization.

DB="${MYSQL_DATABASE:?MYSQL_DATABASE must be set by the mariadb entrypoint}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"

if [[ -z "$ROOT_PW" ]]; then
  echo "[06-create-patients-view] ERROR: MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD is not set" >&2
  exit 1
fi

run_sql() {
  MYSQL_PWD="$ROOT_PW" mariadb --protocol=socket -uroot "$DB"
}

if [[ -z "${CNICS_DATA_DB_HOST:-}" ]]; then
  echo "[06-create-patients-view] CNICS_DATA_DB_HOST is not set; defining patients_view over uw_patients2 only."
  run_sql <<'SQL'
CREATE OR REPLACE VIEW `patients_view` AS
  SELECT `id`, `site_patient_id`, `site`, `last_update`, `create_date`
    FROM `uw_patients2`;
SQL
  exit 0
fi

CNICS_DATA_DB_PORT="${CNICS_DATA_DB_PORT:-3306}"
CNICS_DATA_DB_NAME="${CNICS_DATA_DB_NAME:-cnics_data}"
CNICS_DATA_DB_TABLE="${CNICS_DATA_DB_TABLE:-Patient}"

: "${CNICS_DATA_DB_USER:?CNICS_DATA_DB_USER must be set when CNICS_DATA_DB_HOST is set}"
: "${CNICS_DATA_DB_PASSWORD:?CNICS_DATA_DB_PASSWORD must be set when CNICS_DATA_DB_HOST is set}"

CONN="mysql://${CNICS_DATA_DB_USER}:${CNICS_DATA_DB_PASSWORD}@${CNICS_DATA_DB_HOST}:${CNICS_DATA_DB_PORT}/${CNICS_DATA_DB_NAME}/${CNICS_DATA_DB_TABLE}"

run_sql <<SQL
DROP TABLE IF EXISTS \`cnics_data_patient_remote\`;

CREATE TABLE \`cnics_data_patient_remote\` (
  \`PatientId\`     int(11)      NOT NULL,
  \`SitePatientId\` varchar(64)  NOT NULL,
  \`Site\`          varchar(20)  NOT NULL,
  \`lastupdate\`    timestamp    NOT NULL DEFAULT '0000-00-00 00:00:00',
  \`CreateDate\`    datetime     NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=FEDERATED DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
  CONNECTION='${CONN}';

CREATE OR REPLACE VIEW \`patients_view\` AS
  SELECT
      \`PatientId\`     AS \`id\`,
      \`SitePatientId\` AS \`site_patient_id\`,
      \`Site\`          AS \`site\`,
      \`lastupdate\`    AS \`last_update\`,
      \`CreateDate\`    AS \`create_date\`
    FROM \`cnics_data_patient_remote\`
  UNION
  SELECT
      \`id\`,
      \`site_patient_id\`,
      \`site\`,
      \`last_update\`,
      \`create_date\`
    FROM \`uw_patients2\`;
SQL

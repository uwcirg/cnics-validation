#!/bin/bash
set -euo pipefail

# Create the `patients_view` view, which exposes a single read surface
# over (a) the locally-owned `uw_patients2` table and (b) a FEDERATED
# proxy of the upstream `cnics_data.Patient` table.
#
# The cnics_data server listens only on the VM host's loopback
# interface, which a bridged container cannot reach over TCP. So the
# FEDERATED proxy reaches it over the host's MariaDB/MySQL Unix socket:
# docker-compose.yaml bind-mounts the host socket directory
# (CNICS_DATA_SOCKET_DIR) into this container at /run/cnics_data_host,
# and the proxy table is pointed at it via a CREATE SERVER definition
# carrying a SOCKET option. The FederatedX engine plugin is loaded from
# mariadb/conf.d/custom.cnf at server start.
#
# Env vars consumed:
#   MYSQL_DATABASE         — schema to write to (set by mariadb entrypoint).
#   MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD — set by the mariadb image
#                            entrypoint from docker-compose.yaml's
#                            MYSQL_ROOT_PASSWORD (sourced from .env's
#                            DB_ROOT_PASSWORD); used to authenticate the
#                            local mariadb client during init.
#   CNICS_DATA_DB_NAME / _TABLE / _USER / _PASSWORD — passed through
#                            docker-compose.yaml's `mariadb.environment`
#                            block from .env; identify the upstream
#                            schema/table and the read-only bridge user.
#                            An empty CNICS_DATA_DB_USER disables the
#                            bridge (patients_view over uw_patients2 only).
#
# This script is run by the mariadb docker entrypoint exactly once on
# first DB initialization.

# Path to the host cnics_data socket as seen inside THIS container. Fixed
# by the bind-mount in docker-compose.yaml (CNICS_DATA_SOCKET_DIR ->
# /run/cnics_data_host). The socket file inside must be named mysqld.sock.
CNICS_DATA_SOCKET='/run/cnics_data_host/mysqld.sock'

DB="${MYSQL_DATABASE:?MYSQL_DATABASE must be set by the mariadb entrypoint}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"

if [[ -z "$ROOT_PW" ]]; then
  echo "[06-create-patients-view] ERROR: MARIADB_ROOT_PASSWORD / MYSQL_ROOT_PASSWORD is not set" >&2
  exit 1
fi

run_sql() {
  MYSQL_PWD="$ROOT_PW" mariadb --protocol=socket -uroot "$DB"
}

if [[ -z "${CNICS_DATA_DB_USER:-}" ]]; then
  echo "[06-create-patients-view] CNICS_DATA_DB_USER is not set; defining patients_view over uw_patients2 only."
  run_sql <<'SQL'
CREATE OR REPLACE VIEW `patients_view` AS
  SELECT `id`, `site_patient_id`, `site`, `last_update`, `create_date`
    FROM `uw_patients2`;
SQL
  exit 0
fi

CNICS_DATA_DB_NAME="${CNICS_DATA_DB_NAME:-cnics_data}"
CNICS_DATA_DB_TABLE="${CNICS_DATA_DB_TABLE:-Patient}"
: "${CNICS_DATA_DB_PASSWORD:?CNICS_DATA_DB_PASSWORD must be set when CNICS_DATA_DB_USER is set}"

if [[ ! -S "$CNICS_DATA_SOCKET" ]]; then
  echo "[06-create-patients-view] ERROR: $CNICS_DATA_SOCKET is not a socket." >&2
  echo "  Check the CNICS_DATA_SOCKET_DIR bind-mount in docker-compose.yaml" >&2
  echo "  and that the cnics_data server is running on the VM host." >&2
  exit 1
fi

# Point the FEDERATED proxy at cnics_data via a CREATE SERVER definition
# carrying a SOCKET option. If the FederatedX engine honors the SOCKET
# option, the CREATE TABLE below succeeds (it validates by connecting to
# the foreign table). If it ignores SOCKET and falls back to a TCP
# localhost attempt, CREATE TABLE fails with ER 1434 — that failure is
# the signal that this approach is unsupported on this MariaDB version.
run_sql <<SQL
DROP SERVER IF EXISTS cnics_data_srv;
CREATE SERVER cnics_data_srv
  FOREIGN DATA WRAPPER mysql
  OPTIONS (
    SOCKET   '${CNICS_DATA_SOCKET}',
    USER     '${CNICS_DATA_DB_USER}',
    PASSWORD '${CNICS_DATA_DB_PASSWORD}',
    DATABASE '${CNICS_DATA_DB_NAME}'
  );

DROP TABLE IF EXISTS \`cnics_data_patient_remote\`;
CREATE TABLE \`cnics_data_patient_remote\` (
  \`PatientId\`     int(11)      NOT NULL,
  \`SitePatientId\` varchar(64)  NOT NULL,
  \`Site\`          varchar(20)  NOT NULL,
  \`lastupdate\`    timestamp    NOT NULL DEFAULT '0000-00-00 00:00:00',
  \`CreateDate\`    datetime     NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=FEDERATED DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
  CONNECTION='cnics_data_srv/${CNICS_DATA_DB_TABLE}';

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

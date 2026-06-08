#!/bin/bash
set -euo pipefail

# Create the `patients_view` view, which exposes a single read surface
# over (a) the locally-owned `uw_patients2` table and (b) a FEDERATED
# proxy of the upstream `cnics_data.Patient` table.
#
# The FEDERATED proxy reaches cnics_data one of two ways, selected by
# CNICS_DATA_BRIDGE_MODE (see default.env):
#   socket — cnics_data on the SAME VM, reached over the host's MariaDB
#            Unix socket, bind-mounted into this container at
#            /run/cnics_data_host (CNICS_DATA_SOCKET_DIR). CREATE SERVER
#            carries a SOCKET option. This is the dev server's topology.
#   tcp    — cnics_data on a SEPARATE VM, reached over TCP at an SSH-tunnel
#            endpoint on this VM (CNICS_DATA_DB_HOST:CNICS_DATA_DB_PORT).
#            CREATE SERVER carries HOST/PORT options. This is prod's topology.
# The FederatedX engine plugin is loaded from mariadb/conf.d/custom.cnf at
# server start (required for both modes).
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
CNICS_DATA_BRIDGE_MODE="${CNICS_DATA_BRIDGE_MODE:-socket}"

# Build the transport-specific OPTIONS for the CREATE SERVER below. Both
# modes reach the same upstream cnics_data.Patient and differ only in how
# the FEDERATED proxy connects. FederatedX over TCP (tcp mode) is robust;
# the SOCKET option (socket mode) is honored on MariaDB 10.11 — if a future
# version stops honoring it, the CREATE TABLE below fails with ER 1434.
case "$CNICS_DATA_BRIDGE_MODE" in
  socket)
    if [[ ! -S "$CNICS_DATA_SOCKET" ]]; then
      echo "[06-create-patients-view] ERROR: $CNICS_DATA_SOCKET is not a socket." >&2
      echo "  Mode is 'socket'. Check the CNICS_DATA_SOCKET_DIR bind-mount in" >&2
      echo "  docker-compose.yaml and that cnics_data is running on this VM host." >&2
      echo "  (If cnics_data is on a SEPARATE VM, set CNICS_DATA_BRIDGE_MODE=tcp.)" >&2
      exit 1
    fi
    SERVER_TRANSPORT_OPTS="SOCKET '${CNICS_DATA_SOCKET}',"
    echo "[06-create-patients-view] bridge mode: socket (${CNICS_DATA_SOCKET})"
    ;;
  tcp)
    : "${CNICS_DATA_DB_HOST:?CNICS_DATA_DB_HOST must be set when CNICS_DATA_BRIDGE_MODE=tcp}"
    CNICS_DATA_DB_PORT="${CNICS_DATA_DB_PORT:-3306}"
    SERVER_TRANSPORT_OPTS="HOST '${CNICS_DATA_DB_HOST}', PORT ${CNICS_DATA_DB_PORT},"
    echo "[06-create-patients-view] bridge mode: tcp (${CNICS_DATA_DB_HOST}:${CNICS_DATA_DB_PORT})"
    ;;
  *)
    echo "[06-create-patients-view] ERROR: invalid CNICS_DATA_BRIDGE_MODE='${CNICS_DATA_BRIDGE_MODE}' (want 'socket' or 'tcp')." >&2
    exit 1
    ;;
esac

# Point the FEDERATED proxy at cnics_data via a CREATE SERVER definition
# carrying the transport OPTIONS built above. CREATE TABLE validates by
# connecting to the foreign table, so a bad endpoint/credential fails here.
run_sql <<SQL
DROP SERVER IF EXISTS cnics_data_srv;
CREATE SERVER cnics_data_srv
  FOREIGN DATA WRAPPER mysql
  OPTIONS (
    ${SERVER_TRANSPORT_OPTS}
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

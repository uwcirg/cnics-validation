#!/usr/bin/env bash
# Open the mysql CLI in this deployment's mariadb container as DB_USER.
#
# Usage:
#   bin/db-shell.sh                      # interactive shell on DB_NAME
#   bin/db-shell.sh -e 'SHOW TABLES'     # extra args go to mysql
#   bin/db-shell.sh < query.sql          # piped input works too
#
# The container is found by compose service name ("mariadb"), so it works
# whatever COMPOSE_PROJECT_NAME a deploy uses. Credentials are read from
# .env (never sourced, so nothing in it gets executed) and the password is
# passed via the MYSQL_PWD environment variable, so it never appears on a
# command line (ps, shell history) or in the terminal.
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="${ENV_FILE:-$repo_dir/.env}"

[[ -r "$env_file" ]] || { echo "Cannot read $env_file" >&2; exit 1; }

# Value of the last uncommented KEY=... line, with surrounding quotes removed.
env_value() {
  local line
  line="$(grep -E "^[[:space:]]*$1=" "$env_file" | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%$'\r'}"
  line="${line#\"}"; line="${line%\"}"
  line="${line#\'}"; line="${line%\'}"
  printf '%s' "$line"
}

DB_USER="$(env_value DB_USER)"
DB_NAME="$(env_value DB_NAME)"
MYSQL_PWD="$(env_value DB_PASSWORD)"

for var in DB_USER DB_NAME MYSQL_PWD; do
  if [[ -z "${!var}" || "${!var}" == *CHANGEME* ]]; then
    echo "${var/MYSQL_PWD/DB_PASSWORD} is unset or still a placeholder in $env_file" >&2
    exit 1
  fi
done
export MYSQL_PWD

# Allocate a TTY only when stdin is a terminal, so piping SQL in works.
tty_flag=()
[[ -t 0 ]] || tty_flag=(-T)

# "-e MYSQL_PWD" with no value copies it from this environment into the
# container without putting the value in argv.
exec docker compose --project-directory "$repo_dir" exec "${tty_flag[@]}" \
  -e MYSQL_PWD mariadb mysql -u "$DB_USER" "$DB_NAME" "$@"

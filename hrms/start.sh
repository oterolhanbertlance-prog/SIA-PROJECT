#!/usr/bin/env bash
# Boots a local MariaDB instance (if not already running) + the HRMS DB,
# then serves the PHP app with the built-in PHP dev server.
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="$ROOT_DIR/../.mysql_data"
SOCKET="/tmp/mysql.sock"
PIDFILE="/tmp/mariadb.pid"
MARKER="$DATA_DIR/.hrms_schema_loaded"

mkdir -p "$DATA_DIR"

if [ ! -d "$DATA_DIR/mysql" ]; then
  echo "[hrms] Initializing MariaDB data directory..."
  mariadb-install-db --datadir="$DATA_DIR" --auth-root-authentication-method=normal >/tmp/mariadb_install.log 2>&1
fi

if ! mysqladmin --socket="$SOCKET" ping >/dev/null 2>&1; then
  echo "[hrms] Starting MariaDB..."
  mariadbd --datadir="$DATA_DIR" --socket="$SOCKET" --pid-file="$PIDFILE" \
    --skip-networking=0 --bind-address=127.0.0.1 --port=3306 \
    >/tmp/mariadb.log 2>&1 &

  for i in $(seq 1 60); do
    if mysqladmin --socket="$SOCKET" ping >/dev/null 2>&1; then
      break
    fi
    sleep 0.5
  done
fi

mysql --socket="$SOCKET" -u root -e "CREATE DATABASE IF NOT EXISTS hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [ ! -f "$MARKER" ]; then
  echo "[hrms] Loading base schema..."
  mysql --socket="$SOCKET" -u root hrms < "$ROOT_DIR/database/hrms.sql"
  echo "[hrms] Applying migration_v2..."
  mysql --socket="$SOCKET" -u root hrms < "$ROOT_DIR/database/migration_v2.sql" || true
  echo "[hrms] Applying migration_v3..."
  mysql --socket="$SOCKET" -u root hrms < "$ROOT_DIR/database/migration_v3_two_stage_approval.sql" || true
  if [ -f "$ROOT_DIR/database/migration_v4_applicant_portal.sql" ]; then
    echo "[hrms] Applying migration_v4_applicant_portal..."
    mysql --socket="$SOCKET" -u root hrms < "$ROOT_DIR/database/migration_v4_applicant_portal.sql" || true
  fi
  touch "$MARKER"
else
  if [ -f "$ROOT_DIR/database/migration_v4_applicant_portal.sql" ]; then
    mysql --socket="$SOCKET" -u root hrms < "$ROOT_DIR/database/migration_v4_applicant_portal.sql" >/tmp/migration_v4.log 2>&1 || true
  fi
fi

echo "[hrms] Starting PHP server on port ${PORT:-5000}..."
cd "$ROOT_DIR"
exec php -d display_errors=1 -d error_reporting=E_ALL \
  -d pdo_mysql.default_socket="$SOCKET" -d mysqli.default_socket="$SOCKET" \
  -S 0.0.0.0:"${PORT:-5000}" router.php

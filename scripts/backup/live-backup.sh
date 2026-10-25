#!/usr/bin/env bash
# Daily backup: MySQL + storage + .env → local folder → Google Drive (rclone).
# Read-only on the database; safe while the shop is billing.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG="${BACKUP_CONFIG:-$HOME/backups/jagdamba/backup.env}"
STAMP="$(date +%Y-%m-%d_%H%M%S)"
DAY="$(date +%Y-%m-%d)"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "ERROR: $*"
    if [[ -n "${BACKUP_ALERT_EMAIL:-}" ]] && command -v mail >/dev/null 2>&1; then
        printf 'Jagdamba backup failed on %s\n\n%s\n' "$(hostname)" "$*" | mail -s "Jagdamba backup FAILED" "$BACKUP_ALERT_EMAIL" || true
    fi
    exit 1
}

if [[ ! -f "$CONFIG" ]]; then
    fail "Missing config: $CONFIG (copy scripts/backup/backup.env.example)"
fi

# shellcheck disable=SC1090
source "$CONFIG"

: "${BACKUP_APP_DIR:?Set BACKUP_APP_DIR in $CONFIG}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"

BACKUP_ROOT="${BACKUP_ROOT:-$HOME/backups/jagdamba}"
WORK="$BACKUP_ROOT/runs/$STAMP"
ARCHIVE_DIR="$BACKUP_ROOT/daily"
mkdir -p "$WORK" "$ARCHIVE_DIR"
chmod 700 "$BACKUP_ROOT" 2>/dev/null || true

ENV_FILE="$BACKUP_APP_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
    fail ".env not found at $ENV_FILE"
fi

read_env() {
    local key="$1"
    grep -E "^${key}=" "$ENV_FILE" | head -n1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/' | sed "s/^'\(.*\)'$/\1/"
}

DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"
DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD)"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

for var in DB_DATABASE DB_USERNAME DB_PASSWORD; do
    if [[ -z "${!var:-}" ]]; then
        fail "Missing $var in $ENV_FILE"
    fi
done

MYSQL_CNF="$WORK/mysql.cnf"
chmod 700 "$WORK"
cat >"$MYSQL_CNF" <<EOF
[client]
host=$DB_HOST
port=$DB_PORT
user=$DB_USERNAME
password=$DB_PASSWORD
EOF
chmod 600 "$MYSQL_CNF"

log "Starting database dump ($DB_DATABASE)"
if ! mysqldump --defaults-extra-file="$MYSQL_CNF" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    "$DB_DATABASE" | gzip -9 >"$WORK/database.sql.gz"; then
    fail "mysqldump failed"
fi

DB_BYTES="$(wc -c <"$WORK/database.sql.gz" | tr -d ' ')"
if [[ "$DB_BYTES" -lt 500 ]]; then
    fail "database.sql.gz is too small ($DB_BYTES bytes) — dump may be empty"
fi

log "Archiving storage (public + private)"
STORAGE_ROOT="$BACKUP_APP_DIR/storage/app"
if [[ -d "$STORAGE_ROOT" ]]; then
    tar -czf "$WORK/storage-app.tar.gz" -C "$STORAGE_ROOT" public private 2>/dev/null \
        || tar -czf "$WORK/storage-app.tar.gz" -C "$STORAGE_ROOT" public 2>/dev/null \
        || log "WARN: no storage/app/public or private to archive"
else
    log "WARN: $STORAGE_ROOT not found; skipping storage archive"
fi

cp "$ENV_FILE" "$WORK/env.backup"
chmod 600 "$WORK/env.backup"

BUNDLE="$ARCHIVE_DIR/jagdamba-$STAMP.tar.gz"
tar -czf "$BUNDLE" -C "$WORK" database.sql.gz storage-app.tar.gz env.backup 2>/dev/null \
    || tar -czf "$BUNDLE" -C "$WORK" database.sql.gz env.backup

if [[ -n "$RCLONE_REMOTE" && "$RCLONE_REMOTE" != "skip" && "$RCLONE_REMOTE" != "none" ]]; then
    log "Uploading to $RCLONE_REMOTE"
    if ! command -v rclone >/dev/null 2>&1; then
        fail "rclone not installed. See scripts/backup/SETUP-EASY-WINDOWS.md or set RCLONE_REMOTE=skip for server-only backups."
    fi

    rclone copy "$BUNDLE" "$RCLONE_REMOTE/daily/" --stats-one-line --stats 5s

    MONTH="$(date +%Y-%m)"
    if [[ "$(date +%d)" == "01" ]]; then
        log "Monthly snapshot → $RCLONE_REMOTE/monthly/$MONTH/"
        rclone copy "$BUNDLE" "$RCLONE_REMOTE/monthly/$MONTH/" --stats-one-line --stats 5s
    fi
else
    log "Google Drive upload skipped (set RCLONE_REMOTE in backup.env when ready)."
fi

log "Pruning local daily bundles older than $RETENTION_DAYS days"
find "$ARCHIVE_DIR" -name 'jagdamba-*.tar.gz' -type f -mtime +"$RETENTION_DAYS" -delete 2>/dev/null || true
find "$BACKUP_ROOT/runs" -mindepth 1 -maxdepth 1 -type d -mtime +2 -exec rm -rf {} + 2>/dev/null || true

rm -rf "$WORK"
if [[ -n "$RCLONE_REMOTE" && "$RCLONE_REMOTE" != "skip" && "$RCLONE_REMOTE" != "none" ]]; then
    log "Done: $BUNDLE ($(wc -c <"$BUNDLE" | tr -d ' ') bytes) → Google Drive"
else
    log "Done: $BUNDLE ($(wc -c <"$BUNDLE" | tr -d ' ') bytes) saved on server only"
fi

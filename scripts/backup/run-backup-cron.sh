#!/usr/bin/env bash
# Short entry point for Hostinger hPanel cron (255-char command limit).
export TZ=Asia/Kolkata
export PATH="${HOME}/bin:/usr/bin:/bin"
export BACKUP_CONFIG="${HOME}/backups/jagdamba/backup.env"
APP="${HOME}/domains/digitalcreatorss.com/jagdamba/scripts/backup/live-backup.sh"
LOG="${HOME}/backups/jagdamba/backup.log"
exec /bin/bash "$APP" >> "$LOG" 2>&1

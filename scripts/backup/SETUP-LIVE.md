# Live backup (Option A): Google Drive at 12:00 AM IST

Daily **read-only** MySQL dump + `storage` + `.env` copy, uploaded with **rclone**.  
Does **not** change or delete bill data on the server.

---

## 1. One-time: folders on Hostinger (SSH)

```bash
mkdir -p ~/backups/jagdamba
chmod 700 ~/backups/jagdamba
```

After `git pull`, make the script executable:

```bash
cd ~/domains/digitalcreatorss.com/jagdamba
chmod +x scripts/backup/live-backup.sh
```

---

## 2. Install rclone (if not already installed)

```bash
curl -fsSL https://rclone.org/install.sh | sudo bash
# or, without sudo (user install):
# cd ~ && curl -O https://downloads.rclone.org/rclone-current-linux-amd64.zip && ...
rclone version
```

---

## 3. Connect Google Drive (one-time)

On the server (SSH):

```bash
rclone config
```

Suggested answers:

| Prompt | Choice |
|--------|--------|
| New remote | `n` |
| Name | `gdrive` |
| Storage | Google Drive (often `18` or pick from list) |
| client_id / client_secret | Enter (default) |
| scope | `1` Full access (needed to upload backups) |
| Service account | `n` |
| Advanced | `n` |
| Auto config | `n` (server has no browser) |

Copy the **URL** rclone prints, open it on **your PC**, sign in with the **Google account** that should own backups, approve, paste the **verification code** back into SSH.

Test:

```bash
rclone lsd gdrive:
```

Create a dedicated folder in Drive (optional; rclone will create path on upload):

- Folder name example: `jagdamba-jwellers-backups`

---

## 4. Config file (secrets — only on server)

```bash
cp ~/domains/digitalcreatorss.com/jagdamba/scripts/backup/backup.env.example ~/backups/jagdamba/backup.env
nano ~/backups/jagdamba/backup.env
chmod 600 ~/backups/jagdamba/backup.env
```

Set:

- `BACKUP_APP_DIR` — full path to `jagdamba` (where `artisan` lives)
- `RCLONE_REMOTE` — e.g. `gdrive:jagdamba-jwellers-backups`

---

## 5. Test backup manually (before cron)

```bash
export BACKUP_CONFIG=~/backups/jagdamba/backup.env
~/domains/digitalcreatorss.com/jagdamba/scripts/backup/live-backup.sh
```

Check:

- Log ends with `Done: ... → Google Drive`
- Google Drive → `jagdamba-jwellers-backups/daily/` → file `jagdamba-YYYY-MM-DD_HHMMSS.tar.gz`
- File size is **not** tiny (should be MB, not bytes)

Inside the bundle: `database.sql.gz`, optional `storage-app.tar.gz`, `env.backup`.

---

## 6. Cron: every day at **12:00 AM IST**

```bash
crontab -e
```

Add (adjust paths to your user):

```cron
0 0 * * * TZ=Asia/Kolkata BACKUP_CONFIG=$HOME/backups/jagdamba/backup.env $HOME/domains/digitalcreatorss.com/jagdamba/scripts/backup/live-backup.sh >> $HOME/backups/jagdamba/backup.log 2>&1
```

Verify cron list:

```bash
crontab -l
```

---

## 7. Restore (emergency — test once on a copy)

1. Download latest `jagdamba-*.tar.gz` from Google Drive.
2. Extract: `tar -xzf jagdamba-....tar.gz`
3. Import DB (on a **test** database first):

   ```bash
   gunzip -c database.sql.gz | mysql -u USER -p DATABASE_NAME
   ```

4. Restore files: unpack `storage-app.tar.gz` into `storage/app/` on a test install.
5. Copy `env.backup` to `.env` only if rebuilding the same server.

**Never** run `migrate:fresh` or `db:wipe` on live.

---

## 8. Retention

| Location | Policy |
|----------|--------|
| Server `~/backups/jagdamba/daily/` | Last **14** days (configurable `RETENTION_DAYS`) |
| Google Drive `daily/` | Manage in Drive (suggest keep **90 days**) |
| Google Drive `monthly/YYYY-MM-DD/` | One copy per backup day — trim old months in Drive yearly |

Optional: set `BACKUP_ALERT_EMAIL` in `backup.env` if the server can send mail.

---

## 9. After `git pull` (app deploy)

From the app directory (where `artisan` lives):

```bash
git pull origin main
php artisan migrate --force
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache
```

If **Sales → Invoice** returns **500**, check the error on the server:

```bash
tail -80 storage/logs/laravel.log
```

Common fixes: run `migrate --force` (needs `2026_10_26_120000_add_sale_cancellation_and_settlements`), and always `route:clear` after pull if you ever use `route:cache`.

**Live `.env` (HTTPS subdomain)** — if sign-in works but **Sales → Invoice** sends you back to login, set:

```env
APP_URL=https://jagdambajwellers.digitalcreatorss.com
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
```

Then `php artisan config:clear && php artisan config:cache`.

Staff invoice links look like `/sales/{uuid}` — they **require sign-in**. Customer QR links use `/bill/{uuid}?signature=...` (no login).

---

## 10. Hostinger automatic backups

If your plan includes hPanel backups, **leave them on** as an extra layer. This script is your **daily, bill-focused** copy you control on Google Drive.

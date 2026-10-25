# Easier Google Drive setup (use your Windows PC)

Hostinger SSH **cannot open Google login** in a browser. The simple fix: connect Google Drive **on Windows**, then copy one small config file to the server.

You already have **rclone on the server** (`~/bin/rclone`). You only need the **token file** from Windows.

---

## Part A — Start daily backups **today** (no Google yet)

On the server:

```bash
nano ~/backups/jagdamba/backup.env
```

Set:

```bash
BACKUP_APP_DIR=/home/u922228303/domains/digitalcreatorss.com/jagdamba
RCLONE_REMOTE=skip
```

Save. Then:

```bash
export PATH="$HOME/bin:$PATH"
export BACKUP_CONFIG=~/backups/jagdamba/backup.env
cd ~/domains/digitalcreatorss.com/jagdamba
git pull origin main
chmod +x scripts/backup/live-backup.sh
./scripts/backup/live-backup.sh
```

You should see: **`saved on server only`**.

Backups live in: **`/home/u922228303/backups/jagdamba/daily/`**

Download a copy anytime: Hostinger **hPanel → Files → File Manager** → go to that folder → download the latest `jagdamba-*.tar.gz` to your PC (good habit once a week).

Enable **12 AM IST** cron — it works with `RCLONE_REMOTE=skip` too.

### Hostinger hPanel cron (command must be under 255 characters)

After `git pull`:

```bash
chmod +x ~/domains/digitalcreatorss.com/jagdamba/scripts/backup/run-backup-cron.sh
chmod +x ~/domains/digitalcreatorss.com/jagdamba/scripts/backup/live-backup.sh
```

In **hPanel → Cron Jobs**, schedule **00:00** daily, command **only**:

```bash
/bin/bash /home/u922228303/domains/digitalcreatorss.com/jagdamba/scripts/backup/run-backup-cron.sh
```

(Replace `u922228303` if your home path differs.)

---

## Part B — Add Google Drive later (~10 minutes on Windows)

### 1. On your Windows laptop/PC

1. Open: https://rclone.org/downloads/
2. Download **Windows 64-bit** zip → Extract to `C:\rclone\`

### 2. Open Command Prompt or PowerShell

```powershell
cd C:\rclone
.\rclone.exe config
```

| Step | Answer |
|------|--------|
| n | New remote |
| name | `gdrive` |
| Storage | Google Drive (number from list) |
| client_id / secret | Enter |
| scope | `1` |
| advanced | `n` |
| **Use auto config?** | **`y`** ← browser opens on Windows |
| Sign in → Allow | your Google account |
| Shared Drive? | `n` |
| Keep remote? | `y` |
| Quit | `q` |

Test on Windows:

```powershell
.\rclone.exe lsd gdrive:
```

### 3. Copy config file to Hostinger

On Windows, the file is:

`C:\Users\YOUR_WINDOWS_NAME\AppData\Roaming\rclone\rclone.conf`

Upload with **Hostinger File Manager** or **FileZilla** to the server:

**Path on server:**  
`/home/u922228303/.config/rclone/rclone.conf`

Create folder `.config/rclone` on the server if needed (SSH):

```bash
mkdir -p ~/.config/rclone
chmod 700 ~/.config/rclone
```

After upload (SSH):

```bash
chmod 600 ~/.config/rclone/rclone.conf
export PATH="$HOME/bin:$PATH"
~/bin/rclone lsd gdrive:
```

### 4. Turn Google upload on

```bash
nano ~/backups/jagdamba/backup.env
```

Change to:

```bash
RCLONE_REMOTE=gdrive:jagdamba-jwellers-backups
```

Run backup once:

```bash
export BACKUP_CONFIG=~/backups/jagdamba/backup.env
cd ~/domains/digitalcreatorss.com/jagdamba
./scripts/backup/live-backup.sh
```

Check Google Drive for folder **jagdamba-jwellers-backups/daily/**.

---

## If you want help

Ask someone technical to do **Part B** once, or share Hostinger **File Manager** access only (not billing password) to upload `rclone.conf`.

**Never** share `rclone.conf` or `.env` in WhatsApp groups — they contain secrets.

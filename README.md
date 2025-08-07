# Automated cPanel Backup to Google Drive with a PHP UI

A complete, automated backup system for websites and databases on a cPanel server. It uses a PHP-based web interface for easy configuration and a powerful Bash script as the engine to perform the backups and upload them securely to a cloud destination via `rclone`.

This system is designed specifically for shared hosting environments where direct shell command execution from PHP (`exec`, `shell_exec`) is disabled for security reasons.

## Features

- **Web-Based UI:** An easy-to-use, password-protected web page to configure all backup settings without needing to edit script files.
- **Multi-Destination Support:** Easily configurable for any cloud storage provider supported by `rclone` (Google Drive, Dropbox, Amazon S3, etc.).
- **Selective Backups:** Choose exactly which databases and directories you want to back up.
- **Custom Exclusions:** Specify file or folder patterns (e.g., `*/cache`) to exclude from your file backups, saving space and time.
- **Unique, Timestamped Backups:** Each backup run is saved in a unique folder named with the date and time (e.g., `YYYY-MM-DD_HHMMSS`), preventing overwrites.
- **Automated Cleanup (Retention Policies):**
  - Automatically deletes old backups from your cloud storage after a specified number of days.
  - Automatically cleans up all temporary files from the local server after each run.
- **Email Notifications:** Get notified on success, on failure, or always. The email includes the full execution log for easy monitoring.
- **Robust & Secure:** Separates the UI from the engine, creates temporary database credentials for each run, and is built with standard, reliable tools.

![Screenshot](/Screenshot_1.png)

## Architecture

The system is split into three parts to work around shared hosting security restrictions:

1.  **The UI (PHP):** A web form that saves your settings to a `config.json` file. It **does not** execute any backup commands.
2.  **The Engine (Bash Script):** A `backup_script.sh` that reads its instructions from `config.json` and performs the actual work (database dumps, file archiving, and uploading).
3.  **The Automation (Cron Job):** A standard cPanel cron job that runs the `backup_script.sh` on a schedule.

## File Structure

Your final file structure should look like this. The `backup-system` directory should be placed in your home directory, outside of any web-accessible folders.

```
/home/your_user/
├── backup-system/
│ ├── backup_script.sh
│ ├── rclone
│ └── jq
│
└── backup.yourdomain.com/ (This is your subdomain's folder)
├── index.php
├── save_config.php
└── config.json (created automatically by the UI)
```

## Prerequisites

- A cPanel account with SSH / Terminal access.
- **rclone:** A command-line tool for cloud storage.
- **jq:** A command-line JSON processor.

#### Installing `rclone` and `jq`

Log in to your cPanel Terminal and run these commands to install the tools into your home directory (no root access needed):

**Install `rclone`:**

```bash
curl https://rclone.org/install.sh | bash
```

**Install `jq`:**

```bash
curl -L https://github.com/stedolan/jq/releases/download/jq-1.6/jq-linux64 -o $HOME/jq && chmod +x $HOME/jq
```

## Installation Guide

### Step 1: Configure Cloud Storage Access with rclone

First, you must authorize rclone to access your chosen cloud storage provider. This example uses Google Drive, but the process is similar for others like Dropbox, S3, etc.

1. In your cPanel Terminal, start the configuration wizard:

   ```bash
   rclone config
   ```

2. Follow the interactive prompts to create a "New remote".
   Give your remote a simple name (e.g., gdrive_backup or dropbox_backup). You will need this name for the UI.
   Select your cloud storage provider from the list.
   Follow the authentication steps, which usually involve copying a link into your browser, granting access, and pasting a verification code back into the terminal.

3. Test your connection:
   - Replace remote_name with the name you chose
     rclone
   ```bash
   lsd remote_name:
   ```

### Step 2: Deploy the Project Files

1. **Create a Subdomain:** In cPanel, go to "Subdomains" and create a new one like backup.yourdomain.com. This isolates the UI from your main website. Its document root will be a new folder (e.g., /home/your_user/backup.yourdomain.com).
2. **Create the Engine Folder:** In your terminal, create the master folder for the system:
   ```bash
   mkdir -p /home/your_user/backup-system
   ```
3. **Move Tools & Scripts:**

- Place index.php and save_config.php into your subdomain's folder.
- Place backup_script.sh, rclone, and jq into the /home/your_user/backup-system/ folder.

4. **Make Files Executable:** Run this command in your terminal:
   ```bash
   chmod +x /home/your_user/backup-system/backup_script.sh /home/your_user/backup-system/rclone /home/your_user/backup-system/jq
   ```

### Step 3: Edit Configuration Paths

You must edit **one line** in the `backup_script.sh` file to tell it where to find the configuration from the UI.

- **File to Edit:** `/home/your_user/backup-system/backup_script.sh`
- **Line to Change:** Find the `CONFIG_FILE` variable near the top of the script.
  `bash
    CONFIG_FILE="/home/your_user/backup.yourdomain.com/config.json"
    `
  Replace `your_user` and `backup.yourdomain.com` with your actual cPanel username and subdomain folder.

### Step 4: Final Configuration & Automation

1. **Password Protect the UI:** In cPanel, go to "Directory Privacy" and secure your subdomain's folder. This is a critical security step.
2. **Configure via Web:**

- Go to your new subdomain (e.g., http://backup.yourdomain.com).
- Enter the password you just set.
- Fill out all the backup settings: database credentials, directories to back up, your rclone remote name, and notification preferences.
- Click "Save Configuration".

3. **Run a Manual Test:** In the terminal, run the script to ensure everything works before automating it:
   ```bash
   /bin/bash /home/your_user/backup-system/backup_script.sh
   ```

### You now have a fully automated, configurable backup system.

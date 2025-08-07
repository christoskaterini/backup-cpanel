#!/bin/bash

# =================================================================
#      PRODUCTION BACKUP SCRIPT ENGINE v3.0 
# =================================================================

# --- Set fundamental paths ---
CONFIG_FILE="/my-path-to-the/config.json"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
JQ_CMD="$SCRIPT_DIR/jq"
RCLONE_CMD="$SCRIPT_DIR/rclone"

# --- Create a log file for this run ---
LOG_FILE="/my-path-to-the/backup-system/backup_run.log"
> "$LOG_FILE" # Clear the log file at the start of a new run

# --- Flag to track if any command has failed ---
BACKUP_FAILED=0

# --- Function for logging messages to both console and file ---
log() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# --- Check if config file exists ---
if [ ! -f "$CONFIG_FILE" ]; then
    log "FATAL ERROR: Configuration file not found at $CONFIG_FILE"
    exit 1
fi

log "--- Starting Backup ---"

# --- Read configuration from JSON file ---
DB_USER=$($JQ_CMD -r '.db_user' "$CONFIG_FILE")
DB_PASS=$($JQ_CMD -r '.db_pass' "$CONFIG_FILE")
DATABASES=$($JQ_CMD -r '.databases' "$CONFIG_FILE")
DIRECTORIES=$($JQ_CMD -r '.directories' "$CONFIG_FILE")
RCLONE_REMOTE=$($JQ_CMD -r '.rclone_remote' "$CONFIG_FILE")
GDRIVE_FOLDER=$($JQ_CMD -r '.gdrive_folder' "$CONFIG_FILE")
REMOTE_RETENTION_DAYS=$($JQ_CMD -r '.remote_retention_days' "$CONFIG_FILE")
EMAIL_MODE=$($JQ_CMD -r '.email_mode' "$CONFIG_FILE")
NOTIFY_EMAIL=$($JQ_CMD -r '.notify_email' "$CONFIG_FILE")

# --- Get other variables ---
CPANEL_USER=$(whoami)
BACKUP_DIR="/home/$CPANEL_USER/backups_temp"
TIMESTAMP=$(date +"%Y-%m-%d_%H%M%S")

# --- Run a command and check for failure ---
run_command() {
    # The first argument is the log message, the rest is the command
    local log_message="$1"
    shift
    log "$log_message"
    
    # Execute command, if it fails, set the failure flag
    if ! "$@"; then
        log "ERROR: The previous command failed!"
        BACKUP_FAILED=1
    fi
}

# --- Start of main logic ---
mkdir -p "$BACKUP_DIR"

# Database backups
if [[ -n "$DATABASES" ]]; then
    cnf_file="/home/$CPANEL_USER/.my.cnf.tmp"; echo "[mysqldump]" > "$cnf_file"; echo "user=$DB_USER" >> "$cnf_file"; echo "password=\"$DB_PASS\"" >> "$cnf_file"; chmod 600 "$cnf_file"
    echo "$DATABASES" | while read -r db; do
        [[ -n "$db" ]] && run_command "  - Backing up database: $db" mysqldump --defaults-extra-file="$cnf_file" --single-transaction --routines --triggers "$db" | gzip > "$BACKUP_DIR/${db}_${TIMESTAMP}.sql.gz"
    done
    rm -f "$cnf_file"
    log "Database backup phase complete."
fi

# File backups
if [[ -n "$DIRECTORIES" ]]; then
    echo "$DIRECTORIES" | while IFS= read -r dir; do
        if [[ -n "$dir" ]]; then
            child_dir_name=$(basename "$dir")
            parent_dir_name=$(basename "$(dirname "$dir")")
            unique_archive_name="${parent_dir_name}_${child_dir_name}"

            run_command "  - Archiving directory: $dir" tar --exclude="*/*/cache" --exclude="*/*/backups" --exclude="*.bak" -czf "$BACKUP_DIR/${unique_archive_name}_${TIMESTAMP}.tar.gz" -C "$(dirname "$dir")" "$child_dir_name"
        fi
    done
    log "Directory archiving phase complete."
fi

# Upload to Google Drive
run_command "Uploading to Google Drive..." $RCLONE_CMD copy "$BACKUP_DIR/" "$RCLONE_REMOTE:$GDRIVE_FOLDER/$TIMESTAMP" --create-empty-src-dirs --progress

# Remote cleanup on Google Drive
if [[ "$REMOTE_RETENTION_DAYS" -gt 0 ]]; then
    run_command "Cleaning up old remote backups..." $RCLONE_CMD delete "$RCLONE_REMOTE:$GDRIVE_FOLDER" --min-age "${REMOTE_RETENTION_DAYS}d"
    $RCLONE_CMD rmdirs "$RCLONE_REMOTE:$GDRIVE_FOLDER" --leave-root # This is less critical, so no failure check
fi

# Local cleanup
run_command "Cleaning up local temporary files..." rm -rf "$BACKUP_DIR"

# --- Final Status and Notification ---
if [ $BACKUP_FAILED -eq 1 ]; then
    log "--- Backup Finished with ERRORS ---"
    if [[ "$EMAIL_MODE" == "Always" || "$EMAIL_MODE" == "On Failure" ]]; then
        SUBJECT="❌ Backup FAILED for $(hostname)"
        (echo "Subject: $SUBJECT"; echo "To: $NOTIFY_EMAIL"; echo "Content-Type: text/plain"; echo ""; echo "The backup failed. See log below."; echo ""; echo "--- LOG ---"; cat "$LOG_FILE") | /usr/sbin/sendmail -t
    fi
else
    log "--- Backup Finished Successfully ---"
    if [[ "$EMAIL_MODE" == "Always" ]]; then
        SUBJECT="✅ Backup SUCCESSFUL for $(hostname)"
        (echo "Subject: $SUBJECT"; echo "To: $NOTIFY_EMAIL"; echo "Content-Type: text/plain"; echo ""; echo "The backup completed successfully. See log below."; echo ""; echo "--- LOG ---"; cat "$LOG_FILE") | /usr/sbin/sendmail -t
    fi
fi
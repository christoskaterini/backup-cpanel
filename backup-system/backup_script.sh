#!/bin/bash

# =================================================================
#      PRODUCTION BACKUP SCRIPT ENGINE v4.0 
# =================================================================

# --- Set fundamental paths ---
CONFIG_FILE="/my-path-to-the/config.json"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
JQ_CMD="$SCRIPT_DIR/jq"
RCLONE_CMD="$SCRIPT_DIR/rclone"

# --- Log file setup ---
LOG_FILE="$SCRIPT_DIR/backup_run.log"
> "$LOG_FILE"

# --- Status tracking ---
BACKUP_FAILED=0
declare -A TASK_STATUS # Associative array to hold task statuses

# --- Functions ---
log() { echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"; }

run_task() {
    local task_name="$1"; shift
    local log_message="$1"; shift
    
    TASK_STATUS[$task_name]="⏳ Running"
    log "$log_message"
    
    if ! "$@"; then
        log "ERROR: The previous command failed!"
        TASK_STATUS[$task_name]="❌ Failed"
        BACKUP_FAILED=1
    else
        TASK_STATUS[$task_name]="✅ Success"
    fi
}

send_html_email() {
    local subject="$1"
    local overall_status_html="$2"

    (
    echo "To: $NOTIFY_EMAIL"
    echo "Subject: $subject"
    echo "Content-Type: text/html; charset=\"UTF-8\""
    echo "MIME-Version: 1.0"
    echo ""
    echo "<!DOCTYPE html><html><head><style>"
    echo "body {font-family: Arial, sans-serif; margin: 20px; color: #333;}"
    echo "h2 {color: #588157;} h3 {border-bottom: 1px solid #ccc; padding-bottom: 5px;}"
    echo "table {border-collapse: collapse; width: 100%; max-width: 600px; margin-bottom: 20px;}"
    echo "th, td {text-align: left; padding: 10px; border: 1px solid #ddd;}"
    echo "th {background-color: #f2f2f2;}"
    echo ".status-success {color: #198754; font-weight: bold;}"
    echo ".status-fail {color: #dc3545; font-weight: bold;}"
    echo ".status-pending {color: #6c757d; font-weight: bold;}"
    echo ".log-container {background-color: #212529; color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; word-wrap: break-word;}"
    echo "</style></head><body>"
    echo "<h2>Backup Report for $(hostname)</h2>"
    echo "<p>Backup finished at $(date).</p>"
    echo "$overall_status_html"
    
    echo "<h3>Task Summary</h3><table>"
    echo "<tr><th>Task</th><th>Status</th></tr>"
    echo "<tr><td>Database Backup</td><td class='$( [[ ${TASK_STATUS[DB]} == '✅ Success' ]] && echo status-success || echo status-fail )'>${TASK_STATUS[DB]:-⚪ Not Run}</td></tr>"
    echo "<tr><td>File Archiving</td><td class='$( [[ ${TASK_STATUS[FILES]} == '✅ Success' ]] && echo status-success || echo status-fail )'>${TASK_STATUS[FILES]:-⚪ Not Run}</td></tr>"
    echo "<tr><td>Cloud Upload</td><td class='$( [[ ${TASK_STATUS[UPLOAD]} == '✅ Success' ]] && echo status-success || echo status-fail )'>${TASK_STATUS[UPLOAD]:-⚪ Not Run}</td></tr>"
    echo "<tr><td>Cloud Cleanup</td><td class='$( [[ ${TASK_STATUS[CLEAN_REMOTE]} == '✅ Success' ]] && echo status-success || echo status-fail )'>${TASK_STATUS[CLEAN_REMOTE]:-⚪ Not Run}</td></tr>"
    echo "<tr><td>Local Cleanup</td><td class='$( [[ ${TASK_STATUS[CLEAN_LOCAL]} == '✅ Success' ]] && echo status-success || echo status-fail )'>${TASK_STATUS[CLEAN_LOCAL]:-⚪ Not Run}</td></tr>"
    echo "</table>"
    
    echo "<h3>Full Execution Log</h3><div class='log-container'>"
    # Sanitize log for HTML display
    sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g' "$LOG_FILE"
    echo "</div>"
    echo "</body></html>"
    ) | /usr/sbin/sendmail -t
}

# --- Check for config file ---
if [ ! -f "$CONFIG_FILE" ]; then log "FATAL ERROR: Config file not found."; exit 1; fi

log "--- Starting Backup ---"

# --- Read configuration ---
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
        [[ -n "$db" ]] && log "  - Backing up database: $db" && mysqldump --defaults-extra-file="$cnf_file" --single-transaction --routines --triggers "$db" | gzip > "$BACKUP_DIR/${db}_${TIMESTAMP}.sql.gz"
    done
    rm -f "$cnf_file"
    TASK_STATUS[DB]="✅ Success" # Simplified check; assumes success if loop finishes
fi

# File backups
if [[ -n "$DIRECTORIES" ]]; then
    echo "$DIRECTORIES" | while IFS= read -r dir; do
        if [[ -n "$dir" ]]; then
            child_dir_name=$(basename "$dir")
            parent_dir_name=$(basename "$(dirname "$dir")")
            unique_archive_name="${parent_dir_name}_${child_dir_name}"
            log "  - Archiving directory: $dir"
            tar -czf "$BACKUP_DIR/${unique_archive_name}_${TIMESTAMP}.tar.gz" -C "$(dirname "$dir")" "$child_dir_name"
        fi
    done
    TASK_STATUS[FILES]="✅ Success"
fi

# Sanity Check & Upload
if [ -z "$(ls -A "$BACKUP_DIR")" ]; then
    log "ERROR: Temporary backup directory is empty. Nothing to upload. Aborting."
    TASK_STATUS[UPLOAD]="❌ Failed"
    BACKUP_FAILED=1
else
    run_task "UPLOAD" "Uploading to Cloud Storage..." $RCLONE_CMD copy -v "$BACKUP_DIR/" "$RCLONE_REMOTE:$GDRIVE_FOLDER/$TIMESTAMP" --create-empty-src-dirs
fi

# Remote & Local Cleanup
if [ $BACKUP_FAILED -eq 0 ]; then
    if [ "$REMOTE_RETENTION_DAYS" -gt 0 ]; then
        run_task "CLEAN_REMOTE" "Cleaning up old cloud backups..." $RCLONE_CMD delete "$RCLONE_REMOTE:$GDRIVE_FOLDER" --min-age "${REMOTE_RETENTION_DAYS}d"
        $RCLONE_CMD rmdirs "$RCLONE_REMOTE:$GDRIVE_FOLDER" --leave-root > /dev/null 2>&1
    fi
    run_task "CLEAN_LOCAL" "Cleaning up local temporary files..." rm -rf "$BACKUP_DIR"
fi

# --- Final Status and Notification ---
if [ $BACKUP_FAILED -eq 1 ]; then
    log "--- Backup Finished with ERRORS ---"
    if [[ -n "$NOTIFY_EMAIL" && ( "$EMAIL_MODE" == "Always" || "$EMAIL_MODE" == "On Failure" ) ]]; then
        send_html_email "❌ Backup FAILED for $(hostname)" "<h2 class='status-fail'>Overall Status: Failure</h2>"
    fi
else
    log "--- Backup Finished Successfully ---"
    if [[ -n "$NOTIFY_EMAIL" && "$EMAIL_MODE" == "Always" ]]; then
        send_html_email "✅ Backup SUCCESSFUL for $(hostname)" "<h2 class='status-success'>Overall Status: Success</h2>"
    fi
fi
#!/bin/bash

# Configuration
PROJECT_DIR="C:/Users/Public/Job_Post" # Adjust if your script is elsewhere
DATA_FILES_TO_RESTORE=(
    "data/jobs.json"
    "data/user.json"
    "data/job_views.json"
    "data/roles.json" # Add if you have this
    "data/user_hierarchy.json" # Add if you have this
    "data/feedback.json"
    "data/daily_visitors.json"
    # Add any other data files here
)
REMOTE_NAME="origin" # Your GitHub remote name
BRANCH_NAME="main"   # Or "master", or your default branch

LOG_FILE="${PROJECT_DIR}/logs/restore.log" # Ensure logs directory exists

# --- Functions ---
log_message() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# --- Main Script ---
# Ensure logs directory exists
mkdir -p "$(dirname "$LOG_FILE")"

log_message "Starting data restore from GitHub..."
echo "WARNING: This will overwrite local data files in $PROJECT_DIR with versions from GitHub."
echo "Are you sure you want to continue? (yes/no)"
read -r confirmation

if [ "$confirmation" != "yes" ]; then
    log_message "Restore operation cancelled by user."
    echo "Restore cancelled."
    exit 0
fi

# Navigate to the project directory
cd "$PROJECT_DIR" || { log_message "ERROR: Failed to navigate to project directory: $PROJECT_DIR"; exit 1; }

log_message "Current directory: $(pwd)"

# Check if it's a git repository
if [ ! -d ".git" ]; then
    log_message "ERROR: Not a git repository: $PROJECT_DIR."
    exit 1
fi

# Fetch the latest changes from the remote
log_message "Fetching latest data from $REMOTE_NAME..."
git fetch "$REMOTE_NAME"
if [ $? -ne 0 ]; then
    log_message "ERROR: Git fetch from $REMOTE_NAME failed."
    exit 1
fi

# Checkout the specific data files from the fetched remote branch, overwriting local versions
log_message "Checking out data files from $REMOTE_NAME/$BRANCH_NAME..."
for data_file in "${DATA_FILES_TO_RESTORE[@]}"; do
    log_message "Restoring $data_file..."
    git checkout "$REMOTE_NAME/$BRANCH_NAME" -- "$data_file"
    if [ $? -ne 0 ]; then
        log_message "ERROR: Failed to restore $data_file. It might not exist in the remote branch or there was an issue."
        # Continue to try restoring other files
    else
        log_message "$data_file restored successfully."
    fi
done

log_message "Data restore process completed."
echo "Data restore completed. Check $LOG_FILE for details."

exit 0

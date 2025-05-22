#!/bin/bash

# Configuration
PROJECT_DIR="C:/Users/Public/Job_Post" # Adjust if your script is elsewhere
DATA_DIR="data" # Relative to PROJECT_DIR
COMMIT_MESSAGE="Automated daily data backup - $(date +'%Y-%m-%d %H:%M:%S')"
REMOTE_NAME="origin" # Your GitHub remote name
BRANCH_NAME="main"   # Or "master", or your default branch

LOG_FILE="${PROJECT_DIR}/logs/backup.log" # Ensure logs directory exists

# --- Functions ---
log_message() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# --- Main Script ---
# Ensure logs directory exists
mkdir -p "$(dirname "$LOG_FILE")"

log_message "Starting daily data backup..."

# Navigate to the project directory
cd "$PROJECT_DIR" || { log_message "ERROR: Failed to navigate to project directory: $PROJECT_DIR"; exit 1; }

log_message "Current directory: $(pwd)"

# Check if it's a git repository
if [ ! -d ".git" ]; then
    log_message "ERROR: Not a git repository: $PROJECT_DIR. Please run 'git init' and set up remote."
    exit 1
fi

# Add all files in the data directory to staging
# If you want to be very specific, list each file:
# git add "$DATA_DIR/jobs.json" "$DATA_DIR/user.json" ...
log_message "Adding files from $DATA_DIR to git staging area..."
git add "$DATA_DIR/"*
# Alternatively, to only add tracked files that have changed:
# git add -u "$DATA_DIR/"

# Check if there are changes to commit
if git diff --staged --quiet; then
    log_message "No changes in data files to commit. Backup not needed at this time."
    log_message "Backup process finished."
    exit 0
fi

# Commit the changes
log_message "Committing changes with message: $COMMIT_MESSAGE"
git commit -m "$COMMIT_MESSAGE"
if [ $? -ne 0 ]; then
    log_message "ERROR: Git commit failed."
    # Attempt to reset if commit failed to avoid issues on next run
    git reset HEAD "$DATA_DIR/"*
    exit 1
fi

# Push the changes to the remote repository
log_message "Pushing changes to $REMOTE_NAME $BRANCH_NAME..."
git push "$REMOTE_NAME" "$BRANCH_NAME"
if [ $? -ne 0 ]; then
    log_message "ERROR: Git push to $REMOTE_NAME $BRANCH_NAME failed."
    # Note: A failed push might require manual intervention.
    # You might consider more sophisticated retry logic or notifications here.
    exit 1
fi

log_message "Data backup successful!"
log_message "Backup process finished."

exit 0

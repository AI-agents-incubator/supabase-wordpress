#!/bin/bash

# Create Production Backup
# Usage: ./scripts/create-backup.sh

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Load credentials
source .production-credentials

SSH_KEY="$HOME/.ssh/claude_prod_new"
REMOTE_PLUGIN_PATH="$WP_PATH/wp-content/plugins/supabase-bridge"

# Timestamp for backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="backup_$TIMESTAMP"

echo -e "${YELLOW}📦 Creating production backup: $BACKUP_NAME${NC}"

# Files to backup
FILES=(
    "supabase-bridge.php"
    "auth-form.html"
    "callback.html"
)

# Create backup on production server
ssh -i "$SSH_KEY" -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" << EOF
cd $REMOTE_PLUGIN_PATH
mkdir -p backups/$BACKUP_NAME

# Copy files
for file in ${FILES[@]}; do
    if [ -f "\$file" ]; then
        cp "\$file" "backups/$BACKUP_NAME/\$file"
        echo "Backed up: \$file"
    fi
done

# Save backup metadata
echo "$TIMESTAMP" > "backups/$BACKUP_NAME/.timestamp"
echo "Backup created: $BACKUP_NAME"
EOF

# Save backup name locally for rollback
echo "$BACKUP_NAME" > .last-backup

echo -e "${GREEN}✅ Backup created successfully: $BACKUP_NAME${NC}"

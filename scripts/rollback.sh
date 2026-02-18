#!/bin/bash

# Rollback to Last Backup
# Usage: ./scripts/rollback.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ ! -f ".last-backup" ]; then
    echo -e "${RED}❌ No backup found - cannot rollback${NC}"
    exit 1
fi

BACKUP_NAME=$(cat .last-backup)

echo -e "${YELLOW}🔙 Rolling back to: $BACKUP_NAME${NC}"

# Load credentials
source .production-credentials

SSH_KEY="$HOME/.ssh/claude_prod_new"
REMOTE_PLUGIN_PATH="$WP_PATH/wp-content/plugins/supabase-bridge"

# Restore files on production
ssh -i "$SSH_KEY" -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" << EOF
cd $REMOTE_PLUGIN_PATH

if [ ! -d "backups/$BACKUP_NAME" ]; then
    echo "❌ Backup directory not found: backups/$BACKUP_NAME"
    exit 1
fi

# Restore files
for file in backups/$BACKUP_NAME/*; do
    filename=\$(basename "\$file")
    if [ "\$filename" != ".timestamp" ]; then
        cp "backups/$BACKUP_NAME/\$filename" "\$filename"
        echo "Restored: \$filename"
    fi
done

echo "Rollback completed: $BACKUP_NAME"
EOF

echo -e "${GREEN}✅ Rollback successful${NC}"

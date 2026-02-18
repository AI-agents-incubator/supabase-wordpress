#!/bin/bash

# Sync Check Script - Compare local files with production
# Usage: ./scripts/sync-production.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Load production credentials
source .production-credentials

SSH_KEY="$HOME/.ssh/claude_prod_new"
REMOTE_PLUGIN_PATH="$WP_PATH/wp-content/plugins/supabase-bridge"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}🔍 Checking Production Sync Status${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Files to check
FILES=(
    "supabase-bridge.php"
    "auth-form.html"
    "callback.html"
)

TOTAL=0
SYNCED=0
OUT_OF_SYNC=0
OUT_OF_SYNC_FILES=()

for file in "${FILES[@]}"; do
    ((TOTAL++))

    if [ ! -f "$file" ]; then
        echo -e "${RED}❌ Local file not found: $file${NC}"
        continue
    fi

    # Get local MD5
    LOCAL_MD5=$(md5 -q "$file")

    # Get production MD5
    PROD_MD5=$(ssh -i "$SSH_KEY" -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" \
        "md5sum $REMOTE_PLUGIN_PATH/$file 2>/dev/null | awk '{print \$1}' || echo 'NOT_FOUND'" 2>/dev/null)

    if [ "$PROD_MD5" = "NOT_FOUND" ]; then
        echo -e "${RED}❌ $file - NOT ON PRODUCTION${NC}"
        ((OUT_OF_SYNC++))
        OUT_OF_SYNC_FILES+=("$file")
    elif [ "$LOCAL_MD5" = "$PROD_MD5" ]; then
        echo -e "${GREEN}✅ $file - SYNCED${NC}"
        ((SYNCED++))
    else
        echo -e "${YELLOW}⚠️  $file - OUT OF SYNC${NC}"
        echo -e "   Local:  $LOCAL_MD5"
        echo -e "   Remote: $PROD_MD5"
        ((OUT_OF_SYNC++))
        OUT_OF_SYNC_FILES+=("$file")
    fi
done

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}📊 Sync Summary${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "Total files:      $TOTAL"
echo -e "✅ Synced:        ${GREEN}$SYNCED${NC}"
echo -e "⚠️  Out of sync:  ${YELLOW}$OUT_OF_SYNC${NC}"
echo ""

if [ $OUT_OF_SYNC -gt 0 ]; then
    echo -e "${YELLOW}Files needing deployment:${NC}"
    for file in "${OUT_OF_SYNC_FILES[@]}"; do
        echo -e "  - $file"
    done
    echo ""
    echo -e "${YELLOW}💡 To deploy these files:${NC}"
    echo -e "   ${BLUE}./scripts/deploy.sh ${OUT_OF_SYNC_FILES[@]}${NC}"
    exit 1
else
    echo -e "${GREEN}🎉 All files are in sync!${NC}"
    exit 0
fi

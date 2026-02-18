#!/bin/bash

# Deploy Script for Supabase Bridge Plugin
# Usage: ./scripts/deploy.sh [file1] [file2] ...
# If no files specified, deploys all plugin files

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Load production credentials
CREDENTIALS_FILE=".production-credentials"

if [ ! -f "$CREDENTIALS_FILE" ]; then
    echo -e "${RED}❌ Error: $CREDENTIALS_FILE not found${NC}"
    exit 1
fi

# Source credentials (read SSH_HOST, SSH_USER, SSH_PORT, WP_PATH)
source "$CREDENTIALS_FILE"

# SSH key path
SSH_KEY="$HOME/.ssh/claude_prod_new"

if [ ! -f "$SSH_KEY" ]; then
    echo -e "${RED}❌ Error: SSH key not found at $SSH_KEY${NC}"
    exit 1
fi

# Remote plugin path
REMOTE_PLUGIN_PATH="$WP_PATH/wp-content/plugins/supabase-bridge"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}🚀 Deploying to Production${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Files to deploy
if [ $# -eq 0 ]; then
    # Deploy all plugin files
    FILES=(
        "supabase-bridge.php"
        "auth-form.html"
        "callback.html"
        "composer.json"
        "composer.lock"
    )
    echo -e "${YELLOW}📦 No files specified - deploying all plugin files${NC}"
else
    FILES=("$@")
    echo -e "${YELLOW}📦 Deploying specified files: ${FILES[@]}${NC}"
fi

echo ""

# Deploy each file
DEPLOYED=0
FAILED=0

for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo -e "${RED}❌ File not found: $file${NC}"
        ((FAILED++))
        continue
    fi

    echo -e "${BLUE}📤 Uploading: $file${NC}"

    if scp -i "$SSH_KEY" -P "$SSH_PORT" "$file" "$SSH_USER@$SSH_HOST:$REMOTE_PLUGIN_PATH/$file" 2>&1 | grep -v "Warning: Permanently added"; then
        echo -e "${GREEN}✅ Deployed: $file${NC}"
        ((DEPLOYED++))
    else
        echo -e "${RED}❌ Failed: $file${NC}"
        ((FAILED++))
    fi
    echo ""
done

# Summary
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}📊 Deploy Summary${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "✅ Deployed: ${GREEN}$DEPLOYED${NC}"
echo -e "❌ Failed:   ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 Deploy completed successfully!${NC}"
    echo ""
    echo -e "${YELLOW}💡 Next steps:${NC}"
    echo -e "   Run production tests: ${BLUE}npm run test:production${NC}"
    exit 0
else
    echo -e "${RED}⚠️  Deploy completed with errors${NC}"
    exit 1
fi

#!/bin/bash

# Deploy and Test Pipeline
# Usage: ./scripts/deploy-and-test.sh [file1] [file2] ...
#
# Full cycle:
# 1. Create backup (timestamp)
# 2. Deploy files to production
# 3. Run production smoke tests
# 4. If tests fail → rollback
# 5. Report results

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}🚀 Deploy and Test Pipeline${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Step 1: Create backup
echo -e "${YELLOW}📦 Step 1/4: Creating production backup...${NC}"
./scripts/create-backup.sh
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Backup failed - aborting deploy${NC}"
    exit 1
fi
echo ""

# Step 2: Deploy
echo -e "${YELLOW}📤 Step 2/4: Deploying to production...${NC}"
./scripts/deploy.sh "$@"
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Deploy failed${NC}"
    exit 1
fi
echo ""

# Step 3: Run smoke tests
echo -e "${YELLOW}🧪 Step 3/4: Running production smoke tests...${NC}"
npx playwright test tests/e2e-production/smoke.spec.js --reporter=line
TEST_EXIT_CODE=$?
echo ""

# Step 4: Check results
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}✅ Deploy and Test: SUCCESS${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}🎉 All smoke tests passed!${NC}"
    echo -e "${GREEN}🎉 Production deployment successful!${NC}"
    echo ""
    echo -e "${YELLOW}💡 Next steps:${NC}"
    echo -e "   Run full E2E tests: ${BLUE}npm run test:e2e${NC}"
    exit 0
else
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}❌ Deploy and Test: FAILED${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}⚠️  Smoke tests failed!${NC}"
    echo ""
    echo -e "${YELLOW}🔙 Step 4/4: Rolling back...${NC}"
    ./scripts/rollback.sh
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Rollback successful${NC}"
        echo -e "${YELLOW}⚠️  Production restored to previous state${NC}"
    else
        echo -e "${RED}❌ Rollback failed - manual intervention required!${NC}"
        exit 2
    fi
    exit 1
fi

#!/bin/bash

# Demo Environment Deployment Script
# Usage: ./deploy-demo.sh

set -e

echo "🚀 Starting deployment to demo.kashtre.com..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration - Update these with your actual server details
FTP_SERVER="your-server.com"
FTP_USERNAME="your-username"
FTP_PASSWORD="your-password"
FTP_PORT="65002"
DEPLOY_PATH="/home/u242329769/domains/demo.kashtre.com/public_html"
BRANCH="demo"

echo -e "${BLUE}📋 Deployment Configuration:${NC}"
echo "  Server: $FTP_SERVER"
echo "  Username: $FTP_USERNAME"
echo "  Port: $FTP_PORT"
echo "  Path: $DEPLOY_PATH"
echo "  Branch: $BRANCH"
echo ""

# Check if we're on the demo branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    echo -e "${YELLOW}⚠️  Warning: You're not on the demo branch (current: $CURRENT_BRANCH)${NC}"
    read -p "Do you want to switch to demo branch? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git checkout demo
        git pull origin demo
    else
        echo -e "${RED}❌ Deployment cancelled${NC}"
        exit 1
    fi
fi

# Pull latest changes
echo -e "${BLUE}📥 Pulling latest changes...${NC}"
git pull origin demo

# Install dependencies locally
echo -e "${BLUE}📦 Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Deploy to server using rsync (same as GitHub Actions)
echo -e "${BLUE}🚀 Deploying to demo server...${NC}"

# Copy files using rsync with sshpass, excluding .env and .htaccess
sshpass -p "$FTP_PASSWORD" rsync -avz --exclude '.env' --exclude '.htaccess' -e "ssh -p $FTP_PORT -o StrictHostKeyChecking=no" ./ $FTP_USERNAME@$FTP_SERVER:"$DEPLOY_PATH"

# Set permissions and create storage link
sshpass -p "$FTP_PASSWORD" ssh -o StrictHostKeyChecking=no -p $FTP_PORT $FTP_USERNAME@$FTP_SERVER "cd $DEPLOY_PATH && echo '✓ Setting permissions...' && chmod -R 755 storage bootstrap/cache 2>/dev/null || echo 'Warning: Could not set permissions' && echo '✓ Removing existing storage link...' && rm -f public/storage && echo '✓ Creating storage link...' && php artisan storage:link"

# Run migrations and optimize
sshpass -p "$FTP_PASSWORD" ssh -o StrictHostKeyChecking=no -p $FTP_PORT $FTP_USERNAME@$FTP_SERVER "cd $DEPLOY_PATH && echo '✓ Removing problematic migration files...' && rm -f database/migrations/2025_08_27_055414_add_branch_id_foreign_key_to_transactions_table.php && rm -f database/migrations/2025_09_05_171619_add_client_and_invoice_columns_to_transactions_table.php && rm -f database/migrations/2025_09_05_172316_add_yo_to_provider_enum_in_transactions_table.php && rm -f database/migrations/2025_09_05_173557_add_external_reference_to_transactions_table.php && rm -f database/migrations/2025_10_23_143816_create_transactions_table.php && echo '✓ Reconciling existing tables with migration history...' && php artisan fix:existing-tables && echo '✓ Running migrations...' && php artisan migrate --force --verbose && echo '✓ Checking migration status...' && php artisan migrate:status && echo '✓ Clearing and caching...' && php artisan optimize:clear && php artisan view:clear && php artisan config:cache && php artisan route:cache && echo '✓ Deployment completed successfully!'"

echo ""
echo -e "${GREEN}🎉 Deployment to demo.kashtre.com completed successfully!${NC}"
echo -e "${BLUE}🌐 Demo site: https://demo.kashtre.com${NC}"
echo ""
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "  1. Test the demo site functionality"
echo "  2. Check sub-groups page: https://demo.kashtre.com/sub-groups"
echo "  3. Verify all features are working correctly"
echo ""

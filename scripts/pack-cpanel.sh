#!/usr/bin/env bash
# ==============================================================================
# QR Menu SaaS — cPanel Production Deployment Packaging Script
# ==============================================================================

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_ZIP="${PROJECT_DIR}/qrmenu-production-cpanel.zip"

echo "=========================================================="
echo "🚀 Preparing QR Menu SaaS for cPanel Deployment..."
echo "=========================================================="

cd "${PROJECT_DIR}"

# 1. Compile latest frontend assets with Vite
echo "📦 1. Compiling frontend assets (Vite)..."
npm run build

# 2. Clear development caches
echo "🧹 2. Clearing local cache files..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 3. Create fresh storage framework directories if missing
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs

# 4. Remove previous production archive if exists
if [ -f "${OUTPUT_ZIP}" ]; then
    echo "🗑️ Removing old archive: ${OUTPUT_ZIP}"
    rm -f "${OUTPUT_ZIP}"
fi

# 5. Pack archive
echo "🗜️ 3. Packaging project files into ZIP archive..."
zip -r "${OUTPUT_ZIP}" . \
    -x "*.git*" \
    -x "node_modules/*" \
    -x "tests/*" \
    -x ".env" \
    -x ".env.testing" \
    -x "storage/logs/*.log" \
    -x "storage/framework/cache/data/*" \
    -x "storage/framework/sessions/*" \
    -x "storage/framework/views/*" \
    -x "qrmenu-production-cpanel.zip" \
    -x "*.DS_Store"

echo ""
echo "=========================================================="
echo "✅ Packaging Complete!"
echo "📦 Archive created: ${OUTPUT_ZIP}"
echo "File size: $(du -h "${OUTPUT_ZIP}" | cut -f1)"
echo "=========================================================="
echo "Next Steps in cPanel:"
echo "1. Upload '${OUTPUT_ZIP}' to your cPanel hosting."
echo "2. Extract inside /home/username/qrmenu (outside public_html)."
echo "3. Follow the deployment guide for database, .env, and cron."
echo "=========================================================="


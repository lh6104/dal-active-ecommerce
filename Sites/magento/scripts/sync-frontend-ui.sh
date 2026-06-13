#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "Syncing frontend source into the running Magento container..."
./bin/copytocontainer "app/code/Dalactive"
./bin/copytocontainer "app/design/frontend/Hiddentechies/bizkick"

echo "Clearing generated LESS/static assets inside the container..."
./bin/clinotty sh -lc 'rm -rf var/view_preprocessed/* pub/static/frontend/Hiddentechies/bizkick pub/static/deployed_version.txt'

echo "Deploying static content for active storefront locales..."
./bin/clinotty bin/magento setup:static-content:deploy -f vi_VN en_US

echo "Flushing Magento cache backends..."
./bin/clinotty bin/magento cache:flush

echo "Verifying deployed homepage UI assets..."
./bin/clinotty sh -lc 'grep -q "block.widget.block-new-products .slider-outer" pub/static/frontend/Hiddentechies/bizkick/vi_VN/css/styles-m.css'
./bin/clinotty sh -lc 'grep -q "dalactive-home-news__rates-values" pub/static/frontend/Hiddentechies/bizkick/vi_VN/Dalactive_EconomicNews/css/home-news.css'

echo "Frontend UI assets are synced, deployed, and verified."

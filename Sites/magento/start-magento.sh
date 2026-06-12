#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

./bin/docker-compose up -d

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl start fix-magento-docker-forward.service || {
    echo "Could not start fix-magento-docker-forward.service."
    echo "Install it with:"
    echo "  sudo cp scripts/fix-magento-docker-forward.sh /usr/local/sbin/fix-magento-docker-forward.sh"
    echo "  sudo chmod +x /usr/local/sbin/fix-magento-docker-forward.sh"
    echo "  sudo cp scripts/fix-magento-docker-forward.service /etc/systemd/system/fix-magento-docker-forward.service"
    echo "  sudo systemctl daemon-reload"
    echo "  sudo systemctl enable fix-magento-docker-forward.service"
    echo "  sudo systemctl start fix-magento-docker-forward.service"
  }
else
  sudo ./scripts/fix-magento-docker-forward.sh magento_default
fi

./bin/docker-compose ps

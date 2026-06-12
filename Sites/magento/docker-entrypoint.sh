#!/bin/bash
#
# Docker entrypoint script for Magento project
# Automatically sets up Dalactive modules on first run
#

set -e

# Directory containing this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Starting Magento Docker environment..."

# Check if Magento is already installed
if [ -f "$SCRIPT_DIR/src/app/etc/env.php" ]; then
    echo "Magento is already installed."

    # Check if setup has been run before
    if [ ! -f "$SCRIPT_DIR/.dalactive-setup-complete" ]; then
        echo ""
        echo "Running Dalactive modules setup..."
        cd "$SCRIPT_DIR"

        # Run the setup script
        if bash "$SCRIPT_DIR/bin/setup-dalactive-modules"; then
            touch "$SCRIPT_DIR/.dalactive-setup-complete"
            echo "✓ Dalactive modules setup completed!"
        else
            echo "⚠ Warning: Dalactive modules setup had issues (this is OK if modules are already configured)"
        fi
    fi
else
    echo "Magento installation not found. Skipping Dalactive module setup."
fi

echo "Docker environment ready!"

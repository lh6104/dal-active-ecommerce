#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# PACK FILES FOR SHARING
# =============================================================================
# Tao file archive chua env, auth.json, va SQL backup de chia se cho may khac.
# Chay tu thu muc: Sites/magento/
# =============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

[ -f "compose.yaml" ] || err "Hay chay script tu thu muc Sites/magento/"

OUTPUT_DIR="/tmp/dalactive-share"
ARCHIVE_NAME="dalactive-setup-files.tar.gz"

rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR/env"

# Copy env files
if [ -d "env" ] && [ "$(ls -A env/*.env 2>/dev/null)" ]; then
    cp env/*.env "$OUTPUT_DIR/env/"
    log "Da copy env files"
else
    warn "Khong tim thay env/*.env"
fi

# Copy auth.json
if [ -f "src/auth.json" ]; then
    mkdir -p "$OUTPUT_DIR/src"
    cp src/auth.json "$OUTPUT_DIR/src/"
    log "Da copy src/auth.json"
else
    warn "Khong tim thay src/auth.json"
fi

# Copy SQL backup
if [ -f "backup-dalactive.sql" ]; then
    cp backup-dalactive.sql "$OUTPUT_DIR/"
    log "Da copy backup-dalactive.sql"
else
    warn "Khong tim thay backup-dalactive.sql"
fi

# Tao archive
cd /tmp
tar czf "$SCRIPT_DIR/$ARCHIVE_NAME" -C /tmp dalactive-share/
rm -rf "$OUTPUT_DIR"

echo ""
log "Da tao archive: $ARCHIVE_NAME"
echo ""
echo "Kich thuoc: $(du -h "$SCRIPT_DIR/$ARCHIVE_NAME" | cut -f1)"
echo ""
echo "Cach upload:"
echo "  1. Google Drive: Upload file $ARCHIVE_NAME len Drive, share link"
echo "  2. GitHub Release: Tao release, upload file nay lam asset"
echo "  3. Server rieng: Upload len hosting bat ky"
echo ""
echo "Sau do cap nhat URL trong setup-remote.sh:"
echo "  ENV_ARCHIVE_URL=\"<link_download>\""
echo ""

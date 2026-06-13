#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# SETUP HOMEPAGE-UI BRANCH - Auto download & setup
# =============================================================================
# Chay tu thu muc: Sites/magento/
# Cau hinh cac URL duoi day truoc khi chay.
# =============================================================================

# ---- CAU HINH URL ----
# Thay bang link thuc te. Hoac de trong neu tai thu cong.

# Link den file zip/tar.gz chua 8 file env/*.env
# Vi du Google Drive: "https://drive.google.com/uc?export=download&id=XXXXX"
# Vi du GitHub Release: "https://github.com/user/repo/releases/download/v1.0/env-files.tar.gz"
ENV_ARCHIVE_URL="https://download1347.mediafire.com/gznnyxdo5zsgSqqh_h52CEF7REtNapZqkVKaCZLA4M8lNXZqnC2278XK9I-CQ4RDhJekgRnPF2Y6F2ULO5zkh-n7ftleK9b_nlanFAiKbwPo6z2QOXapV63vmOI6cCPMpVFgLARiYZX1MBTKtXm-ASjoc39UNB5LTKcachN82Uir/v2jn89nu36c55gd/dalactive-setup-files.tar.gz"

# Link den file auth.json
AUTH_JSON_URL=""

# Link den file backup-dalactive.sql
SQL_BACKUP_URL=""

# ----------------------

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }
info() { echo -e "${BLUE}[i]${NC} $1"; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

[ -f "compose.yaml" ] || err "Hay chay script tu thu muc Sites/magento/"

echo ""
echo "============================================"
echo "  SETUP HOMEPAGE-UI BRANCH"
echo "============================================"
echo ""

# ---- BUOC 1: Tai env files ----
echo "=== BUOC 1: Tai env files ==="
mkdir -p env

if [ -f "env/db.env" ] && [ -f "env/magento.env" ]; then
    warn "env/ da co file, bo qua. Xoa env/ de tai lai."
else
    if [ -n "$ENV_ARCHIVE_URL" ]; then
        log "Dang tai env archive..."
        ARCHIVE_EXT="${ENV_ARCHIVE_URL##*.}"
        case "$ARCHIVE_EXT" in
            zip)
                wget -q --show-progress -O /tmp/dalactive-env.zip "$ENV_ARCHIVE_URL"
                unzip -o /tmp/dalactive-env.zip -d env/
                rm -f /tmp/dalactive-env.zip
                ;;
            gz|tgz)
                wget -q --show-progress -O /tmp/dalactive-env.tar.gz "$ENV_ARCHIVE_URL"
                tar xzf /tmp/dalactive-env.tar.gz -C env/ --strip-components=0
                rm -f /tmp/dalactive-env.tar.gz
                ;;
            *)
                warn "Khong nhan dinh dang archive. Thu tai truc tiep tung file..."
                wget -q --show-progress -O /tmp/dalactive-env.tar.gz "$ENV_ARCHIVE_URL"
                tar xzf /tmp/dalactive-env.tar.gz -C env/ 2>/dev/null || \
                    unzip -o /tmp/dalactive-env.tar.gz -d env/ 2>/dev/null || \
                    err "Khong giai nen duoc. Hay tai thu cong."
                rm -f /tmp/dalactive-env.tar.gz
                ;;
        esac
        log "Da tai env files"
    else
        warn "ENV_ARCHIVE_URL chua cau hinh."
        warn "Tai thu cong va copy vao thu muc env/:"
        warn "  cp -f /path/to/*.env env/"
        echo ""
        info "Cac file can co:"
        info "  env/db.env"
        info "  env/magento.env"
        info "  env/elasticsearch.env"
        info "  env/redis.env"
        info "  env/rabbitmq.env"
        info "  env/phpfpm.env"
        info "  env/blackfire.env"
        info "  env/cloudflare.env"
    fi
fi

# ---- BUOC 2: Tai auth.json ----
echo ""
echo "=== BUOC 2: Tai auth.json ==="
mkdir -p src

if [ -f "src/auth.json" ]; then
    warn "src/auth.json da ton tai, bo qua."
else
    if [ -n "$AUTH_JSON_URL" ]; then
        log "Dang tai auth.json..."
        wget -q --show-progress -O src/auth.json "$AUTH_JSON_URL"
        log "Da tai xong auth.json"
    else
        warn "AUTH_JSON_URL chua cau hinh."
        warn "Tai thu cong: cp -f /path/to/auth.json src/auth.json"
    fi
fi

# ---- BUOC 3: Tai database backup ----
echo ""
echo "=== BUOC 3: Tai database backup ==="

if [ -f "backup-dalactive.sql" ]; then
    warn "backup-dalactive.sql da ton tai, bo qua."
else
    if [ -n "$SQL_BACKUP_URL" ]; then
        log "Dang tai backup-dalactive.sql (file lon, co the mat vai phut)..."
        wget -q --show-progress -O backup-dalactive.sql "$SQL_BACKUP_URL"
        log "Da tai xong backup-dalactive.sql"
    else
        warn "SQL_BACKUP_URL chua cau hinh."
        warn "Tai thu cong: cp -f /path/to/backup-dalactive.sql ."
    fi
fi

# ---- BUOC 4: Kiem tra file da du ----
echo ""
echo "=== BUOC 4: Kiem tra file ==="

MISSING=0

if [ ! -f "env/db.env" ]; then warn "Thieu: env/db.env"; MISSING=1; fi
if [ ! -f "env/magento.env" ]; then warn "Thieu: env/magento.env"; MISSING=1; fi
if [ ! -f "env/elasticsearch.env" ]; then warn "Thieu: env/elasticsearch.env"; MISSING=1; fi
if [ ! -f "env/redis.env" ]; then warn "Thieu: env/redis.env"; MISSING=1; fi
if [ ! -f "env/rabbitmq.env" ]; then warn "Thieu: env/rabbitmq.env"; MISSING=1; fi
if [ ! -f "env/phpfpm.env" ]; then warn "Thieu: env/phpfpm.env"; MISSING=1; fi
if [ ! -f "env/blackfire.env" ]; then warn "Thieu: env/blackfire.env"; MISSING=1; fi
if [ ! -f "env/cloudflare.env" ]; then warn "Thieu: env/cloudflare.env"; MISSING=1; fi
if [ ! -f "src/auth.json" ]; then warn "Thieu: src/auth.json"; MISSING=1; fi
if [ ! -f "backup-dalactive.sql" ]; then warn "Thieu: backup-dalactive.sql"; MISSING=1; fi

if [ "$MISSING" -eq 1 ]; then
    echo ""
    warn "Con thieu file. Hay tai day du truoc khi tiep tuc."
    warn "Chay lai script nay sau khi co du file."
    exit 1
else
    log "Tat ca file can thiet da co."
fi

# ---- BUOC 5: Cap quyen script ----
echo ""
echo "=== BUOC 5: Cap quyen script ==="
chmod +x bin/* scripts/*.sh start-magento.sh docker-entrypoint.sh 2>/dev/null || true
log "Da cap quyen"

# ---- BUOC 6: Kiem tra Docker ----
echo ""
echo "=== BUOC 6: Kiem tra Docker ==="
if command -v docker &>/dev/null; then
    log "Docker: $(docker --version)"
else
    err "Docker chua duoc cai dat."
fi

if docker compose version &>/dev/null; then
    log "Docker Compose: $(docker compose version --short 2>/dev/null || echo 'OK')"
elif command -v docker-compose &>/dev/null; then
    log "Docker Compose (legacy): $(docker-compose --version)"
else
    err "Docker Compose khong tim thay."
fi

# ---- BUOC 7: Start container ----
echo ""
echo "=== BUOC 7: Start container ==="
read -p "Start container bay gio? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ./bin/start --no-dev --scale tunnel=0
    log "Container da start. Doi 30s cho services on dinh..."
    sleep 30
else
    warn "Bo qua. Chay: ./bin/start --no-dev --scale tunnel=0"
fi

# ---- BUOC 8: Copy source va cai dependency ----
echo ""
echo "=== BUOC 8: Copy source + Composer install ==="
read -p "Chay copytocontainer va composer install? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ./bin/copytocontainer --all
    ./bin/clinotty composer install
    log "Composer install hoan tat"
else
    warn "Bo qua. Chay thu cong:"
    warn "  ./bin/copytocontainer --all"
    warn "  ./bin/clinotty composer install"
fi

# ---- BUOC 9: Install Magento ----
echo ""
echo "=== BUOC 9: Install Magento ==="
read -p "Chay setup-install? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ./bin/setup-install dalactive.test
    ./bin/copyfromcontainer --all
    log "Magento install hoan tat"
else
    warn "Bo qua. Chay thu cong:"
    warn "  ./bin/setup-install dalactive.test"
    warn "  ./bin/copyfromcontainer --all"
fi

# ---- BUOC 10: Restore database ----
echo ""
echo "=== BUOC 10: Restore database ==="
read -p "Restore backup-dalactive.sql? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log "Dang restore database (co the mat vai phut)..."
    ./bin/mysql < backup-dalactive.sql
    log "Restore xong. Dang chay setup:upgrade..."
    ./bin/clinotty bin/magento setup:upgrade
    log "Database restore hoan tat"
else
    warn "Bo qua. Chay thu cong:"
    warn "  ./bin/mysql < backup-dalactive.sql"
    warn "  ./bin/clinotty bin/magento setup:upgrade"
fi

# ---- BUOC 11: Domain + Bootstrap ----
echo ""
echo "=== BUOC 11: Domain + Bootstrap ==="
read -p "Chay setup-domain va bootstrap? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ./bin/setup-domain dalactive.test
    ./bin/bootstrap-current dalactive.test
    log "Bootstrap hoan tat"
else
    warn "Bo qua. Chay thu cong:"
    warn "  ./bin/setup-domain dalactive.test"
    warn "  ./bin/bootstrap-current dalactive.test"
fi

# ---- BUOC 12: Final deploy ----
echo ""
echo "=== BUOC 12: Deploy static content + Reindex ==="
read -p "Chay deploy va reindex? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ./bin/clinotty bin/magento indexer:reindex
    ./bin/clinotty bin/magento setup:static-content:deploy -f vi_VN en_US
    ./bin/clinotty bin/magento cache:flush
    log "Deploy hoan tat"
else
    warn "Bo qua. Chay thu cong:"
    warn "  ./bin/clinotty bin/magento indexer:reindex"
    warn "  ./bin/clinotty bin/magento setup:static-content:deploy -f vi_VN en_US"
    warn "  ./bin/clinotty bin/magento cache:flush"
fi

# ---- HOAN TAT ----
echo ""
echo "============================================"
echo -e "  ${GREEN}SETUP HOAN TAT!${NC}"
echo "============================================"
echo ""
echo "  Storefront : https://dalactive.test"
echo "  Admin      : https://dalactive.test/admin"
echo "  Username   : dalactive.admin"
echo "  Password   : Dalactive@123"
echo "  phpMyAdmin : http://localhost:8080"
echo ""
echo "  Neu gap loi CSS, chay lai:"
echo "    ./scripts/sync-frontend-ui.sh"
echo ""
echo "  Neu homepage khong hien news blocks:"
echo "    ./bin/clinotty php -f /var/www/html/scripts/update-homepage-cms-blocks.php"
echo "    ./bin/clinotty bin/magento cache:flush"
echo ""

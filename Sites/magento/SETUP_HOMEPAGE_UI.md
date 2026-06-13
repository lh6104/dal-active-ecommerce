# Setup nhánh `homepage-ui` cho máy mới

Tai lieu nay huong dan setup du an Magento tu nhánh `homepage-ui` tren may moi.
Sau khi `git pull`, mot so file bi `.gitignore` khong co trong repo, can tai rieng.

---

## 1. File can tai rieng (khong co tren git)

| File | Vi tri | Noi dung |
|------|--------|----------|
| `env/*.env` (8 file) | `Sites/magento/env/` | Cau hinh Docker: DB, Redis, ES, RabbitMQ, Magento admin, PHP-FPM, Cloudflare, Blackfire |
| `src/auth.json` | `Sites/magento/src/` | Magento Marketplace public/private key cho Composer |
| `backup-dalactive.sql` | `Sites/magento/` | Database dump chua toan bo data san pham, CMS, theme config |

---

## 2. Cach upload file len cloud

Chon 1 trong cac cach sau de upload 3 loai file tren:

### Option A: Google Drive (khuyen nghi)

1. Upload file len Google Drive
2. Right-click file → Share → Copy link
3. Chuyen link thanh direct download link:
   - URL goc: `https://drive.google.com/file/d/FILE_ID/view?usp=sharing`
   - URL download: `https://drive.google.com/uc?export=download&id=FILE_ID`

### Option B: GitHub Releases

1. Tao release tren GitHub repo
2. Upload file vao release assets
3. Copy direct link cua tung file

### Option C: Bat ky HTTP server nao

Upload len server rieng, hosting, hoac dung dich vu nhu:
- `https://file.io`
- `https://transfer.sh`
- Server rieng cua ban

---

## 3. Script tai va setup tu dong

Tao file `setup-remote.sh` trong thu muc goc `Sites/magento/`:

```bash
#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# SETUP HOMEPAGE-UI BRANCH
# =============================================================================
# Script nay tai cac file thieu va setup du an tu nhánh homepage-ui.
# Chay tu thu muc: Sites/magento/
# =============================================================================

# ---- CAU HINH URL ----
# Thay cac URL duoi day bang link thuc te cua ban
ENV_URL="https://drive.google.com/uc?export=download&id=YOUR_ENV_ZIP_ID"
AUTH_URL="https://drive.google.com/uc?export=download&id=YOUR_AUTH_JSON_ID"
SQL_URL="https://drive.google.com/uc?export=download&id=YOUR_SQL_ID"
# ----------------------

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# Kiem tra thu muc hien tai
[ -f "compose.yaml" ] || err "Hay chay script tu thu muc Sites/magento/"

# ---- BUOC 1: Tai file env ----
echo ""
echo "=== BUOC 1: Tai file env ==="
mkdir -p env

if [ -f "env/db.env" ]; then
    warn "env/ da ton tai, bo qua tai. Neu muon tai lai, xoa env/ truoc."
else
    if [ "$ENV_URL" != "https://drive.google.com/uc?export=download&id=YOUR_ENV_ZIP_ID" ]; then
        log "Dang tai env files..."
        # Neu la file zip:
        # wget -q --show-progress -O /tmp/env.zip "$ENV_URL" && unzip -o /tmp/env.zip -d env/ && rm /tmp/env.zip
        # Neu la tar.gz:
        # wget -q --show-progress -O /tmp/env.tar.gz "$ENV_URL" && tar xzf /tmp/env.tar.gz -C env/ && rm /tmp/env.tar.gz
        # Neu download tung file rieng:
        wget -q --show-progress -O env/db.env "${ENV_URL}/db.env" || true
        wget -q --show-progress -O env/magento.env "${ENV_URL}/magento.env" || true
        wget -q --show-progress -O env/elasticsearch.env "${ENV_URL}/elasticsearch.env" || true
        wget -q --show-progress -O env/redis.env "${ENV_URL}/redis.env" || true
        wget -q --show-progress -O env/rabbitmq.env "${ENV_URL}/rabbitmq.env" || true
        wget -q --show-progress -O env/phpfpm.env "${ENV_URL}/phpfpm.env" || true
        wget -q --show-progress -O env/blackfire.env "${ENV_URL}/blackfire.env" || true
        wget -q --show-progress -O env/cloudflare.env "${ENV_URL}/cloudflare.env" || true
        log "Da tai xong env files"
    else
        warn "Chua cau hinh ENV_URL. Hay tai thu cong:"
        warn "  1. Download tu nguon chia se"
        warn "  2. Copy vao thu muc env/"
        warn "  Hoac: cp -f /path/to/env/*.env env/"
    fi
fi

# ---- BUOC 2: Tai auth.json ----
echo ""
echo "=== BUOC 2: Tai auth.json ==="
mkdir -p src

if [ -f "src/auth.json" ]; then
    warn "src/auth.json da ton tai, bo qua."
else
    if [ "$AUTH_URL" != "https://drive.google.com/uc?export=download&id=YOUR_AUTH_JSON_ID" ]; then
        log "Dang tai auth.json..."
        wget -q --show-progress -O src/auth.json "$AUTH_URL"
        log "Da tai xong auth.json"
    else
        warn "Chua cau hinh AUTH_URL. Hay tai thu cong:"
        warn "  cp -f /path/to/auth.json src/auth.json"
    fi
fi

# ---- BUOC 3: Tai database backup ----
echo ""
echo "=== BUOC 3: Tai database backup ==="

if [ -f "backup-dalactive.sql" ]; then
    warn "backup-dalactive.sql da ton tai, bo qua."
else
    if [ "$SQL_URL" != "https://drive.google.com/uc?export=download&id=YOUR_SQL_ID" ]; then
        log "Dang tai backup-dalactive.sql (file lon, co the mat vai phut)..."
        wget -q --show-progress -O backup-dalactive.sql "$SQL_URL"
        log "Da tai xong backup-dalactive.sql"
    else
        warn "Chua cau hinh SQL_URL. Hay tai thu cong:"
        warn "  cp -f /path/to/backup-dalactive.sql ."
    fi
fi

# ---- BUOC 4: Cap quyen script ----
echo ""
echo "=== BUOC 4: Cap quyen script ==="
chmod +x bin/* scripts/*.sh start-magento.sh docker-entrypoint.sh 2>/dev/null || true
log "Da cap quyen cho tat ca script"

# ---- BUOC 5: Kiem tra Docker ----
echo ""
echo "=== BUOC 5: Kiem tra Docker ==="
if command -v docker &>/dev/null; then
    log "Docker: $(docker --version)"
else
    err "Docker chua duoc cai dat. Cai Docker Desktop truoc."
fi

if docker compose version &>/dev/null; then
    log "Docker Compose: $(docker compose version --short)"
elif command -v docker-compose &>/dev/null; then
    log "Docker Compose (legacy): $(docker-compose --version)"
else
    err "Docker Compose khong tim thay."
fi

# Kiem tra RAM Docker
DOCKER_RAM=$(docker info 2>/dev/null | grep -i "memory" | awk '{print $2}' || echo "0")
if [ -n "$DOCKER_RAM" ] && [ "$DOCKER_RAM" != "0" ]; then
    log "Docker RAM: $DOCKER_RAM"
fi

# ---- BUOC 6: Start container ----
echo ""
echo "=== BUOC 6: Start container ==="
read -p "Ban co muon start container bay gio? (y/N): " START_NOW
if [[ "$START_NOW" =~ ^[Yy]$ ]]; then
    ./bin/start --no-dev --scale tunnel=0
    log "Container da start"
else
    warn "Bo qua start. Chay thu cong: ./bin/start --no-dev --scale tunnel=0"
fi

echo ""
log "=== SETUP HOAN TAT ==="
echo ""
echo "Cac buoc tiep theo (chay thu cong):"
echo ""
echo "  1. Copy source vao container va cai dependency:"
echo "     ./bin/copytocontainer --all"
echo "     ./bin/clinotty composer install"
echo ""
echo "  2. Cai Magento database:"
echo "     ./bin/setup-install dalactive.test"
echo "     ./bin/copyfromcontainer --all"
echo ""
echo "  3. Restore database DAL Active:"
echo "     ./bin/mysql < backup-dalactive.sql"
echo "     ./bin/clinotty bin/magento setup:upgrade"
echo ""
echo "  4. Setup domain, bootstrap, va hoan tat:"
echo "     ./bin/setup-domain dalactive.test"
echo "     ./bin/bootstrap-current dalactive.test"
echo "     ./bin/clinotty bin/magento indexer:reindex"
echo "     ./bin/clinotty bin/magento setup:static-content:deploy -f vi_VN en_US"
echo "     ./bin/clinotty bin/magento cache:flush"
echo ""
echo "  5. Mo browser: https://dalactive.test"
echo ""
```

---

## 4. Huong dan thu cong (khong dung script)

Neu khong muon dung script, chay tung buoc:

### 4.1. Clone va checkout

```bash
git clone https://github.com/YOUR_USERNAME/dal-active-ecommerce.git
cd dal-active-ecommerce/Sites/magento
git checkout homepage-ui
```

### 4.2. Tao env files

```bash
mkdir -p env
# Copy tu nguon chia se (USB, Google Drive, etc.)
cp -f /path/to/env/*.env env/
```

8 file can co:

```
env/db.env
env/magento.env
env/elasticsearch.env
env/redis.env
env/rabbitmq.env
env/phpfpm.env
env/blackfire.env
env/cloudflare.env
```

### 4.3. Tao auth.json

```bash
mkdir -p src
cp -f /path/to/auth.json src/auth.json
```

### 4.4. Copy database backup

```bash
cp -f /path/to/backup-dalactive.sql .
```

### 4.5. Cap quyen va start

```bash
chmod +x bin/* scripts/*.sh start-magento.sh docker-entrypoint.sh
./bin/start --no-dev --scale tunnel=0
```

### 4.6. Cai dependency

```bash
./bin/copytocontainer --all
./bin/clinotty composer install
```

Neu gap loi Mageplaza (da giai quyet trong nhánh nay):

```bash
./bin/copytocontainer composer.json
./bin/copytocontainer composer.lock
./bin/clinotty composer install
```

### 4.7. Install Magento

```bash
./bin/setup-install dalactive.test
./bin/copyfromcontainer --all
```

### 4.8. Restore database

```bash
./bin/mysql < backup-dalactive.sql
./bin/clinotty bin/magento setup:upgrade
```

### 4.9. Setup domain va bootstrap

```bash
./bin/setup-domain dalactive.test
./bin/bootstrap-current dalactive.test
```

### 4.10. Deploy va finalize

```bash
./bin/clinotty bin/magento indexer:reindex
./bin/clinotty bin/magento setup:static-content:deploy -f vi_VN en_US
./bin/clinotty bin/magento cache:flush
```

### 4.11. Mo browser

```
https://dalactive.test
```

Admin: `https://dalactive.test/admin`
- Username: `dalactive.admin`
- Password: `Dalactive@123`

---

## 5. Thay doi dac thu cua nhánh `homepage-ui`

Nhung thay doi nay da duoc merge vao nhánh, khong can lam gi them:

### 5.1. Module moi: Dalactive_SportsNews

- Hien thi tin the thao tu vnexpress.net RSS tren homepage
- Da duoc enable trong `config.php`
- Khong can API key

### 5.2. Module moi: Dalactive_EconomicNews (homepage block)

- Block tin kinh doanh hien thi tren homepage
- Da duoc cau hinh trong CMS blocks

### 5.3. Mageplaza LazyLoading

- Da duoc enable trong `config.php`
- Co theme override de tat delay 2 giay tren homepage
- Dam bao `mageplaza/module-lazy-loading` da trong `composer.json`

### 5.4. Mageplaza SMTP

- Da duoc enable trong `config.php`
- Dam bao `mageplaza/module-smtp` da trong `composer.json`

### 5.5. Font Inter

- Theme da chuyen tu Open Sans sang Inter
- Google Fonts duoc load tu CDN, khong can cai them

### 5.6. Homepage UI refresh

- CSS moi cho product carousel, testimonials, weather, brands, service badges
- LESS extend ~1200 dong trong `_extend.less`

### 5.7. CMS Blocks cap nhat

Chay script de cap nhat CMS blocks cho homepage:

```bash
./bin/clinotty php -f /var/www/html/scripts/update-homepage-cms-blocks.php
```

Hoac thu cong trong Admin > Content > Blocks, cap nhat block `bizkick-below` voi noi dung moi.

---

## 6. Loi thuong gap

### Loi `Module 'Dalactive_SportsNews' is not registered`

```bash
./bin/copytocontainer app/code/Dalactive/SportsNews
./bin/clinotty bin/magento setup:upgrade
```

### Loi `Mageplaza_Core has been already defined`

Da fix trong nhánh nay bang `replace` trong `composer.json`. Neu van gap:

```bash
./bin/copytocontainer composer.json
./bin/copytocontainer composer.lock
./bin/clinotty composer install
```

### Loi `Module 'Mageplaza_LazyLoading' is not found`

Dam bao da cai package:

```bash
./bin/clinotty composer require mageplaza/module-lazy-loading
```

### Homepage khong hien sports news / economic news

Chay lai CMS blocks update:

```bash
./bin/clinotty php -f /var/www/html/scripts/update-homepage-cms-blocks.php
./bin/clinotty bin/magento cache:flush
```

### CSS khong hien (homepage bi mat style)

```bash
./bin/clinotty bin/magento setup:static-content:deploy -f vi_VN en_US
./bin/clinotty bin/magento cache:flush
```

Hoac dung `scripts/sync-frontend-ui.sh`:

```bash
./scripts/sync-frontend-ui.sh
```

### Loi Elasticsearch vm.max_map_count

```bash
sudo sysctl -w vm.max_map_count=262144
```

### Browser hien CSS cu

Hard refresh: `Ctrl + Shift + R` hoac `Ctrl + F5`

---

## 7. Cau truc file quan trong

```
Sites/magento/
├── env/                          # KHONG co tren git, can tai rieng
│   ├── db.env
│   ├── magento.env
│   ├── elasticsearch.env
│   ├── redis.env
│   ├── rabbitmq.env
│   ├── phpfpm.env
│   ├── blackfire.env
│   └── cloudflare.env
├── src/
│   ├── auth.json                 # KHONG co tren git, can tai rieng
│   ├── app/code/Dalactive/       # Co tren git
│   ├── app/design/               # Co tren git
│   └── composer.json             # Co tren git
├── backup-dalactive.sql          # KHONG co tren git, can tai rieng
├── compose.yaml                  # Co tren git
├── bin/                          # Co tren git
├── scripts/                      # Co tren git (sync-frontend-ui.sh, update-homepage-cms-blocks.php)
└── setup-remote.sh               # Script tai file tu dong (tao tu buoc 3)
```

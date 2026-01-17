#!/bin/bash
# Plesk Deployment Script
# Bu script Plesk'te otomatik deployment için kullanılabilir
# Git post-receive hook veya Plesk deployment path'inde çalıştırılabilir

set -e

echo "=========================================="
echo "Plesk Deployment Script"
echo "=========================================="
echo ""

# Deployment path (Plesk'te genellikle httpdocs altında)
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/vhosts/wingcert.com/httpdocs/api-service}"
GIT_REPO_PATH="${GIT_REPO_PATH:-/var/www/vhosts/wingcert.com/git/api-service.git}"

echo "Git Repository: $GIT_REPO_PATH"
echo "Deployment Path: $DEPLOY_PATH"
echo ""

# Deployment path'i oluştur
if [ ! -d "$DEPLOY_PATH" ]; then
    echo "Deployment path oluşturuluyor: $DEPLOY_PATH"
    mkdir -p "$DEPLOY_PATH"
fi

# Git repository'den dosyaları kopyala
echo "Git repository'den dosyalar kopyalanıyor..."
cd "$GIT_REPO_PATH"

# En son commit'i al
git archive HEAD | tar -x -C "$DEPLOY_PATH"

# .env dosyasını koru (eğer varsa)
if [ -f "$DEPLOY_PATH/.env" ]; then
    echo "✓ Mevcut .env dosyası korunuyor"
else
    if [ -f "$DEPLOY_PATH/.env.example" ]; then
        echo "⚠️  .env dosyası bulunamadı, .env.example'dan kopyalanıyor..."
        cp "$DEPLOY_PATH/.env.example" "$DEPLOY_PATH/.env"
        echo "⚠️  LÜTFEN .env dosyasını düzenleyip API bilgilerinizi girin!"
    fi
fi

# Logs klasörünü oluştur ve izin ver
if [ ! -d "$DEPLOY_PATH/logs" ]; then
    mkdir -p "$DEPLOY_PATH/logs"
    touch "$DEPLOY_PATH/logs/.gitkeep"
fi

# İzinleri ayarla (Plesk kullanıcısına göre)
echo "İzinler ayarlanıyor..."
chmod -R 755 "$DEPLOY_PATH"
chmod -R 777 "$DEPLOY_PATH/logs"
chmod 600 "$DEPLOY_PATH/.env" 2>/dev/null || true

# PHP versiyonunu kontrol et
echo ""
echo "PHP versiyonu kontrol ediliyor..."
php -v || echo "⚠️  PHP bulunamadı veya PATH'te değil"

echo ""
echo "=========================================="
echo "✅ Deployment tamamlandı!"
echo "=========================================="
echo ""
echo "Sonraki adımlar:"
echo "1. .env dosyasını kontrol edin: $DEPLOY_PATH/.env"
echo "2. Veritabanı tablolarını oluşturun: php database/create_tables.php"
echo "3. Test edin: php test_api.php"
echo ""

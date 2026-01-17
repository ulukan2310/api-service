# Deployment Rehberi

Bu dosya projeyi GitHub'a yüklemek ve sunucuya deploy etmek için adım adım talimatlar içerir.

## 1. GitHub'a Yükleme

### Adım 1: Git Repository Oluşturma

```bash
# Proje dizinine git
cd /Users/ulukanabaci/Downloads/parasut_v3

# Git repository'yi başlat
git init

# Tüm dosyaları ekle
git add .

# İlk commit
git commit -m "Initial commit: Paraşüt API v2 entegrasyon projesi"
```

### Adım 2: GitHub'da Repository Oluşturma

1. GitHub.com'a giriş yapın
2. Sağ üstteki "+" butonuna tıklayın → "New repository"
3. Repository adını girin (örn: `parasut-api-integration`)
4. **Public** veya **Private** seçin (Private önerilir - API credentials içeriyor)
5. **"Initialize this repository with a README"** seçeneğini işaretlemeyin
6. "Create repository" butonuna tıklayın

### Adım 3: GitHub'a Push Etme

GitHub'da repository oluşturduktan sonra, size verilen komutları çalıştırın:

```bash
# GitHub'dan aldığınız remote URL'i ekleyin (örnek)
git remote add origin https://github.com/kullaniciadi/parasut-api-integration.git

# Veya SSH kullanıyorsanız:
# git remote add origin git@github.com:kullaniciadi/parasut-api-integration.git

# Ana branch'i main olarak ayarlayın
git branch -M main

# GitHub'a push edin
git push -u origin main
```

**ÖNEMLİ:** `.env` dosyası `.gitignore`'da olduğundan GitHub'a yüklenmeyecek. Bu güvenlik için doğru bir yaklaşımdır.

## 2. Sunucuya Deploy Etme

### Yöntem 1: Git Clone (Önerilen)

Sunucuda projeyi klonlayın:

```bash
# SSH ile sunucuya bağlanın
ssh kullanici@sunucu-ip

# Web dizinine gidin (örnek: /var/www/html veya /home/kullanici/public_html)
cd /var/www/html

# GitHub'dan projeyi klonlayın
git clone https://github.com/kullaniciadi/parasut-api-integration.git parasut_v3

# Veya SSH ile:
# git clone git@github.com:kullaniciadi/parasut-api-integration.git parasut_v3

# Proje dizinine gidin
cd parasut_v3

# .env dosyasını oluşturun
cp .env.example .env

# .env dosyasını düzenleyin (sunucu bilgilerinizle)
nano .env
# veya
vi .env
```

### Yöntem 2: FTP/SFTP ile Yükleme

1. Tüm dosyaları ZIP olarak indirin veya `git archive` kullanın:
   ```bash
   git archive --format=zip --output=parasut_v3.zip main
   ```

2. ZIP dosyasını FTP/SFTP ile sunucuya yükleyin

3. Sunucuda ZIP'i açın:
   ```bash
   unzip parasut_v3.zip
   ```

4. `.env` dosyasını oluşturun ve düzenleyin

### Yöntem 3: rsync ile Senkronizasyon

```bash
# Lokal makineden sunucuya senkronize et
rsync -avz --exclude '.git' --exclude 'logs/*.log' \
  /Users/ulukanabaci/Downloads/parasut_v3/ \
  kullanici@sunucu-ip:/var/www/html/parasut_v3/
```

## 3. Sunucuda Kurulum

### Adım 1: .env Dosyasını Yapılandırma

Sunucuda `.env` dosyasını oluşturun ve düzenleyin:

```bash
cd /var/www/html/parasut_v3
cp .env.example .env
nano .env
```

Sunucu bilgilerinizi girin:
- Veritabanı bilgileri (sunucu veritabanı)
- Paraşüt API credentials
- Email ayarları (opsiyonel)

### Adım 2: Veritabanını Oluşturma

```bash
# MySQL'e bağlanın
mysql -u root -p

# Veritabanını oluşturun
CREATE DATABASE parasut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Kullanıcı oluşturun (opsiyonel)
CREATE USER 'parasut_user'@'localhost' IDENTIFIED BY 'güçlü_şifre';
GRANT ALL PRIVILEGES ON parasut_db.* TO 'parasut_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Adım 3: Tabloları Oluşturma

```bash
cd /var/www/html/parasut_v3
php database/create_tables.php
```

### Adım 4: İzinleri Ayarlama

```bash
# Logs klasörüne yazma izni verin
chmod 755 logs/
chmod 666 logs/.gitkeep

# Web sunucusu kullanıcısına sahip olma izni verin (örnek: www-data)
chown -R www-data:www-data /var/www/html/parasut_v3
```

### Adım 5: Test Etme

```bash
# Authentication testi
php tests/test_auth.php

# İlk senkronizasyon (küçük bir tablo ile test)
php sync/sync.php contacts
```

## 4. Cron Job Kurulumu

Sunucuda cron job kurun:

```bash
# Crontab'ı düzenleyin
crontab -e

# Aşağıdaki satırı ekleyin (her gün saat 02:00'de)
0 2 * * * cd /var/www/html/parasut_v3 && /usr/bin/php sync/sync_cron.php >> logs/cron.log 2>&1

# Veya her 6 saatte bir:
0 */6 * * * cd /var/www/html/parasut_v3 && /usr/bin/php sync/sync_cron.php >> logs/cron.log 2>&1
```

**Not:** PHP yolunu kontrol edin:
```bash
which php
# Çıktı: /usr/bin/php veya /usr/local/bin/php
```

## 5. Güvenlik Kontrolleri

### .env Dosyası Güvenliği

```bash
# .env dosyasının izinlerini sınırlayın
chmod 600 .env
chown www-data:www-data .env
```

### Web Erişimi (Opsiyonel)

Eğer web üzerinden erişilebilir olmamasını istiyorsanız:

```apache
# Apache .htaccess ile (public_html dışında tutun)
# veya nginx config ile erişimi engelleyin
```

Veya projeyi web root dışında tutun:
```bash
# Örnek: /home/kullanici/parasut_v3 (web erişimi yok)
# Sadece CLI ile çalıştırılabilir
```

## 6. Güncelleme Süreci

### GitHub'dan Güncelleme

```bash
# Sunucuda proje dizinine gidin
cd /var/www/html/parasut_v3

# Değişiklikleri çekin
git pull origin main

# Gerekirse veritabanı migration'ları çalıştırın
php database/create_tables.php
```

## 7. Sorun Giderme

### PHP Versiyonu Kontrolü

```bash
php -v
# PHP 7.4 veya üzeri olmalı
```

### Gerekli PHP Extension'ları

```bash
php -m | grep -E "pdo|curl|json"
# pdo_mysql, curl, json extension'ları yüklü olmalı
```

### Log Dosyalarını Kontrol Etme

```bash
# Sync logları
tail -f logs/sync_$(date +%Y-%m-%d).log

# Cron logları
tail -f logs/cron.log

# Veritabanı logları
mysql -u root -p parasut_db -e "SELECT * FROM parasut_sync_log ORDER BY id DESC LIMIT 10;"
```

### İzin Sorunları

```bash
# Tüm dosyaların sahibini kontrol edin
ls -la

# Gerekirse düzeltin
chown -R www-data:www-data /var/www/html/parasut_v3
chmod -R 755 /var/www/html/parasut_v3
chmod -R 777 logs/
```

## 8. Backup Stratejisi

### Veritabanı Backup

```bash
# Günlük backup scripti oluşturun
mysqldump -u root -p parasut_db > backup_$(date +%Y%m%d).sql

# Cron ile otomatik backup
0 3 * * * mysqldump -u root -pŞİFRE parasut_db > /backup/parasut_db_$(date +\%Y\%m\%d).sql
```

### Log Rotation

```bash
# Logrotate config oluşturun: /etc/logrotate.d/parasut
/var/www/html/parasut_v3/logs/*.log {
    daily
    rotate 30
    compress
    missingok
    notifempty
}
```

## Özet Komutlar

```bash
# 1. Lokal: Git repository oluştur ve GitHub'a push et
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/kullaniciadi/repo.git
git push -u origin main

# 2. Sunucu: Clone et ve kur
git clone https://github.com/kullaniciadi/repo.git parasut_v3
cd parasut_v3
cp .env.example .env
# .env dosyasını düzenle
php database/create_tables.php
php tests/test_auth.php

# 3. Cron job ekle
crontab -e
# 0 2 * * * cd /path/to/parasut_v3 && php sync/sync_cron.php >> logs/cron.log 2>&1
```

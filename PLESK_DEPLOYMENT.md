# Plesk Deployment Rehberi - GitHub Entegrasyonu

Bu rehber, projeyi GitHub'dan Plesk'e deploy etmek için adım adım talimatlar içerir.

## 📋 Ön Hazırlık

### 1. GitHub Repository Oluşturma

1. GitHub.com'a giriş yapın
2. Sağ üstteki "+" → "New repository"
3. Repository adı: `api-service` (veya istediğiniz isim)
4. **Private** seçin (API credentials içerdiği için önerilir)
5. **"Initialize this repository with a README"** seçeneğini işaretlemeyin
6. "Create repository" butonuna tıklayın

### 2. Lokal Makinede GitHub'a Push

```bash
# Proje dizinine gidin
cd /Users/ulukanabaci/Documents/GitHub/api-service

# Git repository'yi başlat (eğer yoksa)
git init

# Tüm dosyaları ekle
git add .

# İlk commit
git commit -m "Initial commit: Paraşüt API v2 entegrasyon projesi"

# GitHub remote'u ekle
git remote add origin https://github.com/KULLANICI_ADINIZ/api-service.git

# Ana branch'i main yap
git branch -M main

# GitHub'a push et
git push -u origin main
```

**ÖNEMLİ:** `.env` dosyası `.gitignore`'da olduğu için GitHub'a yüklenmeyecek. Bu güvenlik için doğru bir yaklaşımdır.

## 🚀 Plesk'te Kurulum

### Adım 1: SSH ile Sunucuya Bağlanın

```bash
ssh kullanici@wingcert.com
```

### Adım 2: Git Repository'yi Clone Edin

```bash
# Git dizinine gidin
cd /var/www/vhosts/wingcert.com/git

# Eski dizin varsa kontrol edin
ls -la api-service.git 2>/dev/null && echo "Dizin var" || echo "Dizin yok"

# Eğer varsa ve önemli bir şey yoksa silin
# rm -rf api-service.git

# GitHub'dan clone edin
git clone https://github.com/KULLANICI_ADINIZ/api-service.git api-service.git

# Branch'i kontrol edin
cd api-service.git
git branch
# Eğer main branch yoksa:
git checkout -b main
# veya
git branch -M main
```

### Adım 3: Plesk'te Git Ayarları

1. Plesk paneline girin
2. Domain'inize gidin (wingcert.com)
3. Sol menüden **"Git"** bölümüne tıklayın
4. **"Enable Git"** butonuna tıklayın
5. Ayarları yapın:
   - **Repository path:** `/var/www/vhosts/wingcert.com/git/api-service.git`
   - **Branch:** `main`
   - **Deployment path:** `/var/www/vhosts/wingcert.com/httpdocs/api-service`
6. **"Enable automatic deployment"** seçeneğini işaretleyin
7. **"Save"** butonuna tıklayın

### Adım 4: İlk Deployment

Plesk otomatik deployment kullanıyorsa, ilk pull'u yapın:

1. Plesk'te Git bölümünde **"Pull"** butonuna tıklayın
2. Veya SSH'de:
   ```bash
   cd /var/www/vhosts/wingcert.com/git/api-service.git
   git pull origin main
   ```

### Adım 5: .env Dosyasını Oluşturun

```bash
# Deployment path'e gidin
cd /var/www/vhosts/wingcert.com/httpdocs/api-service

# .env.example'dan .env oluşturun
cp .env.example .env

# Düzenleyin
nano .env
# veya
vi .env
```

**Sunucu bilgilerinizi girin:**
```env
# Database Configuration (Plesk'teki veritabanı bilgileri)
DB_HOST=localhost
DB_NAME=parasut_db
DB_USER=plesk_db_user
DB_PASSWORD=plesk_db_password
DB_CHARSET=utf8mb4

# Paraşüt API v2 Configuration
PARASUT_API_BASE_URL=https://api.parasut.com/v2
PARASUT_CLIENT_ID=your_client_id_here
PARASUT_CLIENT_SECRET=your_client_secret_here
PARASUT_USERNAME=your_username_here
PARASUT_PASSWORD=your_password_here
PARASUT_COMPANY_ID=your_company_id_here
```

### Adım 6: Veritabanını Oluşturun

Plesk'te veritabanı oluşturun:

1. Plesk'te **"Databases"** → **"Add Database"**
2. Veritabanı adı: `parasut_db`
3. Kullanıcı oluşturun ve yetkileri verin
4. Veya SSH ile:
   ```bash
   mysql -u root -p
   CREATE DATABASE parasut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

### Adım 7: Tabloları Oluşturun

```bash
cd /var/www/vhosts/wingcert.com/httpdocs/api-service
php database/create_tables.php
```

### Adım 8: Test Edin

```bash
# API testi
php test_api.php

# Authentication testi
php tests/test_auth.php

# İlk senkronizasyon (küçük bir tablo ile)
php sync/sync.php contacts
```

## 🔄 Güncelleme Süreci

### Lokal Makinede Değişiklik Yaptıktan Sonra

```bash
# Lokal makinede
cd /Users/ulukanabaci/Documents/GitHub/api-service

# Değişiklikleri commit edin
git add .
git commit -m "Değişiklik açıklaması"

# GitHub'a push edin
git push origin main
```

### Plesk'te Güncelleme

**Yöntem 1: Plesk Arayüzünden (Önerilen)**

1. Plesk'te Git bölümüne gidin
2. **"Pull"** butonuna tıklayın
3. Plesk otomatik olarak deployment path'e kopyalayacak

**Yöntem 2: SSH ile Manuel Pull**

```bash
# SSH ile sunucuya bağlanın
ssh kullanici@wingcert.com

# Git repository'ye gidin
cd /var/www/vhosts/wingcert.com/git/api-service.git

# Güncellemeleri çekin
git pull origin main
```

Eğer Plesk'te otomatik deployment aktifse, dosyalar otomatik kopyalanır. Değilse:

```bash
# Manuel kopyalama (otomatik deployment yoksa)
rsync -av --exclude='.git' --exclude='.env' \
  /var/www/vhosts/wingcert.com/git/api-service.git/ \
  /var/www/vhosts/wingcert.com/httpdocs/api-service/
```

## ⚙️ Cron Job Kurulumu

Senkronizasyonu otomatik çalıştırmak için:

```bash
# SSH ile sunucuya bağlanın
ssh kullanici@wingcert.com

# Crontab'ı düzenleyin
crontab -e

# Aşağıdaki satırı ekleyin (her gün saat 02:00'de)
0 2 * * * cd /var/www/vhosts/wingcert.com/httpdocs/api-service && /usr/bin/php sync/sync_cron.php >> logs/cron.log 2>&1

# Veya her 6 saatte bir:
0 */6 * * * cd /var/www/vhosts/wingcert.com/httpdocs/api-service && /usr/bin/php sync/sync_cron.php >> logs/cron.log 2>&1
```

**PHP yolunu kontrol edin:**
```bash
which php
# Çıktı: /usr/bin/php veya /usr/local/bin/php
```

## 🔒 Güvenlik

### .env Dosyası Güvenliği

```bash
# .env dosyasının izinlerini sınırlayın
chmod 600 /var/www/vhosts/wingcert.com/httpdocs/api-service/.env
```

### Web Erişimi

Bu proje CLI script'leri içerir, web üzerinden erişilebilir olması gerekmez. Eğer web erişimini engellemek isterseniz:

1. Plesk'te **"Apache & nginx Settings"** → **"Additional directives"**
2. Veya `.htaccess` dosyası ekleyin (Apache için)

## 🐛 Sorun Giderme

### Git Pull Hatası

```bash
# Git durumunu kontrol edin
cd /var/www/vhosts/wingcert.com/git/api-service.git
git status

# Eğer conflict varsa
git stash
git pull origin main
git stash pop
```

### İzin Sorunları

```bash
# İzinleri düzeltin (Plesk kullanıcısına göre)
chown -R kullanici:psacln /var/www/vhosts/wingcert.com/git/api-service.git
chown -R kullanici:psacln /var/www/vhosts/wingcert.com/httpdocs/api-service
chmod -R 755 /var/www/vhosts/wingcert.com/httpdocs/api-service
chmod -R 777 /var/www/vhosts/wingcert.com/httpdocs/api-service/logs
```

### .env Dosyası Kaybolursa

```bash
cd /var/www/vhosts/wingcert.com/httpdocs/api-service
cp .env.example .env
nano .env  # Bilgileri tekrar girin
```

### PHP Bulunamıyor

```bash
# PHP yolunu bulun
which php
# veya
/usr/bin/php --version
/usr/local/bin/php --version

# Cron job'da tam yolu kullanın
```

## 📝 Özet Komutlar

### İlk Kurulum

```bash
# Lokal: GitHub'a push
cd /Users/ulukanabaci/Documents/GitHub/api-service
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/KULLANICI_ADINIZ/api-service.git
git push -u origin main

# Sunucu: Clone ve kurulum
cd /var/www/vhosts/wingcert.com/git
git clone https://github.com/KULLANICI_ADINIZ/api-service.git api-service.git
cd /var/www/vhosts/wingcert.com/httpdocs/api-service
cp .env.example .env
# .env dosyasını düzenle
php database/create_tables.php
php test_api.php
```

### Güncelleme

```bash
# Lokal: Değişiklik yap → commit → push
git add . && git commit -m "Mesaj" && git push

# Sunucu: Pull yap (Plesk otomatik deployment varsa)
cd /var/www/vhosts/wingcert.com/git/api-service.git && git pull
```

## ✅ Deployment Checklist

- [ ] GitHub repository oluşturuldu
- [ ] Lokal makineden GitHub'a push edildi
- [ ] Plesk'te Git repository clone edildi
- [ ] Plesk'te Git ayarları yapıldı
- [ ] Deployment path ayarlandı
- [ ] .env dosyası oluşturuldu ve düzenlendi
- [ ] Veritabanı oluşturuldu
- [ ] Tablolar oluşturuldu
- [ ] API testi başarılı
- [ ] Cron job kuruldu
- [ ] İzinler ayarlandı

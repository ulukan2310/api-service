# Manuel GitHub Yükleme ve Online Senkronizasyon Rehberi

## 1. GitHub'a Manuel Yükleme (Lokal Makinede)

### Adım 1: GitHub'da Repository Oluşturun

1. https://github.com adresine gidin
2. Sağ üstteki "+" → "New repository"
3. Repository adı: `parasut-api-integration` (veya istediğiniz isim)
4. **Private** seçin (API credentials içerdiği için önerilir)
5. **"Initialize this repository with a README"** seçeneğini işaretlemeyin
6. "Create repository" butonuna tıklayın

### Adım 2: Lokal Makinede Push Edin

```bash
# Proje dizinine gidin
cd /Users/ulukanabaci/Downloads/parasut_v3

# GitHub repository URL'inizi ekleyin
git remote add origin https://github.com/KULLANICI_ADINIZ/REPO_ADI.git

# Veya SSH kullanıyorsanız:
# git remote add origin git@github.com:KULLANICI_ADINIZ/REPO_ADI.git

# Remote'u kontrol edin
git remote -v

# Ana branch'i main yapın
git branch -M main

# GitHub'a push edin
git push -u origin main
```

**Not:** İlk push'ta GitHub kullanıcı adı ve şifre/token isteyebilir.

## 2. Sunucuda (Plesk) Manuel Clone

### Adım 1: SSH ile Sunucuya Bağlanın

```bash
ssh kullanici@wingcert.com
```

### Adım 2: Git Dizinine Gidin

```bash
cd /var/www/vhosts/wingcert.com/git
```

### Adım 3: GitHub'dan Clone Edin

```bash
# Eski dizin varsa silin (güvenli olmak için önce kontrol edin)
ls -la parasut_v3.git 2>/dev/null && echo "Dizin var, silinecek" || echo "Dizin yok"

# Eğer varsa ve önemli bir şey yoksa silin
rm -rf parasut_v3.git

# GitHub'dan clone edin
git clone https://github.com/KULLANICI_ADINIZ/REPO_ADI.git parasut_v3.git

# Veya SSH ile (eğer SSH key ayarladıysanız):
# git clone git@github.com:KULLANICI_ADINIZ/REPO_ADI.git parasut_v3.git
```

### Adım 4: Branch Kontrolü

```bash
cd parasut_v3.git

# Hangi branch'te olduğunuzu kontrol edin
git branch

# Eğer main branch yoksa:
git checkout -b main
# veya
git branch -M main

# Remote'u kontrol edin
git remote -v
```

### Adım 5: İzinleri Ayarlayın

```bash
# Plesk kullanıcısına göre izinleri ayarlayın
# Genellikle psacln grubu kullanılır
chown -R kullanici:psacln /var/www/vhosts/wingcert.com/git/parasut_v3.git

# Veya sadece kullanıcıya
chown -R kullanici:kullanici /var/www/vhosts/wingcert.com/git/parasut_v3.git
```

## 3. Plesk'te Deployment Ayarları

### Adım 1: Plesk'te Git Bölümüne Gidin

1. Plesk paneline girin
2. Domain'inize gidin (wingcert.com)
3. Sol menüden "Git" bölümüne tıklayın

### Adım 2: Mevcut Repository'yi Bağlayın

Eğer Plesk zaten `parasut_v3.git` dizinini görüyorsa:
- "Refresh" veya "Pull" butonuna tıklayın
- Repository otomatik olarak tanınacaktır

Eğer görmüyorsa:
1. "Enable Git" butonuna tıklayın
2. Repository path: `/var/www/vhosts/wingcert.com/git/parasut_v3.git`
3. Branch: `main`
4. "Save" butonuna tıklayın

### Adım 3: Deployment Path Ayarlayın

1. "Deployment" sekmesine gidin
2. Deployment path: `/var/www/vhosts/wingcert.com/httpdocs/parasut_v3` (veya istediğiniz path)
3. "Enable automatic deployment" seçeneğini işaretleyin
4. "Save" butonuna tıklayın

## 4. İlk Kurulum (Sunucuda)

### Adım 1: Deployment Path'e Gidin

```bash
cd /var/www/vhosts/wingcert.com/httpdocs/parasut_v3
```

### Adım 2: .env Dosyasını Oluşturun

```bash
# .env.example'dan kopyalayın
cp .env.example .env

# Düzenleyin
nano .env
# veya
vi .env
```

Sunucu bilgilerinizi girin:
- Veritabanı bilgileri
- Paraşüt API credentials
- Email ayarları (opsiyonel)

### Adım 3: Veritabanını Oluşturun

Plesk'te veya SSH ile:

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

### Adım 4: Tabloları Oluşturun

```bash
cd /var/www/vhosts/wingcert.com/httpdocs/parasut_v3
php database/create_tables.php
```

### Adım 5: Test Edin

```bash
# Authentication testi
php tests/test_auth.php

# İlk senkronizasyon (küçük bir tablo ile)
php sync/sync.php contacts
```

## 5. Güncelleme Süreci (Online Senkronizasyon)

### Yöntem 1: Plesk Arayüzünden

1. Plesk'te Git bölümüne gidin
2. "Pull" butonuna tıklayın
3. Plesk otomatik olarak deployment path'e kopyalayacak

### Yöntem 2: SSH ile Manuel Pull

```bash
# Git repository'ye gidin
cd /var/www/vhosts/wingcert.com/git/parasut_v3.git

# Son değişiklikleri çekin
git pull origin main

# Deployment path'e kopyalayın (eğer otomatik deployment yoksa)
rsync -av --exclude='.git' --exclude='.env' \
  /var/www/vhosts/wingcert.com/git/parasut_v3.git/ \
  /var/www/vhosts/wingcert.com/httpdocs/parasut_v3/
```

### Yöntem 3: Otomatik Deployment Script

Plesk'te "Enable automatic deployment" seçeneğini işaretlediyseniz, her `git pull` sonrası otomatik kopyalanır.

## 6. Günlük Kullanım

### Lokal Makinede Değişiklik Yaptıktan Sonra

```bash
# Lokal makinede
cd /Users/ulukanabaci/Downloads/parasut_v3

# Değişiklikleri commit edin
git add .
git commit -m "Değişiklik açıklaması"

# GitHub'a push edin
git push origin main
```

### Sunucuda Güncelleme

```bash
# SSH ile sunucuya bağlanın
ssh kullanici@wingcert.com

# Git repository'ye gidin
cd /var/www/vhosts/wingcert.com/git/parasut_v3.git

# Güncellemeleri çekin
git pull origin main
```

Eğer Plesk'te otomatik deployment aktifse, dosyalar otomatik kopyalanır. Değilse:

```bash
# Manuel kopyalama
rsync -av --exclude='.git' --exclude='.env' \
  /var/www/vhosts/wingcert.com/git/parasut_v3.git/ \
  /var/www/vhosts/wingcert.com/httpdocs/parasut_v3/
```

## 7. Cron Job Kurulumu

```bash
# SSH ile sunucuya bağlanın
ssh kullanici@wingcert.com

# Crontab'ı düzenleyin
crontab -e

# Aşağıdaki satırı ekleyin (her gün saat 02:00'de)
0 2 * * * cd /var/www/vhosts/wingcert.com/httpdocs/parasut_v3 && /usr/bin/php sync/sync_cron.php >> logs/cron.log 2>&1
```

## 8. Sorun Giderme

### Git Pull Hatası

```bash
# Git durumunu kontrol edin
cd /var/www/vhosts/wingcert.com/git/parasut_v3.git
git status

# Eğer conflict varsa
git stash
git pull origin main
git stash pop
```

### İzin Sorunları

```bash
# İzinleri düzeltin
chown -R kullanici:psacln /var/www/vhosts/wingcert.com/git/parasut_v3.git
chown -R kullanici:psacln /var/www/vhosts/wingcert.com/httpdocs/parasut_v3
```

### .env Dosyası Kaybolursa

```bash
cd /var/www/vhosts/wingcert.com/httpdocs/parasut_v3
cp .env.example .env
nano .env  # Bilgileri tekrar girin
```

## Özet Komutlar

### Lokal Makinede (İlk Kez)
```bash
cd /Users/ulukanabaci/Downloads/parasut_v3
git remote add origin https://github.com/KULLANICI_ADINIZ/REPO_ADI.git
git push -u origin main
```

### Sunucuda (İlk Kez)
```bash
cd /var/www/vhosts/wingcert.com/git
git clone https://github.com/KULLANICI_ADINIZ/REPO_ADI.git parasut_v3.git
cd /var/www/vhosts/wingcert.com/httpdocs
# Plesk'te deployment path ayarlayın veya manuel kopyalayın
```

### Güncelleme (Her Seferinde)
```bash
# Lokal: Değişiklik yap → commit → push
git add . && git commit -m "Mesaj" && git push

# Sunucu: Pull yap
cd /var/www/vhosts/wingcert.com/git/parasut_v3.git && git pull
```

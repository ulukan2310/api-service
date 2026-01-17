# Kurulum Talimatları

## PHP Kurulumu (macOS)

### Yöntem 1: Homebrew ile (Önerilen)

1. **Homebrew kurulumu** (eğer yoksa):
   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```

2. **PHP kurulumu**:
   ```bash
   brew install php
   ```

3. **Kurulumu kontrol edin**:
   ```bash
   php --version
   ```

### Yöntem 2: Script ile

```bash
chmod +x setup_php.sh
./setup_php.sh
```

### Yöntem 3: XAMPP/MAMP

- [XAMPP](https://www.apachefriends.org/) veya [MAMP](https://www.mamp.info/) indirip kurabilirsiniz
- Bu durumda PHP genellikle `/Applications/XAMPP/bin/php` veya `/Applications/MAMP/bin/php/php8.x.x/bin/php` yolunda olur

## Proje Kurulumu

### 1. .env Dosyası Oluşturma

```bash
cp .env.example .env
```

Sonra `.env` dosyasını düzenleyip Paraşüt API bilgilerinizi girin:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=parasut_db
DB_USER=root
DB_PASSWORD=your_db_password

# Paraşüt API v2 Configuration
PARASUT_API_BASE_URL=https://api.parasut.com/v2
PARASUT_CLIENT_ID=your_client_id_here
PARASUT_CLIENT_SECRET=your_client_secret_here
PARASUT_USERNAME=your_username_here
PARASUT_PASSWORD=your_password_here
PARASUT_COMPANY_ID=your_company_id_here
```

### 2. Veritabanı Kurulumu

MySQL/MariaDB'de veritabanı oluşturun:

```sql
CREATE DATABASE parasut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Tabloları oluşturun:

```bash
php database/create_tables.php
```

### 3. API Testi

```bash
php test_api.php
```

Bu script şunları test eder:
- ✅ Konfigürasyon yükleme
- ✅ Veritabanı bağlantısı
- ✅ API Authentication (token alma)
- ✅ API Client (endpoint testi)

### 4. Detaylı Test

```bash
php tests/test_auth.php
```

### 5. Senkronizasyon

Tüm tabloları senkronize etmek için:

```bash
php sync/sync.php
```

Belirli tabloları senkronize etmek için:

```bash
php sync/sync.php contacts products
```

## Sorun Giderme

### PHP Bulunamıyor

Eğer `php` komutu bulunamıyorsa:

1. PHP'nin kurulu olduğundan emin olun
2. PATH'e ekleyin:
   ```bash
   export PATH="/usr/local/bin:$PATH"
   ```
3. Veya tam yol ile çalıştırın:
   ```bash
   /usr/local/bin/php test_api.php
   ```

### Veritabanı Bağlantı Hatası

- MySQL servisinin çalıştığından emin olun
- `.env` dosyasındaki DB bilgilerini kontrol edin
- Veritabanının oluşturulduğundan emin olun

### API Authentication Hatası

- Paraşüt API bilgilerinizi kontrol edin
- Client ID, Client Secret, Username, Password doğru mu?
- Paraşüt API v2 hesabınız aktif mi?

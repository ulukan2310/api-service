# Entegrasyon Dokümantasyonu

## Yapılan Değişiklikler

### 1. Database Configuration (`config/database.php`)

**Özellikler:**
- ✅ Hem class tabanlı (`Database` class) hem fonksiyon tabanlı (`getDBConnection()`) kullanım
- ✅ `.env` dosyası desteği
- ✅ `define()` ile direkt yapılandırma desteği
- ✅ Geriye dönük uyumluluk (mevcut kod çalışmaya devam eder)

**Kullanım:**

```php
// Fonksiyon tabanlı (form_management stili)
require_once 'config/database.php';
$pdo = getDBConnection();

// Class tabanlı (api-service stili)
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
```

### 2. Paraşüt API Configuration (`config/parasut_api.php`)

**Özellikler:**
- ✅ Paraşüt API v4 desteği (form_management'teki gibi)
- ✅ `.env` dosyası desteği
- ✅ `define()` ile direkt yapılandırma desteği
- ✅ Fonksiyon tabanlı API (form_management stili)
- ✅ Token cache desteği
- ✅ Pagination desteği
- ✅ Rate limiting kontrolü
- ✅ JSON:API format desteği

**Kullanım:**

```php
// Token alma
require_once 'config/parasut_api.php';
require_once 'config/database.php';

$pdo = getDBConnection();
$accessToken = getParasutAccessToken($pdo);

// API isteği
$response = parasutApiRequest('contacts', $accessToken, ['page[size]' => 25]);

// Tüm sayfaları çek
$result = fetchAllParasutPages('contacts', $accessToken);
$allContacts = $result['data'];
```

### 3. .env Dosyası

Mevcut form_management bilgileri `.env` dosyasına eklendi:
- Veritabanı bilgileri
- Paraşüt API v4 credentials
- Company ID

## Kullanım Senaryoları

### Senaryo 1: Mevcut form_management Kodu ile Uyumluluk

```php
// form_management'teki kod aynen çalışır
require_once 'config/database.php';
require_once 'config/parasut_api.php';

$pdo = getDBConnection();
$accessToken = getParasutAccessToken($pdo);
$contacts = fetchAllParasutPagesLegacy('contacts', $accessToken);
```

### Senaryo 2: Yeni api-service Kodu ile

```php
// Class tabanlı kullanım
require_once 'config/database.php';
require_once 'config/parasut_api.php';

$db = new Database();
$conn = $db->getConnection();

$pdo = getDBConnection(); // Fonksiyon tabanlı da kullanılabilir
$accessToken = getParasutAccessToken($pdo);
```

### Senaryo 3: .env ile Yapılandırma

`.env` dosyasından otomatik olarak yüklenir:
```env
DB_HOST=localhost
DB_NAME=admin_
PARASUT_CLIENT_ID=...
```

### Senaryo 4: define() ile Yapılandırma

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'admin_');
define('PARASUT_CLIENT_ID', '...');
require_once 'config/database.php';
require_once 'config/parasut_api.php';
```

## API Versiyon Farkları

### Paraşüt API v2 (Eski - api-service)
- Base URL: `https://api.parasut.com/v2`
- Endpoint format: `/company_id/contacts`
- Response format: Standart JSON

### Paraşüt API v4 (Yeni - form_management)
- Base URL: `https://api.parasut.com/v4`
- Endpoint format: `/v4/company_id/contacts`
- Response format: JSON:API standardı
- Pagination: `page[size]` ve `page[number]` parametreleri

## Migration Notları

### Mevcut api-service Kodları İçin

Eğer mevcut `ParasutAPI` class'ını kullanıyorsanız:
- `config/api.php` hala mevcut (v2 için)
- Yeni kodlar için `config/parasut_api.php` kullanın (v4 için)

### Mevcut form_management Kodları İçin

Form_management'teki kodlar aynen çalışır:
- `getDBConnection()` fonksiyonu
- `getParasutAccessToken()` fonksiyonu
- `parasutApiRequest()` fonksiyonu
- `fetchAllParasutPages()` fonksiyonu

## Test

```bash
# Test scripti çalıştır
php test_api.php

# Veya manuel test
php -r "
require_once 'config/database.php';
require_once 'config/parasut_api.php';
\$pdo = getDBConnection();
echo 'DB Connection: OK\n';
\$token = getParasutToken();
echo 'Token: ' . substr(\$token['access_token'], 0, 20) . '...\n';
"
```

## Önemli Notlar

1. **API Versiyonu:** Artık Paraşüt API v4 kullanılıyor (form_management'teki gibi)
2. **Geriye Dönük Uyumluluk:** Mevcut kodlar çalışmaya devam eder
3. **.env Önceliği:** `.env` dosyası varsa, `define()` değerlerini override eder
4. **Token Cache:** Token'lar veritabanında cache'lenir (`parasut_token_cache` tablosu)

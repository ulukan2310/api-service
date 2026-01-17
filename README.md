# Paraşüt API v2 Entegrasyon Projesi

Paraşüt API v2'den veri çekmek için PHP/MySQL tabanlı senkronizasyon sistemi. OAuth 2.0 authentication ile güvenli bağlantı kurar ve 10 farklı tabloyu senkronize eder.

## Özellikler

- ✅ OAuth 2.0 Password Grant Flow ile güvenli authentication
- ✅ Token cache ve otomatik yenileme
- ✅ 10 tablo için tam senkronizasyon desteği
- ✅ İlişkisel veri yönetimi (foreign keys)
- ✅ Pagination desteği ile tüm verilerin çekilmesi
- ✅ Hata yönetimi ve loglama
- ✅ Cron job desteği
- ✅ Rate limiting kontrolü

## Proje Yapısı

```
parasut_v3/
├── config/
│   ├── database.php          # MySQL bağlantı sınıfı
│   └── api.php               # Paraşüt API v2 ayarları
├── api/
│   ├── auth.php              # OAuth 2.0 kimlik doğrulama
│   └── client.php            # HTTP istekleri wrapper
├── database/
│   ├── schema.sql            # Tüm tabloların CREATE scriptleri
│   └── create_tables.php     # Tabloları oluşturan script
├── models/
│   ├── Contact.php           # Müşteri/Tedarikçi modeli
│   ├── Product.php           # Ürün modeli
│   ├── SalesInvoice.php      # Satış faturası modeli
│   ├── PurchaseBill.php      # Alış faturası modeli
│   ├── Payment.php           # Ödeme modeli
│   ├── Account.php           # Hesap modeli
│   ├── Tag.php               # Etiket modeli
│   └── TagRelation.php       # Etiket ilişkisi modeli
├── sync/
│   ├── sync.php              # Ana senkronizasyon scripti
│   └── sync_cron.php         # Cron için optimize edilmiş script
├── logs/
│   └── .gitkeep
├── tests/
│   └── test_auth.php         # Authentication testi
├── .env.example
├── .gitignore
└── README.md
```

## Gereksinimler

- PHP 7.4 veya üzeri
- MySQL 5.7+ veya MariaDB 10.2+
- cURL extension
- PDO MySQL extension
- Paraşüt API v2 hesabı ve credentials

## Kurulum

### 1. Projeyi İndirin

```bash
git clone <repository-url>
cd parasut_v3
```

### 2. Environment Variables Ayarlayın

`.env.example` dosyasını `.env` olarak kopyalayın:

```bash
cp .env.example .env
```

`.env` dosyasını düzenleyin ve Paraşüt API bilgilerinizi girin:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=parasut_db
DB_USER=root
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4

# Paraşüt API v2 Configuration
PARASUT_API_BASE_URL=https://api.parasut.com/v2
PARASUT_CLIENT_ID=your_client_id_here
PARASUT_CLIENT_SECRET=your_client_secret_here
PARASUT_USERNAME=your_username_here
PARASUT_PASSWORD=your_password_here
PARASUT_COMPANY_ID=your_company_id_here

# Optional: Email alerts for cron errors
ALERT_EMAIL=admin@example.com
ALERT_FROM_EMAIL=noreply@example.com
```

### 3. Veritabanını Oluşturun

Önce MySQL'de veritabanını oluşturun:

```sql
CREATE DATABASE parasut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sonra tabloları oluşturun:

```bash
php database/create_tables.php
```

Bu script 10 tabloyu otomatik olarak oluşturur:
1. `parasut_token_cache` - OAuth token'ları
2. `parasut_contacts` - Müşteri/Tedarikçi
3. `parasut_products` - Ürün/Hizmet
4. `parasut_accounts` - Kasa/Banka hesapları
5. `parasut_sales_invoices` - Satış faturaları
6. `parasut_purchase_bills` - Alış faturaları
7. `parasut_payments` - Ödemeler
8. `parasut_tags` - Etiketler
9. `parasut_tag_relations` - Etiket ilişkileri
10. `parasut_sync_log` - Senkronizasyon logları

### 4. Authentication Testi

API bağlantısını test edin:

```bash
php tests/test_auth.php
```

Başarılı olursa token alınmış ve cache'lenmiştir.

## Kullanım

### Manuel Senkronizasyon

Tüm tabloları senkronize etmek için:

```bash
php sync/sync.php
```

Belirli tabloları senkronize etmek için:

```bash
php sync/sync.php contacts products
```

Mevcut tablolar:
- `contacts`
- `products`
- `accounts`
- `sales_invoices`
- `purchase_bills`
- `payments`
- `tags`

### Otomatik Senkronizasyon (Cron Job)

Cron job kurulumu için `crontab -e` komutunu çalıştırın:

```bash
# Her gün saat 02:00'de tüm tabloları senkronize et
0 2 * * * cd /path/to/parasut_v3 && php sync/sync_cron.php >> logs/cron.log 2>&1

# Her 6 saatte bir sadece contacts ve products'ı senkronize et
0 */6 * * * cd /path/to/parasut_v3 && php sync/sync_cron.php contacts products >> logs/cron.log 2>&1
```

**Önemli:** `/path/to/parasut_v3` kısmını kendi proje yolunuzla değiştirin.

### Log Dosyaları

Senkronizasyon logları `logs/` klasöründe saklanır:
- `logs/sync_YYYY-MM-DD.log` - Günlük sync logları
- `logs/cron.log` - Cron job logları (eğer cron kullanıyorsanız)

Ayrıca tüm senkronizasyon işlemleri `parasut_sync_log` tablosuna kaydedilir.

## Veritabanı Şeması

### İlişkiler

- `parasut_sales_invoices.contact_id` → `parasut_contacts.id`
- `parasut_purchase_bills.contact_id` → `parasut_contacts.id`
- `parasut_payments.account_id` → `parasut_accounts.id`
- `parasut_payments.contact_id` → `parasut_contacts.id`
- `parasut_tag_relations.tag_id` → `parasut_tags.id`

### Önemli Alanlar

Her tabloda:
- `parasut_id` - Paraşüt'ten gelen unique ID
- `raw_data` - JSON formatında ham API verisi
- `created_at` / `updated_at` - Timestamp alanları

## API Kullanımı

### Authentication

```php
require_once 'api/auth.php';

$auth = new ParasutAuth();

// Geçerli token al (cache'den veya yeni)
$token = $auth->getValidToken();

// Yeni token al
$tokenData = $auth->getToken();

// Token yenile
$tokenData = $auth->refreshToken();
```

### API Client

```php
require_once 'api/client.php';

$client = new ParasutClient();

// GET isteği
$response = $client->get('/12345/contacts', ['page' => 1]);

// Tüm kayıtları çek (pagination ile)
$allContacts = $client->getAll('/12345/contacts');

// POST isteği
$response = $client->post('/12345/contacts', ['data' => [...]]);
```

### Model Kullanımı

```php
require_once 'models/Contact.php';

$contact = new Contact();

// API'den tüm contact'ları çek ve kaydet
$stats = $contact->syncFromAPI();

// Tek bir contact kaydet
$result = $contact->save($apiData);

// Paraşüt ID ile bul
$contact = $contact->findByParasutId('12345');
```

## Troubleshooting

### Token Alma Hatası

**Sorun:** "Token alma hatası (HTTP 401)"

**Çözüm:**
- `.env` dosyasındaki credentials'ları kontrol edin
- Client ID, Client Secret, Username, Password doğru mu?
- Paraşüt API v2 hesabınız aktif mi?

### Veritabanı Bağlantı Hatası

**Sorun:** "Veritabanına bağlanılamadı"

**Çözüm:**
- MySQL servisinin çalıştığından emin olun
- `.env` dosyasındaki DB bilgilerini kontrol edin
- Veritabanının oluşturulduğundan emin olun
- Kullanıcı yetkilerini kontrol edin

### Foreign Key Hatası

**Sorun:** "Cannot add or update a child row: a foreign key constraint fails"

**Çözüm:**
- İlişkili tabloların önce senkronize edilmesi gerekir
- Örnek: `contacts` → `sales_invoices` sırası önemli
- Tüm tabloları sırayla senkronize edin: `php sync/sync.php`

### Rate Limiting

**Sorun:** "Rate limit aşıldı" hatası

**Çözüm:**
- Paraşüt API rate limit'ine dikkat edin
- Büyük veri setleri için sync'i parçalara bölün
- `sync_cron.php` kullanarak düzenli aralıklarla çalıştırın

### Pagination Sorunları

**Sorun:** Bazı kayıtlar çekilmiyor

**Çözüm:**
- `api/client.php` içindeki `getAll()` metodunu kontrol edin
- API response yapısını kontrol edin (meta/links)
- Log dosyalarını inceleyin

## Güvenlik

- `.env` dosyası `.gitignore`'da olduğundan emin olun
- API credentials'ları asla commit etmeyin
- Production ortamında `.env` dosyasına sadece gerekli kişiler erişebilmeli
- Token'lar veritabanında saklanıyor, gerekirse şifreleme eklenebilir

## Geliştirme

### Yeni Model Ekleme

1. `models/` klasörüne yeni model dosyası ekleyin
2. `sync/sync.php` içindeki `$availableTables` array'ine ekleyin
3. `database/schema.sql`'e yeni tablo ekleyin
4. Model sınıfı `syncFromAPI()`, `save()`, `findByParasutId()` metodlarını içermeli

### Loglama

Tüm senkronizasyon işlemleri:
- `parasut_sync_log` tablosuna kaydedilir
- `logs/` klasöründe dosya olarak saklanır
- Hata durumlarında email gönderilebilir (cron için)

## Lisans

Bu proje özel kullanım içindir.

## Destek

Sorunlar için:
1. Log dosyalarını kontrol edin
2. `parasut_sync_log` tablosunu inceleyin
3. Paraşüt API dokümantasyonunu kontrol edin: https://apidoc.parasut.com

## Changelog

### v1.0.0
- İlk sürüm
- 10 tablo için senkronizasyon desteği
- OAuth 2.0 authentication
- Cron job desteği
- Kapsamlı loglama

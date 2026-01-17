<?php
/**
 * Basit API Test Scripti
 * API bağlantısını ve token alma işlemini test eder
 * 
 * Kullanım: php test_api.php
 */

echo "========================================\n";
echo "Paraşüt API v2 - Hızlı Test\n";
echo "========================================\n\n";

// .env dosyası kontrolü
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "⚠️  .env dosyası bulunamadı!\n\n";
    echo "Lütfen önce .env dosyasını oluşturun:\n";
    echo "1. .env.example dosyasını .env olarak kopyalayın\n";
    echo "2. Paraşüt API bilgilerinizi girin\n\n";
    echo "Komut: cp .env.example .env\n";
    exit(1);
}

echo "✓ .env dosyası bulundu\n\n";

// Test 1: Config yükleme
echo "1. Konfigürasyon yükleniyor...\n";
try {
    require_once __DIR__ . '/config/api.php';
    $apiConfig = new ParasutAPI();
    echo "   ✓ API konfigürasyonu yüklendi\n";
    echo "   Base URL: " . $apiConfig->getBaseUrl() . "\n";
    echo "   Company ID: " . ($apiConfig->getCompanyId() ?: 'AYARLANMAMIŞ') . "\n\n";
} catch (Exception $e) {
    echo "   ✗ HATA: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Veritabanı bağlantısı (opsiyonel - token cache için)
echo "2. Veritabanı bağlantısı kontrol ediliyor...\n";
try {
    require_once __DIR__ . '/config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    echo "   ✓ Veritabanına bağlanıldı\n\n";
} catch (Exception $e) {
    echo "   ⚠️  Veritabanı hatası: " . $e->getMessage() . "\n";
    echo "   (Token cache kullanılamayacak, ancak API testi devam edebilir)\n\n";
}

// Test 3: Authentication
echo "3. API Authentication testi...\n";
try {
    require_once __DIR__ . '/api/auth.php';
    $auth = new ParasutAuth();
    
    echo "   Token alınıyor...\n";
    $tokenData = $auth->getToken();
    
    echo "   ✓ Token başarıyla alındı!\n";
    echo "   Token Type: " . ($tokenData['token_type'] ?? 'N/A') . "\n";
    echo "   Expires In: " . ($tokenData['expires_in'] ?? 'N/A') . " saniye\n";
    
    if (isset($tokenData['access_token'])) {
        $tokenPreview = substr($tokenData['access_token'], 0, 30) . '...';
        echo "   Access Token: " . $tokenPreview . "\n";
    }
    
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ HATA: " . $e->getMessage() . "\n";
    echo "\n   Olası nedenler:\n";
    echo "   - Client ID veya Client Secret yanlış\n";
    echo "   - Username veya Password yanlış\n";
    echo "   - Paraşüt API hesabınız aktif değil\n";
    echo "   - İnternet bağlantısı yok\n";
    exit(1);
}

// Test 4: API Client (basit bir istek)
echo "4. API Client testi (basit endpoint kontrolü)...\n";
try {
    require_once __DIR__ . '/api/client.php';
    $client = new ParasutClient();
    
    $apiConfig = new ParasutAPI();
    $companyId = $apiConfig->getCompanyId();
    
    if (empty($companyId) || $companyId === 'your_company_id_here') {
        echo "   ⚠️  Company ID ayarlanmamış, endpoint testi atlanıyor\n";
    } else {
        echo "   Contacts endpoint'ine test isteği gönderiliyor...\n";
        $endpoint = '/' . $companyId . '/contacts';
        $response = $client->get($endpoint, ['page' => 1, 'per_page' => 1]);
        
        if (isset($response['data'])) {
            echo "   ✓ API isteği başarılı!\n";
            echo "   Toplam kayıt sayısı: " . ($response['meta']['total_count'] ?? 'Bilinmiyor') . "\n";
        } else {
            echo "   ⚠️  API yanıtı beklenen formatta değil\n";
        }
    }
    
    echo "\n";
} catch (Exception $e) {
    echo "   ⚠️  API isteği hatası: " . $e->getMessage() . "\n";
    echo "   (Token alındı, ancak endpoint testi başarısız)\n\n";
}

echo "========================================\n";
echo "✅ Test tamamlandı!\n";
echo "========================================\n";
echo "\nSonraki adımlar:\n";
echo "1. Veritabanı tablolarını oluşturun: php database/create_tables.php\n";
echo "2. Detaylı test için: php tests/test_auth.php\n";
echo "3. Senkronizasyon için: php sync/sync.php\n";

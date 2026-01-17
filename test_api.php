<?php
/**
 * Basit API Test Scripti
 * API bağlantısını ve token alma işlemini test eder
 * 
 * Kullanım: php test_api.php
 */

echo "========================================\n";
echo "Paraşüt API v4 - Hızlı Test\n";
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
    require_once __DIR__ . '/config/parasut_api.php';
    echo "   ✓ API konfigürasyonu yüklendi\n";
    echo "   Base URL: " . PARASUT_BASE_URL . "/" . PARASUT_API_VERSION . "/" . PARASUT_COMPANY_ID . "\n";
    echo "   Company ID: " . PARASUT_COMPANY_ID . "\n\n";
} catch (Exception $e) {
    echo "   ✗ HATA: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Veritabanı bağlantısı (opsiyonel - token cache için)
echo "2. Veritabanı bağlantısı kontrol ediliyor...\n";
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getDBConnection();
    echo "   ✓ Veritabanına bağlanıldı\n\n";
} catch (Exception $e) {
    echo "   ⚠️  Veritabanı hatası: " . $e->getMessage() . "\n";
    echo "   (Token cache kullanılamayacak, ancak API testi devam edebilir)\n\n";
    $pdo = null;
}

// Test 3: Authentication
echo "3. API Authentication testi...\n";
try {
    echo "   Token alınıyor...\n";
    $tokenData = getParasutToken();
    
    echo "   ✓ Token başarıyla alındı!\n";
    echo "   Token Type: " . ($tokenData['token_type'] ?? 'Bearer') . "\n";
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
    $accessToken = $tokenData['access_token'];
    
    echo "   Contacts endpoint'ine test isteği gönderiliyor...\n";
    $response = parasutApiRequest('contacts', $accessToken, ['page[size]' => 1, 'page[number]' => 1]);
    
    if (isset($response['data'])) {
        echo "   ✓ API isteği başarılı!\n";
        $recordCount = count($response['data']);
        $totalCount = $response['meta']['total_count'] ?? 'Bilinmiyor';
        echo "   Bu sayfada: $recordCount kayıt\n";
        echo "   Toplam kayıt sayısı: $totalCount\n";
    } else {
        echo "   ⚠️  API yanıtı beklenen formatta değil\n";
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

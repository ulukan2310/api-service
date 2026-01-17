<?php
/**
 * Authentication Test Script
 * Token alma ve cache testi
 * 
 * Kullanım: php tests/test_auth.php
 */

require_once __DIR__ . '/../api/auth.php';

echo "========================================\n";
echo "Paraşüt API v2 Authentication Testi\n";
echo "========================================\n\n";

try {
    $auth = new ParasutAuth();
    
    echo "1. Yeni token alınıyor...\n";
    $tokenData = $auth->getToken();
    echo "   ✓ Token başarıyla alındı\n";
    echo "   Token Type: " . ($tokenData['token_type'] ?? 'N/A') . "\n";
    echo "   Expires In: " . ($tokenData['expires_in'] ?? 'N/A') . " saniye\n\n";
    
    echo "2. Cache'den token kontrol ediliyor...\n";
    $cachedToken = $auth->getCachedToken();
    if ($cachedToken) {
        echo "   ✓ Token cache'de bulundu\n";
        echo "   Expires At: " . $cachedToken['expires_at'] . "\n\n";
    } else {
        echo "   ✗ Token cache'de bulunamadı\n\n";
    }
    
    echo "3. Token geçerliliği kontrol ediliyor...\n";
    if ($auth->isTokenValid()) {
        echo "   ✓ Token geçerli\n\n";
    } else {
        echo "   ✗ Token geçersiz veya süresi dolmuş\n\n";
    }
    
    echo "4. Geçerli token alınıyor (getValidToken)...\n";
    $validToken = $auth->getValidToken();
    if ($validToken) {
        echo "   ✓ Geçerli token alındı\n";
        echo "   Token (ilk 20 karakter): " . substr($validToken, 0, 20) . "...\n\n";
    } else {
        echo "   ✗ Token alınamadı\n\n";
    }
    
    echo "========================================\n";
    echo "Test tamamlandı - Başarılı!\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "\n✗ HATA: " . $e->getMessage() . "\n";
    echo "Dosya: " . $e->getFile() . "\n";
    echo "Satır: " . $e->getLine() . "\n";
    exit(1);
}

<?php
/**
 * Paraşüt API v2 Authentication Class
 * OAuth 2.0 Password Grant Flow ile token yönetimi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/api.php';

class ParasutAuth {
    private $apiConfig;
    private $db;
    private $conn;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->apiConfig = new ParasutAPI();
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Yeni token al (Password Grant Flow)
     * @return array Token bilgileri
     * @throws Exception Token alma hatası
     */
    public function getToken() {
        $baseUrl = $this->apiConfig->getBaseUrl();
        $endpoint = $baseUrl . '/oauth/token';
        
        $data = [
            'grant_type' => 'password',
            'client_id' => $this->apiConfig->getClientId(),
            'client_secret' => $this->apiConfig->getClientSecret(),
            'username' => $this->apiConfig->getUsername(),
            'password' => $this->apiConfig->getPassword(),
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL hatası: " . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error_description'] ?? $errorData['error'] ?? 'Bilinmeyen hata';
            throw new Exception("Token alma hatası (HTTP $httpCode): " . $errorMsg);
        }
        
        $tokenData = json_decode($response, true);
        
        if (!isset($tokenData['access_token'])) {
            throw new Exception("Token yanıtı geçersiz: access_token bulunamadı");
        }
        
        // Token'ı kaydet
        $this->saveToken($tokenData);
        
        return $tokenData;
    }
    
    /**
     * Token'ı yenile (Refresh Token)
     * @return array Yeni token bilgileri
     * @throws Exception Token yenileme hatası
     */
    public function refreshToken() {
        $cachedToken = $this->getCachedToken();
        
        if (!$cachedToken || empty($cachedToken['refresh_token'])) {
            // Refresh token yoksa yeni token al
            return $this->getToken();
        }
        
        $baseUrl = $this->apiConfig->getBaseUrl();
        $endpoint = $baseUrl . '/oauth/token';
        
        $data = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->apiConfig->getClientId(),
            'client_secret' => $this->apiConfig->getClientSecret(),
            'refresh_token' => $cachedToken['refresh_token'],
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL hatası: " . $error);
        }
        
        if ($httpCode !== 200) {
            // Refresh token geçersizse yeni token al
            return $this->getToken();
        }
        
        $tokenData = json_decode($response, true);
        
        if (!isset($tokenData['access_token'])) {
            throw new Exception("Token yenileme yanıtı geçersiz");
        }
        
        // Yeni token'ı kaydet
        $this->saveToken($tokenData);
        
        return $tokenData;
    }
    
    /**
     * Token'ın geçerli olup olmadığını kontrol et
     * @return bool
     */
    public function isTokenValid() {
        $cachedToken = $this->getCachedToken();
        
        if (!$cachedToken) {
            return false;
        }
        
        // Expires_at kontrolü (5 dakika buffer ile)
        $expiresAt = new DateTime($cachedToken['expires_at']);
        $now = new DateTime();
        $buffer = new DateInterval('PT5M'); // 5 dakika
        
        return $expiresAt > $now->add($buffer);
    }
    
    /**
     * Geçerli token'ı al (cache'den veya yeni)
     * @return string Access token
     * @throws Exception
     */
    public function getValidToken() {
        // Önce cache'den kontrol et
        if ($this->isTokenValid()) {
            $cachedToken = $this->getCachedToken();
            return $cachedToken['access_token'];
        }
        
        // Geçersizse yenile
        try {
            $tokenData = $this->refreshToken();
            return $tokenData['access_token'];
        } catch (Exception $e) {
            // Refresh başarısızsa yeni token al
            $tokenData = $this->getToken();
            return $tokenData['access_token'];
        }
    }
    
    /**
     * Cache'den token'ı al
     * @return array|null Token bilgileri veya null
     */
    public function getCachedToken() {
        try {
            $stmt = $this->conn->prepare("
                SELECT access_token, refresh_token, token_type, expires_at 
                FROM parasut_token_cache 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Token cache okuma hatası: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Token'ı veritabanına kaydet
     * @param array $tokenData Token bilgileri
     * @return bool
     */
    private function saveToken($tokenData) {
        try {
            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $tokenType = $tokenData['token_type'] ?? 'Bearer';
            $expiresIn = $tokenData['expires_in'] ?? 3600; // Varsayılan 1 saat
            
            // Expires_at hesapla
            $expiresAt = new DateTime();
            $expiresAt->add(new DateInterval('PT' . $expiresIn . 'S'));
            
            // Önce eski token'ları sil (opsiyonel - sadece bir token tutmak için)
            $this->conn->exec("DELETE FROM parasut_token_cache");
            
            // Yeni token'ı kaydet
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_token_cache 
                (access_token, refresh_token, token_type, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $accessToken,
                $refreshToken,
                $tokenType,
                $expiresAt->format('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Token kaydetme hatası: " . $e->getMessage());
            throw new Exception("Token kaydedilemedi: " . $e->getMessage());
        }
    }
}

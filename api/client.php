<?php
/**
 * Paraşüt API v2 HTTP Client
 * API istekleri için wrapper sınıfı
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/api.php';

class ParasutClient {
    private $apiConfig;
    private $auth;
    private $baseUrl;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->apiConfig = new ParasutAPI();
        $this->auth = new ParasutAuth();
        $this->baseUrl = $this->apiConfig->getBaseUrl();
    }
    
    /**
     * GET isteği
     * @param string $endpoint API endpoint
     * @param array $params Query parametreleri
     * @return array JSON response
     * @throws Exception
     */
    public function get($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * POST isteği
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @return array JSON response
     * @throws Exception
     */
    public function post($endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('POST', $url, $data);
    }
    
    /**
     * PUT isteği
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @return array JSON response
     * @throws Exception
     */
    public function put($endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('PUT', $url, $data);
    }
    
    /**
     * DELETE isteği
     * @param string $endpoint API endpoint
     * @return array JSON response
     * @throws Exception
     */
    public function delete($endpoint) {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('DELETE', $url);
    }
    
    /**
     * Pagination ile tüm kayıtları çek
     * @param string $endpoint API endpoint
     * @param array $params Query parametreleri
     * @return array Tüm kayıtlar
     * @throws Exception
     */
    public function getAll($endpoint, $params = []) {
        $allData = [];
        $page = 1;
        $perPage = 25; // Paraşüt API varsayılan sayfa boyutu
        
        do {
            $params['page'] = $page;
            $params['per_page'] = $perPage;
            
            $response = $this->get($endpoint, $params);
            
            // Response yapısına göre veriyi al
            if (isset($response['data'])) {
                $allData = array_merge($allData, $response['data']);
            } elseif (is_array($response)) {
                $allData = array_merge($allData, $response);
            }
            
            // Pagination bilgisi kontrol et
            $hasMore = false;
            if (isset($response['meta'])) {
                $currentPage = $response['meta']['current_page'] ?? $page;
                $totalPages = $response['meta']['total_pages'] ?? 1;
                $hasMore = $currentPage < $totalPages;
            } elseif (isset($response['links']['next'])) {
                $hasMore = !empty($response['links']['next']);
            } elseif (count($response['data'] ?? []) >= $perPage) {
                // Eğer tam sayfa geldiyse bir sonraki sayfa olabilir
                $hasMore = true;
            }
            
            $page++;
            
            // Rate limiting için kısa bekleme
            usleep(100000); // 0.1 saniye
            
        } while ($hasMore);
        
        return $allData;
    }
    
    /**
     * HTTP isteği yap
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $data Request body (POST/PUT için)
     * @return array JSON response
     * @throws Exception
     */
    private function makeRequest($method, $url, $data = []) {
        $token = $this->auth->getValidToken();
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        switch ($method) {
            case 'GET':
                // GET için ek ayar gerekmez
                break;
                
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL hatası: " . $error);
        }
        
        // Rate limiting kontrolü (429 Too Many Requests)
        if ($httpCode === 429) {
            $retryAfter = 60; // Varsayılan 60 saniye bekle
            error_log("Rate limit aşıldı. $retryAfter saniye bekleniyor...");
            sleep($retryAfter);
            // Tekrar dene
            return $this->makeRequest($method, $url, $data);
        }
        
        // Token geçersizse yenile ve tekrar dene
        if ($httpCode === 401) {
            error_log("Token geçersiz, yenileniyor...");
            $this->auth->refreshToken();
            return $this->makeRequest($method, $url, $data);
        }
        
        // Diğer hatalar
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 
                       $errorData['error'] ?? 
                       $errorData['message'] ?? 
                       "HTTP $httpCode hatası";
            throw new Exception("API hatası (HTTP $httpCode): " . $errorMsg);
        }
        
        $jsonResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parse hatası: " . json_last_error_msg());
        }
        
        return $jsonResponse;
    }
}

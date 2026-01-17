<?php
/**
 * Paraşüt API Yapılandırması
 * API Service için
 * 
 * Güvenlik için bu dosyayı .gitignore'a ekleyin
 * Paraşüt API v4 - OAuth2 Password Grant Flow
 * 
 * Hem fonksiyon tabanlı hem class tabanlı kullanımı destekler
 */

// .env dosyasını yükle (eğer varsa)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// API Credentials - .env'den veya define() ile
if (!defined('PARASUT_CLIENT_ID')) {
    define('PARASUT_CLIENT_ID', $_ENV['PARASUT_CLIENT_ID'] ?? '5haTHQjSVTk0QRy2boRyWQzMkNq1K-jh1CDMtqH5wgI');
}
if (!defined('PARASUT_CLIENT_SECRET')) {
    define('PARASUT_CLIENT_SECRET', $_ENV['PARASUT_CLIENT_SECRET'] ?? '1ppbnLAYGl4EBRF7Y-_iU25Dr4J0_NNc4xIalQJascM');
}
if (!defined('PARASUT_COMPANY_ID')) {
    define('PARASUT_COMPANY_ID', $_ENV['PARASUT_COMPANY_ID'] ?? '765847');
}
if (!defined('PARASUT_USERNAME')) {
    define('PARASUT_USERNAME', $_ENV['PARASUT_USERNAME'] ?? 'ulukan@northfly.aero');
}
if (!defined('PARASUT_PASSWORD')) {
    define('PARASUT_PASSWORD', $_ENV['PARASUT_PASSWORD'] ?? 'Ayse1997');
}

// API Endpoints
if (!defined('PARASUT_BASE_URL')) {
    define('PARASUT_BASE_URL', $_ENV['PARASUT_API_BASE_URL'] ?? $_ENV['PARASUT_BASE_URL'] ?? 'https://api.parasut.com');
}
if (!defined('PARASUT_API_VERSION')) {
    define('PARASUT_API_VERSION', $_ENV['PARASUT_API_VERSION'] ?? 'v4');
}
if (!defined('PARASUT_OAUTH_URL')) {
    define('PARASUT_OAUTH_URL', PARASUT_BASE_URL . '/oauth/token');
}
if (!defined('PARASUT_API_URL')) {
    define('PARASUT_API_URL', PARASUT_BASE_URL . '/' . PARASUT_API_VERSION . '/' . PARASUT_COMPANY_ID);
}

// Timeout ayarları
if (!defined('PARASUT_API_TIMEOUT')) {
    define('PARASUT_API_TIMEOUT', 30); // Saniye
}
if (!defined('PARASUT_CONNECT_TIMEOUT')) {
    define('PARASUT_CONNECT_TIMEOUT', 10); // Bağlantı timeout'u
}

/**
 * OAuth2 Token Alma (Veritabanısız - Basit Versiyon)
 * 
 * @return array Token bilgileri
 * @throws Exception
 */
function getParasutToken() {
    $data = [
        'grant_type' => 'password',
        'username' => PARASUT_USERNAME,
        'password' => PARASUT_PASSWORD,
        'client_id' => PARASUT_CLIENT_ID,
        'client_secret' => PARASUT_CLIENT_SECRET,
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob'
    ];
    
    $ch = curl_init(PARASUT_OAUTH_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => PARASUT_API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => PARASUT_CONNECT_TIMEOUT
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("cURL error: $curlError");
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error_description'] ?? $errorData['error'] ?? "HTTP $httpCode";
        throw new Exception("OAuth token error: $errorMsg (HTTP $httpCode)");
    }
    
    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception("Invalid token response - access_token not found");
    }
    
    return $tokenData;
}

/**
 * OAuth2 Token Alma (PDO ile veritabanı cache desteği)
 * 
 * @param PDO $pdo Veritabanı bağlantısı
 * @return string Access token
 * @throws Exception
 */
function getParasutAccessToken($pdo) {
    // Önce cache'den kontrol et
    try {
        $stmt = $pdo->query("SELECT access_token, expires_at FROM parasut_token_cache ORDER BY id DESC LIMIT 1");
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cached && strtotime($cached['expires_at']) > time() + 60) {
            // Token hala geçerli (60 saniye buffer)
            return $cached['access_token'];
        }
    } catch (PDOException $e) {
        // Tablo yoksa devam et
    }
    
    // Yeni token al
    $tokenData = getParasutToken();
    
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn = $tokenData['expires_in'] ?? 7200; // Default 2 saat
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn - 60); // 60 saniye buffer
    
    // Token'ı cache'e kaydet
    try {
        $stmt = $pdo->prepare("
            INSERT INTO parasut_token_cache (access_token, refresh_token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$accessToken, $refreshToken, $expiresAt]);
    } catch (PDOException $e) {
        // Cache kaydedilemezse sessizce devam et
        error_log("Token cache error: " . $e->getMessage());
    }
    
    return $accessToken;
}

/**
 * Endpoint capability bilgilerini döndür
 * Paraşüt API v4'ün gerçek davranışına göre updated_at desteğini kontrol eder
 * 
 * @param string $endpoint API endpoint
 * @return array Capability bilgileri
 */
function getEndpointCapabilities($endpoint) {
    $capabilities = [
        'contacts' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ],
        'tags' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ],
        'products' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ],
        'accounts' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ],
        'payments' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ],
        'sales_invoices' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ],
        'purchase_bills' => [
            'supports_updated_at_filter' => false,
            'supports_updated_at_sort' => false,
            'sync_strategy' => 'periodic_full'
        ]
    ];
    
    return $capabilities[$endpoint] ?? [
        'supports_updated_at_filter' => false,
        'supports_updated_at_sort' => false,
        'sync_strategy' => 'periodic_full'
    ];
}

/**
 * Paraşüt API'ye istek at
 * 
 * @param string $endpoint API endpoint (örn: 'contacts', 'sales_invoices')
 * @param string $accessToken OAuth2 access token
 * @param array $params Query parametreleri (opsiyonel)
 * @param string $method HTTP metodu (GET, POST, PUT, DELETE)
 * @param array $body POST/PUT için body data
 * @param int $retryCount Retry sayacı (internal use)
 * @return array API yanıtı
 * @throws Exception
 */
function parasutApiRequest($endpoint, $accessToken, $params = [], $method = 'GET', $body = null, $retryCount = 0) {
    $url = PARASUT_API_URL . '/' . $endpoint;
    
    if (!empty($params) && $method === 'GET') {
        $url .= '?' . http_build_query($params);
    }
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => PARASUT_API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => PARASUT_CONNECT_TIMEOUT,
        CURLOPT_HEADER => true // Header'ları almak için
    ]);
    
    // HTTP metodunu ayarla
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlError = curl_error($ch);
    
    // Header ve body'yi ayır
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("cURL error: $curlError");
    }
    
    // 429 Rate Limit kontrolü - Exponential Backoff
    $maxRetries = 5;
    $baseDelay = 1; // 1 saniye
    
    if ($httpCode == 429 && $retryCount < $maxRetries) {
        // Retry-After header'ını kontrol et
        $delay = $baseDelay * pow(2, $retryCount); // Exponential: 1s, 2s, 4s, 8s, 16s
        $delay = min($delay, 30); // Max 30 saniye
        
        // Retry-After header'ını parse et
        if (preg_match('/Retry-After:\s*(\d+)/i', $responseHeaders, $matches)) {
            $delay = (int)$matches[1];
        }
        
        // Error mesajından "Try again in X seconds" bilgisini çıkar
        $errorData = json_decode($responseBody, true);
        if ($errorData && isset($errorData['errors'][0]['detail'])) {
            $errorDetail = $errorData['errors'][0]['detail'];
            if (preg_match('/Try again in (\d+) seconds?/i', $errorDetail, $matches)) {
                $delay = (int)$matches[1];
            }
        }
        
        error_log("Rate limit hit for $endpoint. Retrying after {$delay}s (attempt " . ($retryCount + 1) . "/$maxRetries)");
        sleep($delay);
        
        // Recursive retry - body parametresi zaten array olarak geliyor (POST/PUT için)
        return parasutApiRequest($endpoint, $accessToken, $params, $method, $body, $retryCount + 1);
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        $errorData = json_decode($responseBody, true);
        $errorMsg = "HTTP $httpCode";
        
        if ($errorData && isset($errorData['errors'][0])) {
            $errorMsg = $errorData['errors'][0]['detail'] ?? $errorData['errors'][0]['title'] ?? $errorMsg;
        }
        
        throw new Exception("API error: $errorMsg (HTTP $httpCode)");
    }
    
    // Response body'yi JSON olarak parse et
    $data = json_decode($responseBody, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for endpoint $endpoint. Body preview: " . substr($responseBody, 0, 200));
        throw new Exception("JSON decode error: " . json_last_error_msg() . " (HTTP $httpCode)");
    }
    
    return $data;
}

/**
 * Tüm sayfaları çek (pagination desteği) - İYİLEŞTİRİLMİŞ VERSİYON
 * 
 * @param string $endpoint API endpoint
 * @param string $accessToken OAuth2 access token
 * @param array $params Query parametreleri
 * @param callable|null $progressCallback Progress callback fonksiyonu (current, total, page)
 * @return array Tüm kayıtlar ve meta bilgileri
 */
function fetchAllParasutPages($endpoint, $accessToken, $params = [], $progressCallback = null) {
    $allData = [];
    $pageNumber = 1;
    $maxPages = 1000; // Güvenlik limiti
    $totalCount = 0;
    $totalPages = 0;
    
    // Sayfa boyutunu ayarla (Paraşüt API maksimum 25)
    if (!isset($params['page[size]'])) {
        $params['page[size]'] = 25;
    }
    
    // Test modu kontrolü: Eğer page[number] zaten 1 olarak set edilmişse ve test modundaysa, sadece 1 sayfa çek
    $isTestMode = isset($params['page[number]']) && $params['page[number]'] == 1 && isset($params['_test_mode']);
    $maxPagesToFetch = $isTestMode ? 1 : $maxPages;
    
    do {
        $params['page[number]'] = $pageNumber;
        
        try {
            $response = parasutApiRequest($endpoint, $accessToken, $params);
            
            // JSON:API formatından data array'ini çıkar
            $pageData = [];
            if (isset($response['data']) && is_array($response['data'])) {
                $pageData = $response['data'];
                $allData = array_merge($allData, $pageData);
            }
            
            // Meta bilgilerini al
            if (isset($response['meta']['total_count'])) {
                $totalCount = $response['meta']['total_count'];
            }
            if (isset($response['meta']['total_pages'])) {
                $totalPages = $response['meta']['total_pages'];
            }
            
            // Progress callback
            if ($progressCallback !== null && is_callable($progressCallback)) {
                $progressCallback(count($allData), $totalCount, $pageNumber, $totalPages);
            }
            
            // Test modunda sadece 1 sayfa çek
            if ($isTestMode) {
                break;
            }
            
            // Pagination kontrolü - JSON:API standardına göre
            $hasNext = false;
            
            // 1. Öncelik: links.next kontrolü (JSON:API standardı)
            if (isset($response['links']['next']) && !empty($response['links']['next'])) {
                $hasNext = true;
            }
            // 2. Meta current_page ve total_pages kontrolü
            elseif (isset($response['meta']['current_page']) && isset($response['meta']['total_pages'])) {
                $hasNext = $response['meta']['current_page'] < $response['meta']['total_pages'];
            }
            // 3. Fallback: Gelen veri sayısı kontrolü
            elseif (count($pageData) >= $params['page[size]']) {
                // Eğer sayfa boyutu kadar veri geldiyse, bir sonraki sayfa olabilir
                $hasNext = true;
            }
            
            // Debug log
            if (function_exists('error_log')) {
                error_log("[$endpoint] Page $pageNumber: " . count($pageData) . " records fetched. Total so far: " . count($allData) . ". Has next: " . ($hasNext ? 'yes' : 'no'));
            }
            
            $pageNumber++;
            
            // Rate limiting için kısa delay
            if ($hasNext) {
                usleep(200000); // 0.2 saniye
            }
            
        } catch (Exception $e) {
            error_log("Error fetching page $pageNumber for $endpoint: " . $e->getMessage());
            
            // Eğer hiç veri çekemediyse hatayı fırlat
            if (empty($allData)) {
                throw $e;
            }
            
            // Kısmi veri varsa dur ve döndür
            break;
        }
        
        // Güvenlik kontrolü
        if ($pageNumber > $maxPagesToFetch) {
            error_log("Maximum page limit ($maxPagesToFetch) reached for $endpoint");
            break;
        }
        
    } while ($hasNext && !$isTestMode);
    
    return [
        'data' => $allData,
        'meta' => [
            'total_count' => $totalCount > 0 ? $totalCount : count($allData),
            'total_pages' => $totalPages > 0 ? $totalPages : $pageNumber - 1,
            'pages_fetched' => $pageNumber - 1,
            'records_fetched' => count($allData)
        ]
    ];
}

/**
 * LEGACY: Eski format için wrapper - geriye dönük uyumluluk
 * Sadece data array'ini döndürür
 */
function fetchAllParasutPagesLegacy($endpoint, $accessToken, $params = []) {
    $result = fetchAllParasutPages($endpoint, $accessToken, $params);
    return $result['data'];
}

/**
 * Incremental sync için sadece güncellenmiş kayıtları çek
 * NOT: Paraşüt API v4'te çoğu endpoint updated_at filter/sort desteklemiyor
 * Bu fonksiyon sadece destekleyen endpoint'ler için kullanılmalı
 * 
 * @param string $endpoint API endpoint
 * @param string $accessToken OAuth2 access token
 * @param string $lastSyncDate Son sync tarihi (Y-m-d H:i:s formatında)
 * @param array $params Ek query parametreleri
 * @param callable|null $progressCallback Progress callback fonksiyonu
 * @return array Güncellenmiş kayıtlar ve meta bilgileri
 * @throws Exception Eğer endpoint updated_at filter desteklemiyorsa
 */
function fetchParasutPagesIncremental($endpoint, $accessToken, $lastSyncDate, $params = [], $progressCallback = null) {
    // Endpoint capability kontrolü
    $capabilities = getEndpointCapabilities($endpoint);
    
    if (!$capabilities['supports_updated_at_filter']) {
        // updated_at desteklenmiyorsa, exception fırlat
        throw new Exception("Endpoint '$endpoint' does not support updated_at filter. Use full sync instead.");
    }
    
    // Tarihi ISO 8601 formatına çevir (Paraşüt API formatı)
    $dateTime = new DateTime($lastSyncDate);
    $isoDate = $dateTime->format('Y-m-d\TH:i:s\Z');
    
    // updated_at filtresi ekle
    $params['filter[updated_at]'] = $isoDate;
    
    // Sort by updated_at (sadece destekleniyorsa)
    if ($capabilities['supports_updated_at_sort']) {
        $params['sort'] = '-updated_at';
    }
    
    // Normal pagination fonksiyonunu kullan
    return fetchAllParasutPages($endpoint, $accessToken, $params, $progressCallback);
}

/**
 * JSON:API formatındaki veriyi düzleştir
 * 
 * @param array $jsonApiData JSON:API formatındaki data
 * @return array Düzleştirilmiş veri
 */
function flattenJsonApiData($jsonApiData) {
    $flattened = [];
    
    foreach ($jsonApiData as $item) {
        $row = [];
        
        // ID
        if (isset($item['id'])) {
            $row['id'] = $item['id'];
        }
        
        // Attributes
        if (isset($item['attributes']) && is_array($item['attributes'])) {
            foreach ($item['attributes'] as $key => $value) {
                // Nested array/object'leri JSON string'e çevir
                if (is_array($value) || is_object($value)) {
                    $row[$key] = json_encode($value);
                } else {
                    $row[$key] = $value;
                }
            }
        }
        
        // Relationships (sadece ID'leri al)
        if (isset($item['relationships']) && is_array($item['relationships'])) {
            foreach ($item['relationships'] as $key => $rel) {
                if (isset($rel['data']['id'])) {
                    $row[$key . '_id'] = $rel['data']['id'];
                } elseif (isset($rel['data']) && is_array($rel['data'])) {
                    // Multiple relationships
                    $ids = [];
                    foreach ($rel['data'] as $relItem) {
                        if (isset($relItem['id'])) {
                            $ids[] = $relItem['id'];
                        }
                    }
                    $row[$key . '_ids'] = json_encode($ids);
                }
            }
        }
        
        $flattened[] = $row;
    }
    
    return $flattened;
}

/**
 * Refresh token ile yeni access token al
 * 
 * @param string $refreshToken Refresh token
 * @return array Token bilgileri
 * @throws Exception
 */
function refreshParasutToken($refreshToken) {
    $data = [
        'grant_type' => 'refresh_token',
        'client_id' => PARASUT_CLIENT_ID,
        'client_secret' => PARASUT_CLIENT_SECRET,
        'refresh_token' => $refreshToken
    ];
    
    $ch = curl_init(PARASUT_OAUTH_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => PARASUT_API_TIMEOUT
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Refresh token failed: HTTP $httpCode");
    }
    
    return json_decode($response, true);
}

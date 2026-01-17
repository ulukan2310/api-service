<?php
/**
 * Paraşüt API v2 Configuration
 * API endpoint'leri ve ayarları
 */

class ParasutAPI {
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $username;
    private $password;
    private $companyId;

    /**
     * Constructor - Environment variables'dan ayarları yükle
     */
    public function __construct() {
        // .env dosyasını yükle
        $this->loadEnv();
        
        $this->baseUrl = $_ENV['PARASUT_API_BASE_URL'] ?? 'https://api.parasut.com/v2';
        $this->clientId = $_ENV['PARASUT_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['PARASUT_CLIENT_SECRET'] ?? '';
        $this->username = $_ENV['PARASUT_USERNAME'] ?? '';
        $this->password = $_ENV['PARASUT_PASSWORD'] ?? '';
        $this->companyId = $_ENV['PARASUT_COMPANY_ID'] ?? '';
    }

    /**
     * .env dosyasını yükle
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('.env dosyası bulunamadı! Lütfen .env.example dosyasını .env olarak kopyalayın.');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Yorum satırlarını atla
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // KEY=VALUE formatını parse et
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Tırnak işaretlerini kaldır
                $value = trim($value, '"\'');
                
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    /**
     * Base URL'i al
     * @return string
     */
    public function getBaseUrl() {
        return $this->baseUrl;
    }

    /**
     * Client ID'yi al
     * @return string
     */
    public function getClientId() {
        return $this->clientId;
    }

    /**
     * Client Secret'i al
     * @return string
     */
    public function getClientSecret() {
        return $this->clientSecret;
    }

    /**
     * Username'i al
     * @return string
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * Password'ü al
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * Company ID'yi al
     * @return string
     */
    public function getCompanyId() {
        return $this->companyId;
    }

    /**
     * API endpoint'lerini al
     * @return array
     */
    public function getEndpoints() {
        return [
            'auth' => '/oauth/token',
            'contacts' => '/' . $this->companyId . '/contacts',
            'products' => '/' . $this->companyId . '/products',
            'sales_invoices' => '/' . $this->companyId . '/sales_invoices',
            'purchase_bills' => '/' . $this->companyId . '/purchase_bills',
            'payments' => '/' . $this->companyId . '/payments',
            'accounts' => '/' . $this->companyId . '/accounts',
            'tags' => '/' . $this->companyId . '/tags',
        ];
    }

    /**
     * Belirli bir endpoint'i al
     * @param string $endpointName
     * @return string
     */
    public function getEndpoint($endpointName) {
        $endpoints = $this->getEndpoints();
        return $endpoints[$endpointName] ?? '';
    }
}

<?php
/**
 * Database Connection Class
 * MySQL veritabanı bağlantısı için sınıf
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $conn;

    /**
     * Constructor - Environment variables'dan ayarları yükle
     */
    public function __construct() {
        // .env dosyasını yükle
        $this->loadEnv();
        
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'parasut_db';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
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
     * Veritabanı bağlantısını al
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            throw new Exception("Veritabanına bağlanılamadı: " . $e->getMessage());
        }

        return $this->conn;
    }

    /**
     * Bağlantıyı kapat
     */
    public function closeConnection() {
        $this->conn = null;
    }
}

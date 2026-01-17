<?php
/**
 * Veritabanı Bağlantı Yapılandırması
 * Paraşüt API Verileri için Bağımsız Veritabanı
 * 
 * Hem class hem fonksiyon tabanlı kullanımı destekler
 * .env dosyasından veya define() ile yapılandırma yapılabilir
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

// Veritabanı bağlantı bilgileri - .env'den veya define() ile
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'admin_');
}
if (!defined('DB_USER')) {
    define('DB_USER', $_ENV['DB_USER'] ?? 'wingcert_58ggflbokb8');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '3A8oef&76');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
}

/**
 * PDO Veritabanı Bağlantısı (Fonksiyon Tabanlı)
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            
            // Detaylı hata mesajı
            $errorMsg = "❌ Veritabanı bağlantı hatası!\n\n";
            $errorMsg .= "Hata: " . $e->getMessage() . "\n";
            $errorMsg .= "Kod: " . $e->getCode() . "\n\n";
            $errorMsg .= "Bağlantı Bilgileri:\n";
            $errorMsg .= "- Host: " . DB_HOST . ":" . DB_PORT . "\n";
            $errorMsg .= "- Database: " . DB_NAME . "\n";
            $errorMsg .= "- User: " . DB_USER . "\n\n";
            
            if ($e->getCode() == 1045) {
                $errorMsg .= "💡 Kullanıcı adı veya şifre yanlış olabilir.\n";
            } elseif ($e->getCode() == 1049) {
                $errorMsg .= "💡 Veritabanı adı yanlış veya veritabanı mevcut değil.\n";
            } elseif ($e->getCode() == 2002) {
                $errorMsg .= "💡 Veritabanı sunucusuna bağlanılamıyor.\n";
            }
            
            die($errorMsg);
        }
    }
    
    return $pdo;
}

/**
 * Database Class (Class Tabanlı - Geriye Dönük Uyumluluk)
 * Mevcut api-service yapısı ile uyumlu
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
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
    }

    /**
     * Veritabanı bağlantısını al
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . DB_PORT . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
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

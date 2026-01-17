<?php
/**
 * Basit Paraşüt Tablo Oluşturucu
 * Tek dosya - direkt çalıştırılabilir
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı bilgileri - .env'den
define('DB_HOST', 'localhost');
define('DB_NAME', 'admin_');
define('DB_USER', 'wingcert_58ggflbokb8');
define('DB_PASS', '3A8oef&76');
define('DB_CHARSET', 'utf8mb4');

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Paraşüt DB Kurulum</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; }
        .success { color: green; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Paraşüt Veritabanı Kurulum</h1>
        
<?php

try {
    echo "<div class='info'>Veritabanına bağlanılıyor...</div>";
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<div class='success'>✅ Veritabanına bağlanıldı: " . DB_NAME . "</div>";
    
    // Tablolar
    $tables = [
        "parasut_token_cache" => "CREATE TABLE IF NOT EXISTS `parasut_token_cache` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `access_token` TEXT NOT NULL,
            `refresh_token` TEXT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_contacts" => "CREATE TABLE IF NOT EXISTS `parasut_contacts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `name` VARCHAR(255),
            `email` VARCHAR(255),
            `tax_number` VARCHAR(50),
            `phone` VARCHAR(50),
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_products" => "CREATE TABLE IF NOT EXISTS `parasut_products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `name` VARCHAR(255),
            `code` VARCHAR(100),
            `list_price` DECIMAL(15,2),
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_accounts" => "CREATE TABLE IF NOT EXISTS `parasut_accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `name` VARCHAR(255),
            `account_type` VARCHAR(50),
            `currency` VARCHAR(10),
            `balance` DECIMAL(15,2) DEFAULT 0,
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_sales_invoices" => "CREATE TABLE IF NOT EXISTS `parasut_sales_invoices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `invoice_no` VARCHAR(100),
            `contact_id` VARCHAR(50),
            `issue_date` DATE,
            `net_total` DECIMAL(15,2),
            `gross_total` DECIMAL(15,2),
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_purchase_bills" => "CREATE TABLE IF NOT EXISTS `parasut_purchase_bills` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `bill_no` VARCHAR(100),
            `supplier_id` VARCHAR(50),
            `issue_date` DATE,
            `net_total` DECIMAL(15,2),
            `gross_total` DECIMAL(15,2),
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_payments" => "CREATE TABLE IF NOT EXISTS `parasut_payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `date` DATE,
            `amount` DECIMAL(15,2),
            `account_id` VARCHAR(50),
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_tags" => "CREATE TABLE IF NOT EXISTS `parasut_tags` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `parasut_id` VARCHAR(50) UNIQUE,
            `name` VARCHAR(255),
            `raw_data` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_tag_relations" => "CREATE TABLE IF NOT EXISTS `parasut_tag_relations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tag_id` VARCHAR(50),
            `item_id` VARCHAR(50),
            `item_type` VARCHAR(50),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "parasut_sync_log" => "CREATE TABLE IF NOT EXISTS `parasut_sync_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `endpoint` VARCHAR(50),
            `status` VARCHAR(20),
            `records_fetched` INT DEFAULT 0,
            `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `completed_at` TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    $created = 0;
    $exists = 0;
    
    foreach ($tables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            
            // Tablo var mı kontrol et
            $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
                $count = $stmt->fetch()['count'];
                echo "<div class='success'>✅ $tableName ($count kayıt)</div>";
                $created++;
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<div class='info'>⚠️ $tableName - Zaten mevcut</div>";
                $exists++;
            } else {
                echo "<div class='error'>❌ $tableName - Hata: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    echo "<hr>";
    echo "<div class='success'><h2>🎉 Kurulum Tamamlandı!</h2>";
    echo "Oluşturulan tablolar: $created<br>";
    echo "Mevcut tablolar: $exists<br>";
    echo "Toplam: " . ($created + $exists) . " tablo</div>";
    
    echo "<div class='info'><strong>Sonraki adım:</strong> test_api.php çalıştırın</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'><h3>❌ Veritabanı Hatası</h3>";
    echo "Hata: " . $e->getMessage() . "<br>";
    echo "Kod: " . $e->getCode() . "</div>";
}

?>
    </div>
</body>
</html>

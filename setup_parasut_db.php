<?php
/**
 * Paraşüt Veritabanı Kurulum Script'i
 * 
 * Bu script, Paraşüt API'den çekilecek veriler için gerekli tabloları oluşturur.
 * FPL veritabanına dokunmaz - bağımsız bir sistemdir.
 * 
 * Kullanım:
 * - Web'den: https://yourdomain.com/setup_parasut_db.php
 * - CLI'den: php setup_parasut_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Config dosyasını yükle - farklı yolları dene
$configPaths = [
    __DIR__ . '/config/database.php',
    dirname(__DIR__) . '/config/database.php',
    '/var/www/vhosts/wingcert.com/ua.wingcert.com/config/database.php',
    '/home/fplhymwn/public_html/config/database.php',
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

// Eğer config dosyası bulunamadıysa, kullanıcıdan bilgi al veya varsayılan kullan
if (!$configLoaded) {
    // Web arayüzünden bilgi al
    if (php_sapi_name() !== 'cli' && ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_host']))) {
        // POST'tan gelen bilgileri kullan
        define('DB_HOST', $_POST['db_host']);
        define('DB_NAME', $_POST['db_name']);
        define('DB_USER', $_POST['db_user']);
        define('DB_PASS', $_POST['db_pass']);
        define('DB_CHARSET', $_POST['db_charset'] ?? 'utf8mb4');
        $configLoaded = true;
    } elseif (php_sapi_name() !== 'cli') {
        // Web arayüzünde form göster
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Paraşüt Veritabanı Kurulum - Config</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    padding: 40px;
                }
                h1 { color: #333; margin-bottom: 10px; }
                p { color: #666; margin-bottom: 30px; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 5px; color: #333; font-weight: 600; }
                input { width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px; }
                input:focus { outline: none; border-color: #667eea; }
                .btn {
                    width: 100%;
                    padding: 12px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.3s;
                }
                .btn:hover { background: #5568d3; }
                .error { background: #fee; border: 2px solid #f00; padding: 15px; border-radius: 6px; margin-bottom: 20px; color: #c00; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🗄️ Veritabanı Bağlantı Bilgileri</h1>
                <p>Config dosyası bulunamadı. Lütfen veritabanı bilgilerini girin:</p>
                
                <div class="error">
                    <strong>⚠️ Config dosyası bulunamadı!</strong><br>
                    Aranan yollar:<br>
                    <?php foreach ($configPaths as $path): ?>
                        • <?php echo htmlspecialchars($path); ?><br>
                    <?php endforeach; ?>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Veritabanı Host:</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Veritabanı Adı:</label>
                        <input type="text" name="db_name" value="form_management" required>
                    </div>
                    <div class="form-group">
                        <label>Kullanıcı Adı:</label>
                        <input type="text" name="db_user" required>
                    </div>
                    <div class="form-group">
                        <label>Şifre:</label>
                        <input type="password" name="db_pass" required>
                    </div>
                    <div class="form-group">
                        <label>Charset:</label>
                        <input type="text" name="db_charset" value="utf8mb4">
                    </div>
                    <button type="submit" class="btn">Devam Et</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        // CLI için hata ver
        die("❌ Config dosyası bulunamadı!\nLütfen config/database.php dosyasını oluşturun veya script'i web arayüzünden çalıştırın.\n");
    }
}

// getDBConnection fonksiyonu yoksa oluştur
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        static $pdo = null;
        
        if ($pdo === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
                die("Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.");
            }
        }
        
        return $pdo;
    }
}

// Paraşüt API config'i yükle (opsiyonel - sadece test için)
$parasutConfigPaths = [
    __DIR__ . '/config/parasut_api.php',
    __DIR__ . '/parasut/config/parasut_api.php',
    '/home/fplhymwn/public_html/config/parasut_api.php',
    '/home/fplhymwn/public_html/parasut/config/parasut_api.php',
];
$parasutConfigLoaded = false;
foreach ($parasutConfigPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $parasutConfigLoaded = true;
        break;
    }
}

// CLI mi web mi?
$isCLI = php_sapi_name() === 'cli';

// Web arayüzü
if (!$isCLI) {
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paraşüt Veritabanı Kurulum</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .status-success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        
        .status-info {
            background: #d1ecf1;
            border: 2px solid #0c5460;
            color: #0c5460;
        }
        
        .log-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #667eea;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .log-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .log-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .log-item.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .home-icon {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 24px;
            text-decoration: none;
            color: white;
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .home-icon:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <a href="welcome.php" class="home-icon" title="Ana Sayfa">🏠</a>
    
    <div class="container">
        <div class="header">
            <h1>🗄️ Paraşüt Veritabanı Kurulum</h1>
            <p>Paraşüt API verileri için tablolar oluşturuluyor</p>
        </div>
        
        <div class="content">
<?php
}

// Log fonksiyonu
function logMessage($message, $level = 'info') {
    global $isCLI;
    
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[$timestamp] $message";
    
    if ($isCLI) {
        echo $logMsg . "\n";
    } else {
        $class = $level === 'error' ? 'error' : ($level === 'success' ? 'success' : ($level === 'warning' ? 'warning' : 'info'));
        echo "<div class='log-item $class'>$message</div>";
        flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
    }
}

// Endpoint yapılandırmaları (FPL'den bağımsız, sadece Paraşüt verileri)
$endpoints = [
    'contacts' => [
        'name' => 'Müşteriler/Tedarikçiler',
        'table' => 'parasut_contacts',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'name' => 'VARCHAR(255)',
            'email' => 'VARCHAR(255)',
            'tax_number' => 'VARCHAR(50)',
            'tax_office' => 'VARCHAR(255)',
            'city' => 'VARCHAR(100)',
            'district' => 'VARCHAR(100)',
            'address' => 'TEXT',
            'phone' => 'VARCHAR(50)',
            'account_type' => 'VARCHAR(50)',
            'balance' => 'DECIMAL(15,2) DEFAULT 0',
            'balance_trl' => 'DECIMAL(15,2) DEFAULT 0',
            'untracked_balance' => 'DECIMAL(15,2) DEFAULT 0',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_email' => 'email',
            'idx_tax_number' => 'tax_number',
            'idx_name' => 'name',
            'idx_synced' => 'synced_at'
        ]
    ],
    'sales_invoices' => [
        'name' => 'Satış Faturaları',
        'table' => 'parasut_sales_invoices',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'invoice_no' => 'VARCHAR(100)',
            'invoice_series' => 'VARCHAR(50)',
            'contact_id' => 'VARCHAR(50)',
            'contact_name' => 'VARCHAR(255)',
            'issue_date' => 'DATE',
            'due_date' => 'DATE',
            'description' => 'TEXT',
            'net_total' => 'DECIMAL(15,2) DEFAULT 0',
            'gross_total' => 'DECIMAL(15,2) DEFAULT 0',
            'remaining' => 'DECIMAL(15,2) DEFAULT 0',
            'remaining_in_trl' => 'DECIMAL(15,2) DEFAULT 0',
            'currency' => 'VARCHAR(10) DEFAULT "TRL"',
            'item_type' => 'VARCHAR(50)',
            'payment_status' => 'VARCHAR(50)',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_invoice_no' => 'invoice_no',
            'idx_contact' => 'contact_id',
            'idx_issue_date' => 'issue_date',
            'idx_synced' => 'synced_at'
        ]
    ],
    'products' => [
        'name' => 'Ürün/Hizmetler',
        'table' => 'parasut_products',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'name' => 'VARCHAR(255)',
            'code' => 'VARCHAR(100)',
            'vat_rate' => 'DECIMAL(5,2) DEFAULT 0',
            'sales_excise_duty' => 'DECIMAL(5,2) DEFAULT 0',
            'sales_excise_duty_type' => 'VARCHAR(50)',
            'unit' => 'VARCHAR(50)',
            'communications_tax_rate' => 'DECIMAL(5,2) DEFAULT 0',
            'list_price' => 'DECIMAL(15,2) DEFAULT 0',
            'currency' => 'VARCHAR(10) DEFAULT "TRL"',
            'buying_price' => 'DECIMAL(15,2) DEFAULT 0',
            'inventory_tracking' => 'BOOLEAN DEFAULT FALSE',
            'initial_stock_count' => 'DECIMAL(10,2) DEFAULT 0',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_code' => 'code',
            'idx_name' => 'name',
            'idx_synced' => 'synced_at'
        ]
    ],
    'payments' => [
        'name' => 'Ödemeler',
        'table' => 'parasut_payments',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'date' => 'DATE',
            'amount' => 'DECIMAL(15,2) DEFAULT 0',
            'account_id' => 'VARCHAR(50)',
            'payable_id' => 'VARCHAR(50)',
            'payable_type' => 'VARCHAR(50)',
            'description' => 'TEXT',
            'currency' => 'VARCHAR(10) DEFAULT "TRL"',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_date' => 'date',
            'idx_account' => 'account_id',
            'idx_payable' => 'payable_id',
            'idx_synced' => 'synced_at'
        ]
    ],
    'accounts' => [
        'name' => 'Hesaplar',
        'table' => 'parasut_accounts',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'name' => 'VARCHAR(255)',
            'account_type' => 'VARCHAR(50)',
            'currency' => 'VARCHAR(10) DEFAULT "TRL"',
            'bank_name' => 'VARCHAR(255)',
            'bank_branch' => 'VARCHAR(255)',
            'account_no' => 'VARCHAR(100)',
            'iban' => 'VARCHAR(50)',
            'balance' => 'DECIMAL(15,2) DEFAULT 0',
            'archived' => 'BOOLEAN DEFAULT FALSE',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_name' => 'name',
            'idx_type' => 'account_type',
            'idx_synced' => 'synced_at'
        ]
    ],
    'purchase_bills' => [
        'name' => 'Alış Faturaları',
        'table' => 'parasut_purchase_bills',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'bill_no' => 'VARCHAR(100)',
            'supplier_id' => 'VARCHAR(50)',
            'supplier_name' => 'VARCHAR(255)',
            'issue_date' => 'DATE',
            'due_date' => 'DATE',
            'description' => 'TEXT',
            'net_total' => 'DECIMAL(15,2) DEFAULT 0',
            'gross_total' => 'DECIMAL(15,2) DEFAULT 0',
            'remaining' => 'DECIMAL(15,2) DEFAULT 0',
            'remaining_in_trl' => 'DECIMAL(15,2) DEFAULT 0',
            'currency' => 'VARCHAR(10) DEFAULT "TRL"',
            'item_type' => 'VARCHAR(50)',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_bill_no' => 'bill_no',
            'idx_supplier' => 'supplier_id',
            'idx_issue_date' => 'issue_date',
            'idx_synced' => 'synced_at'
        ]
    ],
    'tags' => [
        'name' => 'Etiketler',
        'table' => 'parasut_tags',
        'schema' => [
            'parasut_id' => 'VARCHAR(50) PRIMARY KEY',
            'name' => 'VARCHAR(255)',
            'color' => 'VARCHAR(50)',
            'taggable_type' => 'VARCHAR(100)',
            'usage_count' => 'INT DEFAULT 0',
            'raw_data' => 'LONGTEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'synced_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'idx_name' => 'name',
            'idx_type' => 'taggable_type',
            'idx_synced' => 'synced_at'
        ]
    ]
];

/**
 * Tabloları oluştur
 */
function createParasutTables($pdo, $endpoints) {
    logMessage("📋 Veritabanı tabloları oluşturuluyor...");
    
    foreach ($endpoints as $endpoint => $config) {
        $tableName = $config['table'];
        $schema = $config['schema'];
        $indexes = $config['indexes'] ?? [];
        
        // Tablo var mı kontrol et
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            logMessage("⚠️ Tablo zaten mevcut: $tableName - Atlandı", 'warning');
            continue;
        }
        
        // Tablo oluştur
        logMessage("🆕 Tablo oluşturuluyor: $tableName");
        
        // CREATE TABLE SQL'i oluştur
        $createSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (\n";
        
        foreach ($schema as $col => $type) {
            $createSQL .= "  `$col` $type,\n";
        }
        
        // İndeksler
        foreach ($indexes as $idxName => $idxCol) {
            if (strpos($idxCol, ',') !== false) {
                // Composite index
                $cols = array_map('trim', explode(',', $idxCol));
                $createSQL .= "  INDEX `$idxName` (`" . implode("`, `", $cols) . "`),\n";
            } else {
                $createSQL .= "  INDEX `$idxName` (`$idxCol`),\n";
            }
        }
        
        $createSQL = rtrim($createSQL, ",\n") . "\n";
        $createSQL .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        try {
            $pdo->exec($createSQL);
            logMessage("✅ Tablo oluşturuldu: $tableName", 'success');
        } catch (PDOException $e) {
            logMessage("❌ Tablo oluşturma hatası ($tableName): " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    // Sync log tablosu
    $syncLogSQL = "CREATE TABLE IF NOT EXISTS `parasut_sync_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `endpoint` VARCHAR(50) NOT NULL,
        `sync_type` ENUM('full', 'incremental') DEFAULT 'full',
        `status` ENUM('running', 'success', 'error') DEFAULT 'running',
        `records_fetched` INT DEFAULT 0,
        `records_inserted` INT DEFAULT 0,
        `records_updated` INT DEFAULT 0,
        `records_errors` INT DEFAULT 0,
        `duration_seconds` DECIMAL(10,2) DEFAULT 0,
        `error_message` TEXT NULL,
        `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `completed_at` TIMESTAMP NULL,
        INDEX `idx_endpoint` (`endpoint`),
        INDEX `idx_started` (`started_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    try {
        $pdo->exec($syncLogSQL);
        logMessage("✅ Sync log tablosu oluşturuldu", 'success');
    } catch (PDOException $e) {
        logMessage("⚠️ Sync log tablosu hatası: " . $e->getMessage(), 'warning');
    }
    
    // Token cache tablosu
    $tokenCacheSQL = "CREATE TABLE IF NOT EXISTS `parasut_token_cache` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `access_token` TEXT NOT NULL,
        `refresh_token` TEXT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    try {
        $pdo->exec($tokenCacheSQL);
        logMessage("✅ Token cache tablosu oluşturuldu", 'success');
    } catch (PDOException $e) {
        logMessage("⚠️ Token cache tablosu hatası: " . $e->getMessage(), 'warning');
    }
    
    // Tag-Item ilişkileri için junction table
    $tagRelationsSQL = "CREATE TABLE IF NOT EXISTS `parasut_tag_relations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tag_id` VARCHAR(50) NOT NULL,
        `tag_name` VARCHAR(255) NULL,
        `item_id` VARCHAR(50) NOT NULL,
        `item_type` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_tag_id` (`tag_id`),
        INDEX `idx_item` (`item_id`, `item_type`),
        INDEX `idx_item_type` (`item_type`),
        UNIQUE KEY `unique_tag_item` (`tag_id`, `item_id`, `item_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    try {
        $pdo->exec($tagRelationsSQL);
        logMessage("✅ Tag relations tablosu oluşturuldu", 'success');
    } catch (PDOException $e) {
        logMessage("⚠️ Tag relations tablosu hatası: " . $e->getMessage(), 'warning');
    }
}

// Ana işlem
try {
    logMessage("🔌 Veritabanına bağlanılıyor...");
    logMessage("   Host: " . DB_HOST);
    logMessage("   Database: " . DB_NAME);
    logMessage("   User: " . DB_USER);
    
    $pdo = getDBConnection();
    logMessage("✅ Veritabanına bağlanıldı: " . DB_NAME, 'success');
    
    logMessage("📊 Veritabanı bilgileri:");
    $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as database_name");
    $info = $stmt->fetch();
    logMessage("   - Veritabanı: " . $info['database_name']);
    logMessage("   - MySQL Versiyonu: " . $info['version']);
    
    // Tabloları oluştur
    createParasutTables($pdo, $endpoints);
    
    // Oluşturulan tabloları listele
    logMessage("");
    logMessage("📋 Oluşturulan tablolar:");
    $stmt = $pdo->query("SHOW TABLES LIKE 'parasut_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $stmt->fetch()['count'];
        logMessage("   ✓ $table ($count kayıt)");
    }
    
    logMessage("");
    logMessage("🎉 Kurulum tamamlandı!", 'success');
    logMessage("✅ Tüm Paraşüt tabloları hazır. Artık sync script'ini çalıştırabilirsiniz.");
    
} catch (PDOException $e) {
    logMessage("❌ Veritabanı hatası: " . $e->getMessage(), 'error');
    logMessage("   Hata Kodu: " . $e->getCode(), 'error');
    logMessage("   Host: " . DB_HOST, 'error');
    logMessage("   Database: " . DB_NAME, 'error');
    logMessage("   User: " . DB_USER, 'error');
    
    // Ortak hatalar için öneriler
    $errorCode = $e->getCode();
    if ($errorCode == 1045) {
        logMessage("💡 Çözüm: Kullanıcı adı veya şifre yanlış olabilir.", 'warning');
    } elseif ($errorCode == 1049) {
        logMessage("💡 Çözüm: Veritabanı adı yanlış veya veritabanı mevcut değil.", 'warning');
    } elseif ($errorCode == 2002) {
        logMessage("💡 Çözüm: Veritabanı sunucusuna bağlanılamıyor. Host adresini kontrol edin.", 'warning');
    }
} catch (Exception $e) {
    logMessage("❌ Genel hata: " . $e->getMessage(), 'error');
}

if (!$isCLI) {
?>
            <a href="welcome.php" class="btn">← Ana Sayfaya Dön</a>
        </div>
    </div>
</body>
</html>
<?php
}

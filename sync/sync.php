<?php
/**
 * Ana Senkronizasyon Scripti
 * Tüm tabloları Paraşüt API v2'den çekip veritabanına kaydeder
 * 
 * Kullanım: php sync/sync.php [table1] [table2] ...
 * Örnek: php sync/sync.php contacts products
 * Hiç argüman verilmezse tüm tablolar senkronize edilir
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/SalesInvoice.php';
require_once __DIR__ . '/../models/PurchaseBill.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/Tag.php';
require_once __DIR__ . '/../models/TagRelation.php';

class SyncManager {
    private $conn;
    private $availableTables = [
        'contacts' => 'Contact',
        'products' => 'Product',
        'accounts' => 'Account',
        'sales_invoices' => 'SalesInvoice',
        'purchase_bills' => 'PurchaseBill',
        'payments' => 'Payment',
        'tags' => 'Tag'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }
    
    /**
     * Belirli tabloları senkronize et
     * @param array $tables Senkronize edilecek tablo isimleri
     * @return array Genel istatistikler
     */
    public function sync($tables = []) {
        // Eğer tablo belirtilmemişse tümünü senkronize et
        if (empty($tables)) {
            $tables = array_keys($this->availableTables);
        }
        
        // Geçersiz tablo isimlerini filtrele
        $tables = array_intersect($tables, array_keys($this->availableTables));
        
        if (empty($tables)) {
            throw new Exception("Geçerli tablo ismi bulunamadı. Mevcut tablolar: " . implode(', ', array_keys($this->availableTables)));
        }
        
        $overallStats = [
            'total_tables' => count($tables),
            'successful' => 0,
            'failed' => 0,
            'tables' => []
        ];
        
        echo "========================================\n";
        echo "Paraşüt API v2 Senkronizasyon Başlatılıyor\n";
        echo "========================================\n";
        echo "Senkronize edilecek tablolar: " . implode(', ', $tables) . "\n";
        echo "Başlangıç zamanı: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $tableName) {
            $startTime = microtime(true);
            $logId = $this->startSyncLog($tableName);
            
            echo "\n--- $tableName senkronizasyonu başlıyor ---\n";
            
            try {
                $modelClass = $this->availableTables[$tableName];
                $model = new $modelClass();
                
                // Sync işlemini çalıştır
                $stats = $model->syncFromAPI();
                
                $duration = microtime(true) - $startTime;
                $status = 'success';
                
                // Hata varsa partial olarak işaretle
                if (!empty($stats['errors'])) {
                    $status = 'partial';
                }
                
                $this->completeSyncLog($logId, $status, $stats, $duration);
                
                $overallStats['successful']++;
                $overallStats['tables'][$tableName] = [
                    'status' => $status,
                    'stats' => $stats,
                    'duration' => round($duration, 2)
                ];
                
                echo "✓ $tableName senkronizasyonu tamamlandı\n";
                echo "  Çekilen: {$stats['fetched']}, Kaydedilen: {$stats['saved']}, Güncellenen: {$stats['updated']}, Atlanan: {$stats['skipped']}\n";
                echo "  Süre: " . round($duration, 2) . " saniye\n";
                
                if (!empty($stats['errors'])) {
                    echo "  ⚠ " . count($stats['errors']) . " hata oluştu\n";
                }
                
            } catch (Exception $e) {
                $duration = microtime(true) - $startTime;
                $this->completeSyncLog($logId, 'failed', [
                    'fetched' => 0,
                    'saved' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [['error' => $e->getMessage()]]
                ], $duration, $e->getMessage());
                
                $overallStats['failed']++;
                $overallStats['tables'][$tableName] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'duration' => round($duration, 2)
                ];
                
                echo "✗ $tableName senkronizasyonu başarısız: " . $e->getMessage() . "\n";
                error_log("Sync hatası ($tableName): " . $e->getMessage());
            }
        }
        
        echo "\n========================================\n";
        echo "Senkronizasyon Özeti\n";
        echo "========================================\n";
        echo "Toplam tablo: {$overallStats['total_tables']}\n";
        echo "Başarılı: {$overallStats['successful']}\n";
        echo "Başarısız: {$overallStats['failed']}\n";
        echo "Bitiş zamanı: " . date('Y-m-d H:i:s') . "\n";
        echo "========================================\n";
        
        return $overallStats;
    }
    
    /**
     * Sync log kaydı başlat
     * @param string $tableName
     * @return int Log ID
     */
    private function startSyncLog($tableName) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_sync_log 
                (table_name, sync_type, status, started_at)
                VALUES (?, 'full', 'success', NOW())
            ");
            $stmt->execute([$tableName]);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Sync log başlatma hatası: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Sync log kaydını tamamla
     * @param int $logId
     * @param string $status
     * @param array $stats
     * @param float $duration
     * @param string|null $errorMessage
     */
    private function completeSyncLog($logId, $status, $stats, $duration, $errorMessage = null) {
        if ($logId == 0) {
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("
                UPDATE parasut_sync_log SET
                    status = ?,
                    records_fetched = ?,
                    records_saved = ?,
                    records_updated = ?,
                    records_skipped = ?,
                    error_message = ?,
                    completed_at = NOW(),
                    duration_seconds = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $status,
                $stats['fetched'] ?? 0,
                $stats['saved'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['skipped'] ?? 0,
                $errorMessage ?? (empty($stats['errors']) ? null : json_encode($stats['errors'])),
                round($duration, 2),
                $logId
            ]);
        } catch (PDOException $e) {
            error_log("Sync log tamamlama hatası: " . $e->getMessage());
        }
    }
}

// Script çalıştırıldığında
if (php_sapi_name() === 'cli') {
    try {
        $syncManager = new SyncManager();
        
        // Komut satırı argümanlarını al (ilk argüman script adı olduğu için atla)
        $tables = array_slice($argv, 1);
        
        $syncManager->sync($tables);
        
    } catch (Exception $e) {
        echo "\n✗ HATA: " . $e->getMessage() . "\n";
        echo "Dosya: " . $e->getFile() . "\n";
        echo "Satır: " . $e->getLine() . "\n";
        exit(1);
    }
}

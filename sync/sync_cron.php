<?php
/**
 * Cron Job için Senkronizasyon Scripti
 * Komut satırı argümanları ile çalışır, hata durumunda email gönderebilir
 * 
 * Kullanım:
 *   php sync/sync_cron.php                    # Tüm tablolar
 *   php sync/sync_cron.php contacts products  # Sadece belirtilen tablolar
 * 
 * Cron örneği (her gün saat 02:00'de):
 *   0 2 * * * cd /path/to/parasut_v3 && php sync/sync_cron.php >> logs/cron.log 2>&1
 */

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log dosyası ayarla
$logFile = __DIR__ . '/../logs/sync_' . date('Y-m-d') . '.log';
ini_set('error_log', $logFile);

require_once __DIR__ . '/sync.php';

/**
 * Log mesajı yaz
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../logs/sync_' . date('Y-m-d') . '.log';
    $logMessage = "[$timestamp] $message\n";
    
    // Dosyaya yaz
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Konsola da yaz (eğer CLI ise)
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

/**
 * Email gönder (opsiyonel)
 */
function sendErrorEmail($subject, $message) {
    // .env'den email ayarlarını oku
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\'');
        }
    }
    
    $toEmail = $env['ALERT_EMAIL'] ?? null;
    
    if (!$toEmail) {
        return false;
    }
    
    $headers = [
        'From: ' . ($env['ALERT_FROM_EMAIL'] ?? 'noreply@example.com'),
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($toEmail, $subject, $message, implode("\r\n", $headers));
}

// Ana işlem
try {
    logMessage("=== Senkronizasyon başlatıldı ===");
    
    $syncManager = new SyncManager();
    
    // Komut satırı argümanlarını al
    $tables = array_slice($argv, 1);
    
    logMessage("Senkronize edilecek tablolar: " . (empty($tables) ? 'TÜMÜ' : implode(', ', $tables)));
    
    $overallStats = $syncManager->sync($tables);
    
    // Özet log
    logMessage("=== Senkronizasyon tamamlandı ===");
    logMessage("Başarılı: {$overallStats['successful']}, Başarısız: {$overallStats['failed']}");
    
    // Başarısız tablolar varsa email gönder
    if ($overallStats['failed'] > 0) {
        $failedTables = [];
        foreach ($overallStats['tables'] as $tableName => $info) {
            if ($info['status'] === 'failed') {
                $failedTables[] = $tableName . ': ' . ($info['error'] ?? 'Bilinmeyen hata');
            }
        }
        
        $subject = "Paraşüt Sync Hatası - " . date('Y-m-d H:i:s');
        $message = "Senkronizasyon sırasında hata oluştu:\n\n";
        $message .= "Başarısız tablolar:\n" . implode("\n", $failedTables) . "\n\n";
        $message .= "Detaylı log: " . $logFile;
        
        sendErrorEmail($subject, $message);
        logMessage("Hata email'i gönderildi");
    }
    
    exit(0);
    
} catch (Exception $e) {
    $errorMessage = "Kritik hata: " . $e->getMessage() . "\n";
    $errorMessage .= "Dosya: " . $e->getFile() . "\n";
    $errorMessage .= "Satır: " . $e->getLine() . "\n";
    
    logMessage($errorMessage);
    
    // Kritik hata email'i gönder
    $subject = "Paraşüt Sync Kritik Hata - " . date('Y-m-d H:i:s');
    sendErrorEmail($subject, $errorMessage);
    
    exit(1);
}

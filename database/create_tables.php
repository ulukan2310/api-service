<?php
/**
 * Database Table Creation Script
 * Veritabanı tablolarını oluşturan script
 * 
 * Kullanım: php database/create_tables.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Veritabanı bağlantısı kuruluyor...\n";
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "Bağlantı başarılı!\n\n";
    
    // Schema dosyasını oku
    $schemaFile = __DIR__ . '/schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema dosyası bulunamadı: $schemaFile");
    }
    
    echo "Schema dosyası okunuyor...\n";
    $schema = file_get_contents($schemaFile);
    
    // Her CREATE TABLE komutunu ayrı ayrı çalıştır
    // Regex ile CREATE TABLE IF NOT EXISTS ile başlayan ve ; ile biten komutları bul
    preg_match_all('/CREATE TABLE IF NOT EXISTS[^;]+;/is', $schema, $matches);
    
    $tablesCreated = 0;
    $tablesSkipped = 0;
    
    foreach ($matches[0] as $statement) {
        $statement = trim($statement);
        
        if (empty($statement)) {
            continue;
        }
        
        // Tablo adını bul
        if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $statement, $tableMatch)) {
            $tableName = $tableMatch[1];
            
            try {
                echo "Tablo oluşturuluyor: $tableName... ";
                $conn->exec($statement);
                echo "✓ Başarılı\n";
                $tablesCreated++;
            } catch (PDOException $e) {
                // Tablo zaten varsa devam et
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "⚠ Zaten mevcut\n";
                    $tablesSkipped++;
                } else {
                    echo "✗ Hata: " . $e->getMessage() . "\n";
                    throw $e;
                }
            }
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Özet:\n";
    echo "Oluşturulan tablolar: $tablesCreated\n";
    echo "Zaten mevcut tablolar: $tablesSkipped\n";
    echo "Toplam: " . ($tablesCreated + $tablesSkipped) . " tablo\n";
    echo "========================================\n";
    echo "\nVeritabanı kurulumu tamamlandı!\n";
    
} catch (Exception $e) {
    echo "\n✗ HATA: " . $e->getMessage() . "\n";
    echo "Dosya: " . $e->getFile() . "\n";
    echo "Satır: " . $e->getLine() . "\n";
    exit(1);
}

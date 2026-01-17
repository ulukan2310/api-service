<?php
/**
 * Product Model
 * Ürün/Hizmet modeli - Paraşüt API v2 entegrasyonu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';

class Product {
    private $conn;
    private $client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->client = new ParasutClient();
    }
    
    /**
     * API'den tüm product'ları çek ve kaydet
     * @return array İstatistikler
     */
    public function syncFromAPI() {
        $stats = [
            'fetched' => 0,
            'saved' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        try {
            $apiConfig = new ParasutAPI();
            $endpoint = $apiConfig->getEndpoint('products');
            
            echo "Product'lar API'den çekiliyor...\n";
            $products = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($products);
            echo count($products) . " product bulundu.\n";
            
            foreach ($products as $productData) {
                try {
                    $result = $this->save($productData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $productData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Product kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Product sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Product'ı veritabanına kaydet
     * @param array $productData API'den gelen product verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($productData) {
        if (empty($productData['id'])) {
            throw new Exception("Product ID bulunamadı");
        }
        
        $parasutId = $productData['id'];
        $attributes = $productData['attributes'] ?? [];
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'name' => $attributes['name'] ?? '',
            'code' => $attributes['code'] ?? null,
            'vat_rate' => $this->parseDecimal($attributes['vat_rate'] ?? 0),
            'sales_excise_duty' => $this->parseDecimal($attributes['sales_excise_duty'] ?? 0),
            'sales_excise_duty_type' => $attributes['sales_excise_duty_type'] ?? null,
            'purchase_excise_duty' => $this->parseDecimal($attributes['purchase_excise_duty'] ?? 0),
            'purchase_excise_duty_type' => $attributes['purchase_excise_duty_type'] ?? null,
            'unit' => $attributes['unit'] ?? null,
            'archived' => isset($attributes['archived']) && $attributes['archived'] ? 1 : 0,
            'list_price' => $this->parseDecimal($attributes['list_price'] ?? 0),
            'currency' => $attributes['currency'] ?? 'TRY',
            'buying_price' => $this->parseDecimal($attributes['buying_price'] ?? 0),
            'buying_currency' => $attributes['buying_currency'] ?? 'TRY',
            'inventory_tracking' => isset($attributes['inventory_tracking']) && $attributes['inventory_tracking'] ? 1 : 0,
            'initial_stock_count' => $this->parseDecimal($attributes['initial_stock_count'] ?? 0),
            'category' => $attributes['category'] ?? null,
            'raw_data' => json_encode($productData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_products SET
                    name = ?, code = ?, vat_rate = ?, sales_excise_duty = ?,
                    sales_excise_duty_type = ?, purchase_excise_duty = ?,
                    purchase_excise_duty_type = ?, unit = ?, archived = ?,
                    list_price = ?, currency = ?, buying_price = ?, buying_currency = ?,
                    inventory_tracking = ?, initial_stock_count = ?, category = ?,
                    raw_data = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'], $data['code'], $data['vat_rate'],
                $data['sales_excise_duty'], $data['sales_excise_duty_type'],
                $data['purchase_excise_duty'], $data['purchase_excise_duty_type'],
                $data['unit'], $data['archived'], $data['list_price'],
                $data['currency'], $data['buying_price'], $data['buying_currency'],
                $data['inventory_tracking'], $data['initial_stock_count'],
                $data['category'], $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_products (
                    parasut_id, name, code, vat_rate, sales_excise_duty,
                    sales_excise_duty_type, purchase_excise_duty,
                    purchase_excise_duty_type, unit, archived, list_price,
                    currency, buying_price, buying_currency, inventory_tracking,
                    initial_stock_count, category, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['name'], $data['code'], $data['vat_rate'],
                $data['sales_excise_duty'], $data['sales_excise_duty_type'],
                $data['purchase_excise_duty'], $data['purchase_excise_duty_type'],
                $data['unit'], $data['archived'], $data['list_price'],
                $data['currency'], $data['buying_price'], $data['buying_currency'],
                $data['inventory_tracking'], $data['initial_stock_count'],
                $data['category'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile product bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_products WHERE parasut_id = ?");
        $stmt->execute([$parasutId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Decimal değeri parse et
     * @param mixed $value
     * @return float
     */
    private function parseDecimal($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return 0.00;
    }
}

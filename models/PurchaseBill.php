<?php
/**
 * Purchase Bill Model
 * Alış faturası modeli - Paraşüt API v2 entegrasyonu
 * İlişkisel veri: contact_id foreign key
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';
require_once __DIR__ . '/Contact.php';

class PurchaseBill {
    private $conn;
    private $client;
    private $contactModel;
    
    /**
     * Constructor
     */
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->client = new ParasutClient();
        $this->contactModel = new Contact();
    }
    
    /**
     * API'den tüm purchase bill'leri çek ve kaydet
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
            $endpoint = $apiConfig->getEndpoint('purchase_bills');
            
            echo "Purchase bill'ler API'den çekiliyor...\n";
            $bills = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($bills);
            echo count($bills) . " purchase bill bulundu.\n";
            
            foreach ($bills as $billData) {
                try {
                    $result = $this->save($billData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $billData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Purchase bill kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Purchase bill sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Purchase bill'i veritabanına kaydet
     * @param array $billData API'den gelen bill verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($billData) {
        if (empty($billData['id'])) {
            throw new Exception("Purchase bill ID bulunamadı");
        }
        
        $parasutId = $billData['id'];
        $attributes = $billData['attributes'] ?? [];
        $relationships = $billData['relationships'] ?? [];
        
        // Contact ilişkisini çöz
        $contactId = null;
        if (isset($relationships['contact']['data']['id'])) {
            $contactParasutId = $relationships['contact']['data']['id'];
            $contact = $this->contactModel->findByParasutId($contactParasutId);
            
            if ($contact) {
                $contactId = $contact['id'];
            } else {
                error_log("Purchase bill için contact bulunamadı: $contactParasutId (Bill: $parasutId)");
            }
        }
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'contact_id' => $contactId,
            'bill_series' => $attributes['bill_series'] ?? null,
            'bill_number' => $attributes['bill_no'] ?? $attributes['bill_number'] ?? null,
            'bill_date' => $this->parseDate($attributes['bill_date'] ?? null),
            'due_date' => $this->parseDate($attributes['due_date'] ?? null),
            'net_total' => $this->parseDecimal($attributes['net_total'] ?? 0),
            'vat_total' => $this->parseDecimal($attributes['vat_total'] ?? 0),
            'gross_total' => $this->parseDecimal($attributes['gross_total'] ?? 0),
            'currency' => $attributes['currency'] ?? 'TRY',
            'exchange_rate' => $this->parseDecimal($attributes['exchange_rate'] ?? 1.0),
            'withholding_rate' => $this->parseDecimal($attributes['withholding_rate'] ?? 0),
            'vat_withholding_rate' => $this->parseDecimal($attributes['vat_withholding_rate'] ?? 0),
            'bill_status' => $attributes['status'] ?? null,
            'payment_status' => $attributes['payment_status'] ?? null,
            'description' => $attributes['description'] ?? null,
            'item_type' => $attributes['item_type'] ?? null,
            'archived' => isset($attributes['archived']) && $attributes['archived'] ? 1 : 0,
            'raw_data' => json_encode($billData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_purchase_bills SET
                    contact_id = ?, bill_series = ?, bill_number = ?,
                    bill_date = ?, due_date = ?, net_total = ?, vat_total = ?,
                    gross_total = ?, currency = ?, exchange_rate = ?,
                    withholding_rate = ?, vat_withholding_rate = ?,
                    bill_status = ?, payment_status = ?, description = ?,
                    item_type = ?, archived = ?, raw_data = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['contact_id'], $data['bill_series'], $data['bill_number'],
                $data['bill_date'], $data['due_date'], $data['net_total'],
                $data['vat_total'], $data['gross_total'], $data['currency'],
                $data['exchange_rate'], $data['withholding_rate'],
                $data['vat_withholding_rate'], $data['bill_status'],
                $data['payment_status'], $data['description'], $data['item_type'],
                $data['archived'], $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_purchase_bills (
                    parasut_id, contact_id, bill_series, bill_number,
                    bill_date, due_date, net_total, vat_total, gross_total,
                    currency, exchange_rate, withholding_rate, vat_withholding_rate,
                    bill_status, payment_status, description, item_type,
                    archived, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['contact_id'], $data['bill_series'],
                $data['bill_number'], $data['bill_date'], $data['due_date'],
                $data['net_total'], $data['vat_total'], $data['gross_total'],
                $data['currency'], $data['exchange_rate'], $data['withholding_rate'],
                $data['vat_withholding_rate'], $data['bill_status'],
                $data['payment_status'], $data['description'], $data['item_type'],
                $data['archived'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile purchase bill bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_purchase_bills WHERE parasut_id = ?");
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
    
    /**
     * Tarih değerini parse et
     * @param mixed $value
     * @return string|null YYYY-MM-DD formatında
     */
    private function parseDate($value) {
        if (empty($value)) {
            return null;
        }
        
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }
        
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}

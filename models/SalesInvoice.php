<?php
/**
 * Sales Invoice Model
 * Satış faturası modeli - Paraşüt API v2 entegrasyonu
 * İlişkisel veri: contact_id foreign key
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';
require_once __DIR__ . '/Contact.php';

class SalesInvoice {
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
     * API'den tüm sales invoice'ları çek ve kaydet
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
            $endpoint = $apiConfig->getEndpoint('sales_invoices');
            
            echo "Sales invoice'lar API'den çekiliyor...\n";
            $invoices = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($invoices);
            echo count($invoices) . " sales invoice bulundu.\n";
            
            foreach ($invoices as $invoiceData) {
                try {
                    $result = $this->save($invoiceData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $invoiceData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Sales invoice kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Sales invoice sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Sales invoice'ı veritabanına kaydet
     * @param array $invoiceData API'den gelen invoice verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($invoiceData) {
        if (empty($invoiceData['id'])) {
            throw new Exception("Sales invoice ID bulunamadı");
        }
        
        $parasutId = $invoiceData['id'];
        $attributes = $invoiceData['attributes'] ?? [];
        $relationships = $invoiceData['relationships'] ?? [];
        
        // Contact ilişkisini çöz
        $contactId = null;
        if (isset($relationships['contact']['data']['id'])) {
            $contactParasutId = $relationships['contact']['data']['id'];
            $contact = $this->contactModel->findByParasutId($contactParasutId);
            
            if ($contact) {
                $contactId = $contact['id'];
            } else {
                // Contact bulunamadı - uyarı ver ama devam et
                error_log("Sales invoice için contact bulunamadı: $contactParasutId (Invoice: $parasutId)");
            }
        }
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'contact_id' => $contactId,
            'invoice_series' => $attributes['invoice_series'] ?? null,
            'invoice_number' => $attributes['invoice_no'] ?? $attributes['invoice_number'] ?? null,
            'invoice_date' => $this->parseDate($attributes['invoice_date'] ?? null),
            'due_date' => $this->parseDate($attributes['due_date'] ?? null),
            'net_total' => $this->parseDecimal($attributes['net_total'] ?? 0),
            'vat_total' => $this->parseDecimal($attributes['vat_total'] ?? 0),
            'gross_total' => $this->parseDecimal($attributes['gross_total'] ?? 0),
            'currency' => $attributes['currency'] ?? 'TRY',
            'exchange_rate' => $this->parseDecimal($attributes['exchange_rate'] ?? 1.0),
            'withholding_rate' => $this->parseDecimal($attributes['withholding_rate'] ?? 0),
            'vat_withholding_rate' => $this->parseDecimal($attributes['vat_withholding_rate'] ?? 0),
            'invoice_status' => $attributes['status'] ?? null,
            'payment_status' => $attributes['payment_status'] ?? null,
            'description' => $attributes['description'] ?? null,
            'item_type' => $attributes['item_type'] ?? null,
            'archived' => isset($attributes['archived']) && $attributes['archived'] ? 1 : 0,
            'raw_data' => json_encode($invoiceData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_sales_invoices SET
                    contact_id = ?, invoice_series = ?, invoice_number = ?,
                    invoice_date = ?, due_date = ?, net_total = ?, vat_total = ?,
                    gross_total = ?, currency = ?, exchange_rate = ?,
                    withholding_rate = ?, vat_withholding_rate = ?,
                    invoice_status = ?, payment_status = ?, description = ?,
                    item_type = ?, archived = ?, raw_data = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['contact_id'], $data['invoice_series'], $data['invoice_number'],
                $data['invoice_date'], $data['due_date'], $data['net_total'],
                $data['vat_total'], $data['gross_total'], $data['currency'],
                $data['exchange_rate'], $data['withholding_rate'],
                $data['vat_withholding_rate'], $data['invoice_status'],
                $data['payment_status'], $data['description'], $data['item_type'],
                $data['archived'], $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_sales_invoices (
                    parasut_id, contact_id, invoice_series, invoice_number,
                    invoice_date, due_date, net_total, vat_total, gross_total,
                    currency, exchange_rate, withholding_rate, vat_withholding_rate,
                    invoice_status, payment_status, description, item_type,
                    archived, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['contact_id'], $data['invoice_series'],
                $data['invoice_number'], $data['invoice_date'], $data['due_date'],
                $data['net_total'], $data['vat_total'], $data['gross_total'],
                $data['currency'], $data['exchange_rate'], $data['withholding_rate'],
                $data['vat_withholding_rate'], $data['invoice_status'],
                $data['payment_status'], $data['description'], $data['item_type'],
                $data['archived'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile sales invoice bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_sales_invoices WHERE parasut_id = ?");
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
        
        // String ise parse et
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}

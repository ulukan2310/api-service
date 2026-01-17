<?php
/**
 * Payment Model
 * Ödeme modeli - Paraşüt API v2 entegrasyonu
 * İlişkiler: account_id, contact_id foreign keys
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';
require_once __DIR__ . '/Account.php';
require_once __DIR__ . '/Contact.php';

class Payment {
    private $conn;
    private $client;
    private $accountModel;
    private $contactModel;
    
    /**
     * Constructor
     */
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->client = new ParasutClient();
        $this->accountModel = new Account();
        $this->contactModel = new Contact();
    }
    
    /**
     * API'den tüm payment'ları çek ve kaydet
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
            $endpoint = $apiConfig->getEndpoint('payments');
            
            echo "Payment'lar API'den çekiliyor...\n";
            $payments = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($payments);
            echo count($payments) . " payment bulundu.\n";
            
            foreach ($payments as $paymentData) {
                try {
                    $result = $this->save($paymentData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $paymentData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Payment kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Payment sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Payment'ı veritabanına kaydet
     * @param array $paymentData API'den gelen payment verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($paymentData) {
        if (empty($paymentData['id'])) {
            throw new Exception("Payment ID bulunamadı");
        }
        
        $parasutId = $paymentData['id'];
        $attributes = $paymentData['attributes'] ?? [];
        $relationships = $paymentData['relationships'] ?? [];
        
        // Account ilişkisini çöz
        $accountId = null;
        if (isset($relationships['account']['data']['id'])) {
            $accountParasutId = $relationships['account']['data']['id'];
            $account = $this->accountModel->findByParasutId($accountParasutId);
            
            if ($account) {
                $accountId = $account['id'];
            } else {
                error_log("Payment için account bulunamadı: $accountParasutId (Payment: $parasutId)");
            }
        }
        
        // Contact ilişkisini çöz
        $contactId = null;
        if (isset($relationships['contact']['data']['id'])) {
            $contactParasutId = $relationships['contact']['data']['id'];
            $contact = $this->contactModel->findByParasutId($contactParasutId);
            
            if ($contact) {
                $contactId = $contact['id'];
            } else {
                error_log("Payment için contact bulunamadı: $contactParasutId (Payment: $parasutId)");
            }
        }
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'account_id' => $accountId,
            'contact_id' => $contactId,
            'payment_date' => $this->parseDate($attributes['date'] ?? $attributes['payment_date'] ?? null),
            'amount' => $this->parseDecimal($attributes['amount'] ?? 0),
            'currency' => $attributes['currency'] ?? 'TRY',
            'exchange_rate' => $this->parseDecimal($attributes['exchange_rate'] ?? 1.0),
            'payment_type' => $attributes['payment_type'] ?? null,
            'description' => $attributes['description'] ?? null,
            'archived' => isset($attributes['archived']) && $attributes['archived'] ? 1 : 0,
            'raw_data' => json_encode($paymentData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_payments SET
                    account_id = ?, contact_id = ?, payment_date = ?,
                    amount = ?, currency = ?, exchange_rate = ?,
                    payment_type = ?, description = ?, archived = ?,
                    raw_data = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['account_id'], $data['contact_id'], $data['payment_date'],
                $data['amount'], $data['currency'], $data['exchange_rate'],
                $data['payment_type'], $data['description'], $data['archived'],
                $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_payments (
                    parasut_id, account_id, contact_id, payment_date,
                    amount, currency, exchange_rate, payment_type,
                    description, archived, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['account_id'], $data['contact_id'],
                $data['payment_date'], $data['amount'], $data['currency'],
                $data['exchange_rate'], $data['payment_type'], $data['description'],
                $data['archived'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile payment bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_payments WHERE parasut_id = ?");
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

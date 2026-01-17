<?php
/**
 * Account Model
 * Kasa/Banka hesabı modeli - Paraşüt API v2 entegrasyonu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';

class Account {
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
     * API'den tüm account'ları çek ve kaydet
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
            $endpoint = $apiConfig->getEndpoint('accounts');
            
            echo "Account'lar API'den çekiliyor...\n";
            $accounts = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($accounts);
            echo count($accounts) . " account bulundu.\n";
            
            foreach ($accounts as $accountData) {
                try {
                    $result = $this->save($accountData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $accountData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Account kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Account sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Account'ı veritabanına kaydet
     * @param array $accountData API'den gelen account verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($accountData) {
        if (empty($accountData['id'])) {
            throw new Exception("Account ID bulunamadı");
        }
        
        $parasutId = $accountData['id'];
        $attributes = $accountData['attributes'] ?? [];
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'name' => $attributes['name'] ?? '',
            'currency' => $attributes['currency'] ?? 'TRY',
            'account_type' => $attributes['account_type'] ?? null,
            'bank_name' => $attributes['bank_name'] ?? null,
            'bank_branch' => $attributes['bank_branch'] ?? null,
            'account_number' => $attributes['account_number'] ?? null,
            'iban' => $attributes['iban'] ?? null,
            'balance' => $this->parseDecimal($attributes['balance'] ?? 0),
            'archived' => isset($attributes['archived']) && $attributes['archived'] ? 1 : 0,
            'raw_data' => json_encode($accountData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_accounts SET
                    name = ?, currency = ?, account_type = ?, bank_name = ?,
                    bank_branch = ?, account_number = ?, iban = ?,
                    balance = ?, archived = ?, raw_data = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'], $data['currency'], $data['account_type'],
                $data['bank_name'], $data['bank_branch'], $data['account_number'],
                $data['iban'], $data['balance'], $data['archived'],
                $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_accounts (
                    parasut_id, name, currency, account_type, bank_name,
                    bank_branch, account_number, iban, balance, archived, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['name'], $data['currency'],
                $data['account_type'], $data['bank_name'], $data['bank_branch'],
                $data['account_number'], $data['iban'], $data['balance'],
                $data['archived'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile account bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_accounts WHERE parasut_id = ?");
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

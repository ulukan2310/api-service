<?php
/**
 * Contact Model
 * Müşteri/Tedarikçi modeli - Paraşüt API v2 entegrasyonu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';

class Contact {
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
     * API'den tüm contact'ları çek ve kaydet
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
            $endpoint = $apiConfig->getEndpoint('contacts');
            
            echo "Contact'lar API'den çekiliyor...\n";
            $contacts = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($contacts);
            echo count($contacts) . " contact bulundu.\n";
            
            foreach ($contacts as $contactData) {
                try {
                    $result = $this->save($contactData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $contactData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Contact kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Contact sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Contact'ı veritabanına kaydet
     * @param array $contactData API'den gelen contact verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($contactData) {
        if (empty($contactData['id'])) {
            throw new Exception("Contact ID bulunamadı");
        }
        
        $parasutId = $contactData['id'];
        $attributes = $contactData['attributes'] ?? [];
        $relationships = $contactData['relationships'] ?? [];
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'name' => $attributes['name'] ?? '',
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'tax_number' => $attributes['tax_number'] ?? null,
            'tax_office' => $attributes['tax_office'] ?? null,
            'contact_type' => $this->determineContactType($attributes),
            'balance' => $this->parseDecimal($attributes['balance'] ?? 0),
            'tr_balance' => $this->parseDecimal($attributes['tr_balance'] ?? 0),
            'us_balance' => $this->parseDecimal($attributes['us_balance'] ?? 0),
            'eu_balance' => $this->parseDecimal($attributes['eu_balance'] ?? 0),
            'gb_balance' => $this->parseDecimal($attributes['gb_balance'] ?? 0),
            'address' => $this->extractAddress($attributes),
            'city' => $attributes['city'] ?? null,
            'district' => $attributes['district'] ?? null,
            'country' => $attributes['country'] ?? null,
            'postal_code' => $attributes['postal_code'] ?? null,
            'raw_data' => json_encode($contactData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_contacts SET
                    name = ?, email = ?, phone = ?, tax_number = ?, tax_office = ?,
                    contact_type = ?, balance = ?, tr_balance = ?, us_balance = ?,
                    eu_balance = ?, gb_balance = ?, address = ?, city = ?,
                    district = ?, country = ?, postal_code = ?, raw_data = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'], $data['email'], $data['phone'], $data['tax_number'],
                $data['tax_office'], $data['contact_type'], $data['balance'],
                $data['tr_balance'], $data['us_balance'], $data['eu_balance'],
                $data['gb_balance'], $data['address'], $data['city'],
                $data['district'], $data['country'], $data['postal_code'],
                $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_contacts (
                    parasut_id, name, email, phone, tax_number, tax_office,
                    contact_type, balance, tr_balance, us_balance, eu_balance,
                    gb_balance, address, city, district, country, postal_code, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['name'], $data['email'], $data['phone'],
                $data['tax_number'], $data['tax_office'], $data['contact_type'],
                $data['balance'], $data['tr_balance'], $data['us_balance'],
                $data['eu_balance'], $data['gb_balance'], $data['address'],
                $data['city'], $data['district'], $data['country'],
                $data['postal_code'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile contact bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_contacts WHERE parasut_id = ?");
        $stmt->execute([$parasutId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Contact type belirle
     * @param array $attributes
     * @return string
     */
    private function determineContactType($attributes) {
        $isCustomer = $attributes['is_customer'] ?? false;
        $isSupplier = $attributes['is_supplier'] ?? false;
        
        if ($isCustomer && $isSupplier) {
            return 'both';
        } elseif ($isSupplier) {
            return 'supplier';
        } else {
            return 'customer';
        }
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
     * Adres bilgisini çıkar
     * @param array $attributes
     * @return string|null
     */
    private function extractAddress($attributes) {
        $addressParts = [];
        
        if (!empty($attributes['address'])) {
            $addressParts[] = $attributes['address'];
        }
        if (!empty($attributes['address_2'])) {
            $addressParts[] = $attributes['address_2'];
        }
        
        return !empty($addressParts) ? implode(', ', $addressParts) : null;
    }
}

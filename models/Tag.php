<?php
/**
 * Tag Model
 * Etiket modeli - Paraşüt API v2 entegrasyonu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/client.php';

class Tag {
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
     * API'den tüm tag'leri çek ve kaydet
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
            $endpoint = $apiConfig->getEndpoint('tags');
            
            echo "Tag'ler API'den çekiliyor...\n";
            $tags = $this->client->getAll($endpoint);
            
            $stats['fetched'] = count($tags);
            echo count($tags) . " tag bulundu.\n";
            
            foreach ($tags as $tagData) {
                try {
                    $result = $this->save($tagData);
                    
                    if ($result['action'] === 'created') {
                        $stats['saved']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = [
                        'parasut_id' => $tagData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    $stats['skipped']++;
                    error_log("Tag kaydetme hatası: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Tag sync hatası: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Tag'i veritabanına kaydet
     * @param array $tagData API'den gelen tag verisi
     * @return array ['action' => 'created|updated|skipped', 'id' => int]
     */
    public function save($tagData) {
        if (empty($tagData['id'])) {
            throw new Exception("Tag ID bulunamadı");
        }
        
        $parasutId = $tagData['id'];
        $attributes = $tagData['attributes'] ?? [];
        
        // Mevcut kaydı kontrol et
        $existing = $this->findByParasutId($parasutId);
        
        // Veriyi hazırla
        $data = [
            'parasut_id' => $parasutId,
            'name' => $attributes['name'] ?? '',
            'raw_data' => json_encode($tagData)
        ];
        
        if ($existing) {
            // Güncelle
            $stmt = $this->conn->prepare("
                UPDATE parasut_tags SET
                    name = ?, raw_data = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'], $data['raw_data'], $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Yeni kayıt
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_tags (parasut_id, name, raw_data)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $data['parasut_id'], $data['name'], $data['raw_data']
            ]);
            
            return ['action' => 'created', 'id' => $this->conn->lastInsertId()];
        }
    }
    
    /**
     * Paraşüt ID ile tag bul
     * @param string $parasutId
     * @return array|null
     */
    public function findByParasutId($parasutId) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_tags WHERE parasut_id = ?");
        $stmt->execute([$parasutId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * ID ile tag bul
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM parasut_tags WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

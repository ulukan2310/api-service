<?php
/**
 * Tag Relation Model
 * Etiket ilişkisi modeli - Many-to-many ilişki yönetimi
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Tag.php';

class TagRelation {
    private $conn;
    private $tagModel;
    
    /**
     * Constructor
     */
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->tagModel = new Tag();
    }
    
    /**
     * Tag ilişkisini kaydet
     * @param string $tagParasutId Tag'ın Paraşüt ID'si
     * @param string $taggableType İlişkili model tipi (contacts, products, sales_invoices, vb.)
     * @param int $taggableId İlişkili kaydın ID'si
     * @return bool
     */
    public function save($tagParasutId, $taggableType, $taggableId) {
        // Tag'ı bul
        $tag = $this->tagModel->findByParasutId($tagParasutId);
        
        if (!$tag) {
            error_log("Tag bulunamadı: $tagParasutId");
            return false;
        }
        
        $tagId = $tag['id'];
        
        // Mevcut ilişkiyi kontrol et
        $existing = $this->find($tagId, $taggableType, $taggableId);
        
        if ($existing) {
            // Zaten mevcut
            return true;
        }
        
        // Yeni ilişki oluştur
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO parasut_tag_relations (tag_id, taggable_type, taggable_id)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([$tagId, $taggableType, $taggableId]);
            return true;
        } catch (PDOException $e) {
            // Duplicate key hatası ise sorun değil
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return true;
            }
            error_log("Tag relation kaydetme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tag ilişkisini bul
     * @param int $tagId
     * @param string $taggableType
     * @param int $taggableId
     * @return array|null
     */
    public function find($tagId, $taggableType, $taggableId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM parasut_tag_relations 
            WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?
        ");
        $stmt->execute([$tagId, $taggableType, $taggableId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Bir kaydın tüm tag'lerini getir
     * @param string $taggableType
     * @param int $taggableId
     * @return array
     */
    public function getTagsFor($taggableType, $taggableId) {
        $stmt = $this->conn->prepare("
            SELECT t.* FROM parasut_tags t
            INNER JOIN parasut_tag_relations tr ON t.id = tr.tag_id
            WHERE tr.taggable_type = ? AND tr.taggable_id = ?
        ");
        $stmt->execute([$taggableType, $taggableId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Bir kayıt için tüm tag ilişkilerini sil
     * @param string $taggableType
     * @param int $taggableId
     * @return bool
     */
    public function deleteFor($taggableType, $taggableId) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM parasut_tag_relations 
                WHERE taggable_type = ? AND taggable_id = ?
            ");
            $stmt->execute([$taggableType, $taggableId]);
            return true;
        } catch (PDOException $e) {
            error_log("Tag relation silme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * API'den gelen tag ilişkilerini kaydet
     * @param array $relationships API relationships objesi
     * @param string $taggableType İlişkili model tipi
     * @param int $taggableId İlişkili kaydın ID'si
     * @return int Kaydedilen ilişki sayısı
     */
    public function syncFromRelationships($relationships, $taggableType, $taggableId) {
        // Önce mevcut ilişkileri sil
        $this->deleteFor($taggableType, $taggableId);
        
        $saved = 0;
        
        // Tags ilişkisini kontrol et
        if (isset($relationships['tags']['data']) && is_array($relationships['tags']['data'])) {
            foreach ($relationships['tags']['data'] as $tagData) {
                if (isset($tagData['id'])) {
                    if ($this->save($tagData['id'], $taggableType, $taggableId)) {
                        $saved++;
                    }
                }
            }
        }
        
        return $saved;
    }
}

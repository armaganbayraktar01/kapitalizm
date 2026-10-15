<?php
namespace App\Models;

use System\Core\Database;
use PDO;

class Setting {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Tüm ayarları anahtar-değer dizisi olarak getir
    public function getAll(): array {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    // Toplu ayar güncelleme (Admin panelinden gelen form verileri için)
    public function updateBatch(array $data): void {
        $stmt = $this->db->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
        
        foreach ($data as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => $value
            ]);
        }
    }
}
<?php
namespace App\Models;

use System\Core\Database;
use PDO;

class Page {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Tüm sayfaları listele
    public function getAll(): array {
        $stmt = $this->db->query("SELECT p.*, m.title as menu_title FROM pages p LEFT JOIN menus m ON p.menu_id = m.id ORDER BY p.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Slug değerine göre sayfa bul (Router için)
    public function findBySlug(string $slug) {
        $stmt = $this->db->prepare("SELECT * FROM pages WHERE slug = :slug AND status = 1");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ID'ye göre sayfa bul
    public function find(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM pages WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Yeni sayfa ekle
    public function create(array $data): bool {
        $stmt = $this->db->prepare("INSERT INTO pages (menu_id, title, slug, page_type, content, controller_name, action_name, status) VALUES (:menu_id, :title, :slug, :page_type, :content, :controller_name, :action_name, :status)");
        return $stmt->execute([
            'menu_id'         => !empty($data['menu_id']) ? $data['menu_id'] : null,
            'title'           => $data['title'],
            'slug'            => trim($data['slug'], '/'),
            'page_type'       => $data['page_type'],
            'content'         => $data['content'] ?? null,
            'controller_name' => $data['controller_name'] ?? null,
            'action_name'     => !empty($data['action_name']) ? $data['action_name'] : 'index',
            'status'          => isset($data['status']) ? 1 : 0
        ]);
    }

    // Sayfa güncelle
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE pages SET menu_id = :menu_id, title = :title, slug = :slug, page_type = :page_type, content = :content, controller_name = :controller_name, action_name = :action_name, status = :status WHERE id = :id");
        return $stmt->execute([
            'id'              => $id,
            'menu_id'         => !empty($data['menu_id']) ? $data['menu_id'] : null,
            'title'           => $data['title'],
            'slug'            => trim($data['slug'], '/'),
            'page_type'       => $data['page_type'],
            'content'         => $data['content'] ?? null,
            'controller_name' => $data['controller_name'] ?? null,
            'action_name'     => !empty($data['action_name']) ? $data['action_name'] : 'index',
            'status'          => isset($data['status']) ? 1 : 0
        ]);
    }

    // Sayfa sil
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM pages WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
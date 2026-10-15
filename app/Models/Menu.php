<?php
namespace App\Models;

use System\Core\Database;

class Menu {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM menus ORDER BY menu_order ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getActiveMenus() {
        $stmt = $this->db->query("SELECT * FROM menus WHERE status = 1 ORDER BY menu_order ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getParentMenus() {
        $stmt = $this->db->query("SELECT * FROM menus WHERE parent_id IS NULL OR parent_id = 0 ORDER BY menu_order ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM menus WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    public function updateOrder(int $id, int $order): bool {
        $stmt = $this->db->prepare("UPDATE menus SET menu_order = :menu_order WHERE id = :id");
        return $stmt->execute([
            'menu_order' => $order,
            'id'         => $id
        ]);
    }

    public function create(array $data): bool {
        $stmt = $this->db->prepare("INSERT INTO menus (parent_id, title, url, icon, menu_order, target, status) VALUES (:parent_id, :title, :url, :icon, :menu_order, :target, :status)");
        return $stmt->execute([
            'parent_id'  => !empty($data['parent_id']) ? $data['parent_id'] : null,
            'title'      => $data['title'],
            'url'        => $data['url'],
            'icon'       => !empty($data['icon']) ? $data['icon'] : 'fas fa-circle',
            'menu_order' => $data['menu_order'] ?? 0,
            'target'     => $data['target'] ?? '_self',
            'status'     => isset($data['status']) ? 1 : 0
        ]);
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE menus SET parent_id = :parent_id, title = :title, url = :url, icon = :icon, menu_order = :menu_order, target = :target, status = :status WHERE id = :id");
        return $stmt->execute([
            'id'         => $id,
            'parent_id'  => !empty($data['parent_id']) ? $data['parent_id'] : null,
            'title'      => $data['title'],
            'url'        => $data['url'],
            'icon'       => !empty($data['icon']) ? $data['icon'] : 'fas fa-circle',
            'menu_order' => $data['menu_order'] ?? 0,
            'target'     => $data['target'] ?? '_self',
            'status'     => isset($data['status']) ? 1 : 0
        ]);
    }

    public function delete(int $id): bool {
        $stmtChild = $this->db->prepare("UPDATE menus SET parent_id = NULL WHERE parent_id = :id");
        $stmtChild->execute(['id' => $id]);

        $stmt = $this->db->prepare("DELETE FROM menus WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
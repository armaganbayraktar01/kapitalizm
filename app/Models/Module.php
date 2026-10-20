<?php
namespace App\Models;

use System\Core\Model;

class Module extends Model {
    protected string $table = 'modules';

    // Tüm modülleri getir
    public function getAll() {
        $db = $this->db();
        $stmt = $db->query("SELECT * FROM {$this->table} ORDER BY menu_order ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ID'ye göre bul
    public function find($id) {
        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Yeni Modül Ekle
    public function create($data) {
        $db = $this->db();
        $stmt = $db->prepare("INSERT INTO {$this->table} (title, url, icon, parent_id, menu_order, status, target) VALUES (:title, :url, :icon, :parent_id, :menu_order, :status, :target)");
        return $stmt->execute($data);
    }

    // Modül Güncelle (Status ve Target kontrolü dahil)
    public function update($id, $data) {
        $db = $this->db();
        $stmt = $db->prepare("UPDATE {$this->table} SET title = :title, url = :url, icon = :icon, parent_id = :parent_id, status = :status, target = :target WHERE id = :id");
        
        return $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'url' => $data['url'],
            'icon' => $data['icon'],
            'parent_id' => $data['parent_id'],
            'status' => $data['status'],
            'target' => $data['target']
        ]);
    }

    // Silme
    public function delete($id) {
        $db = $this->db();
        $stmt = $db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // Sürükle-bırak sıralama
    public function updateOrder($id, $order) {
        $db = $this->db();
        $stmt = $db->prepare("UPDATE {$this->table} SET menu_order = :menu_order WHERE id = :id");
        return $stmt->execute(['menu_order' => $order, 'id' => $id]);
    }

    // Aktif Hiyerarşik Menüleri Getirir
    public function getActiveHierarchicalMenus() {
        $db = $this->db();
        $stmt = $db->query("SELECT * FROM {$this->table} WHERE status = 1 ORDER BY menu_order ASC");
        $modules = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->buildTree($modules);
    }

    // Sadece Oyuncu (App) Paneli İçin Aktif Hiyerarşik Menüleri Getirir
    public function getAppHierarchicalMenus() {
        $db = $this->db();
        $stmt = $db->query("SELECT * FROM {$this->table} WHERE status = 1 AND (url LIKE 'app/%') ORDER BY menu_order ASC");
        $modules = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->buildTree($modules);
    }

    // Düz diziyi hiyerarşik ağaca dönüştüren yardımcı metot
    private function buildTree(array $elements, $parentId = null) {
        $branch = [];
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
}
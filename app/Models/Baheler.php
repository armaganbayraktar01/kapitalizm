<?php
namespace App\Models;

use System\Core\Database;

class Baheler {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM bahelers ORDER BY id DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM bahelers WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): bool {
        unset($_POST['form_type'], $_POST['module_name'], $_POST['template_type'], $_POST['fields']);
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = ':' . implode(', :', $keys);

        $stmt = $this->db->prepare("INSERT INTO bahelers ($fields) VALUES ($placeholders)");
        return $stmt->execute($data);
    }

    public function update(int $id, array $data): bool {
        unset($_POST['form_type'], $_POST['module_name'], $_POST['template_type'], $_POST['fields']);
        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= "$key = :$key, ";
        }
        $fields = rtrim($fields, ', ');

        $data['id'] = $id;
        $stmt = $this->db->prepare("UPDATE bahelers SET $fields WHERE id = :id");
        return $stmt->execute($data);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM bahelers WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

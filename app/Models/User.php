<?php
namespace App\Models;

use System\Core\Database;
use PDO;

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // E-posta adresine göre kullanıcı bulma
    public function findByEmail(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Yeni kullanıcı kaydı oluşturma
    public function create(array $data) {
        $sql = "INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT), // Şifreyi güvenli şekilde şifreliyoruz
            'role'     => $data['role'] ?? 'user'
        ]);
    }
}
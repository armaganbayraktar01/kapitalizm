<?php
namespace App\Controllers;

use System\Core\Controller;

class AdminController extends Controller {

    public function __construct() {
        // Bu Controller içerisindeki tüm metodlara girmeden önce admin yetkisi kontrol edilir!
        admin_check();
    }

    // Admin Panel Ana Sayfası / Dashboard
    public function dashboard() {
        $data = [
            'title' => 'Admin Paneli - Dashboard',
            'username' => $_SESSION['username'] ?? 'Admin'
        ];

        // Görünümü yükle (İleride app/Views/admin/dashboard.php oluşturacağız)
        $this->view('admin/dashboard', $data);
    }
}
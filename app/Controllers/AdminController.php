<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class AdminController extends Controller {

    public function __construct() {
        admin_check();
    }

    // Admin Panel Ana Sayfası / Dashboard
    public function dashboard() {
        $data = [
            'title' => 'Yönetim Paneli - Dashboard'
        ];
        $this->adminView('admin/dashboard', $data);
    }

    // ==========================================
    // MENÜ YÖNETİMİ
    // ==========================================

    public function menus() {
        $menuModel = $this->model('Menu');
        $menus = $menuModel->getAll();

        $data = [
            'title' => 'Sidebar Menü Yönetimi',
            'menus' => $menus
        ];

        $this->adminView('admin/menus/index', $data);
    }

    public function menuCreate() {
        $menuModel = $this->model('Menu');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $menuModel->create($_POST);
            redirect('admin/menus');
        }

        $data = [
            'title' => 'Yeni Menü Ekle'
        ];

        $this->adminView('admin/menus/create', $data);
    }

    public function menuEdit($id) {
        $menuModel = $this->model('Menu');
        $menu = $menuModel->find($id);

        if (!$menu) {
            redirect('admin/menus');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $menuModel->update($id, $_POST);
            redirect('admin/menus');
        }

        $data = [
            'title' => 'Menüyü Düzenle',
            'menu' => $menu
        ];

        $this->adminView('admin/menus/edit', $data);
    }

    public function menuDelete($id) {
        $menuModel = $this->model('Menu');
        $menuModel->delete($id);
        redirect('admin/menus');
    }

    public function menuSort() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
            $menuModel = $this->model('Menu');
            $order = $_POST['order'];

            foreach ($order as $index => $menuId) {
                if (method_exists($menuModel, 'updateOrder')) {
                    $menuModel->updateOrder((int)$menuId, $index + 1);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Sıralama güncellendi.']);
            exit;
        }
    }

    // ==========================================
    // SAYFA YÖNETİMİ
    // ==========================================

    public function pages() {
        $pageModel = $this->model('Page');
        $pages = $pageModel->getAll();

        $data = [
            'title' => 'Sayfa Yönetimi',
            'pages' => $pages
        ];

        $this->adminView('admin/pages/index', $data);
    }

    public function pageCreate() {
        $pageModel = $this->model('Page');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $slug = trim($_POST['slug'] ?? '', '/');
            $_POST['slug'] = $slug;
            
            $pageModel->create($_POST);
            redirect('admin/pages');
        }

        $data = [
            'title' => 'Yeni Sayfa Oluştur'
        ];

        $this->adminView('admin/pages/create', $data);
    }

    public function pageEdit($id) {
        $pageModel = $this->model('Page');
        $page = $pageModel->find($id);

        if (!$page) {
            redirect('admin/pages');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST['slug'] = trim($_POST['slug'] ?? '', '/');
            $pageModel->update($id, $_POST);
            redirect('admin/pages');
        }

        $data = [
            'title' => 'Sayfayı Düzenle',
            'page' => $page
        ];

        $this->adminView('admin/pages/edit', $data);
    }

    public function pageDelete($id) {
        $pageModel = $this->model('Page');
        $pageModel->delete($id);
        redirect('admin/pages');
    }

    // ==========================================
    // SİSTEM VE SİTE AYARLARI
    // ==========================================

    public function settings() {
        $envPath = __DIR__ . '/../../.env';
        $message = '';
        $error = '';

        $settingModel = $this->model('Setting');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $type = $_POST['form_type'] ?? '';

            if ($type === 'env') {
                $dbHost = trim($_POST['DB_HOST'] ?? '');
                $dbName = trim($_POST['DB_NAME'] ?? '');
                $dbUser = trim($_POST['DB_USER'] ?? '');
                $dbPass = trim($_POST['DB_PASS'] ?? '');
                $baseUrl = trim($_POST['BASE_URL'] ?? '');

                $envContent = "DB_HOST={$dbHost}\n";
                $envContent .= "DB_NAME={$dbName}\n";
                $envContent .= "DB_USER={$dbUser}\n";
                $envContent .= "DB_PASS={$dbPass}\n";
                $envContent .= "BASE_URL={$baseUrl}\n";

                if (file_put_contents($envPath, $envContent) !== false) {
                    $message = 'Altyapı (.env) ayarları başarıyla güncellendi!';
                } else {
                    $error = '.env dosyası güncellenirken hata oluştu.';
                }
            } elseif ($type === 'general') {
                unset($_POST['form_type']);
                $settingModel->updateBatch($_POST);
                $message = 'Sistem ayarları başarıyla güncellendi!';
            }
        }

        $envData = [];
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                list($key, $value) = explode('=', $line, 2) + ['', ''];
                $envData[trim($key)] = trim($value);
            }
        }

        $dbSettings = $settingModel->getAll();

        $data = [
            'title' => 'Sistem ve Site Ayarları',
            'env' => $envData,
            'dbSettings' => $dbSettings,
            'message' => $message,
            'error' => $error
        ];

        $this->adminView('admin/settings', $data);
    }
}
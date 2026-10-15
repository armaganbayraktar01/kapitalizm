<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class AdminController extends Controller {

    public function __construct() {
        // Bu Controller içerisindeki tüm metodlara girmeden önce admin yetkisi kontrol edilir!
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

    // Menü Yönetim Sayfası ve Listeleme
    public function menus() {
        $menuModel = $this->model('Menu');
        $menus = $menuModel->getAll();

        $data = [
            'title' => 'Sidebar Menü Yönetimi',
            'menus' => $menus
        ];

        $this->adminView('admin/menus/index', $data);
    }

    // Yeni Menü Ekleme Sayfası ve Kayıt İşlemi
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

    // Menü Düzenleme
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

    // Menü Silme
    public function menuDelete($id) {
        $menuModel = $this->model('Menu');
        $menuModel->delete($id);
        redirect('admin/menus');
    }

    // AJAX ile Menü Sıralamasını Güncelleme
    public function menuSort() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
            $menuModel = $this->model('Menu');
            $order = $_POST['order']; // Sıralı ID dizisi

            foreach ($order as $index => $menuId) {
                // Menu modeline bu metodun ekli olduğundan emin olun
                if (method_exists($menuModel, 'updateOrder')) {
                    $menuModel->updateOrder((int)$menuId, $index + 1);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Sıralama güncellendi.']);
            exit;
        }
    }

    // ==========================================
    // SAYFA YÖNETİMİ & SİHİRBAZ
    // ==========================================

    // Sayfaları Listeleme
    public function pages() {
        $pageModel = $this->model('Page');
        $pages = $pageModel->getAll();

        $data = [
            'title' => 'Sayfa Yönetimi',
            'pages' => $pages
        ];

        $this->adminView('admin/pages/index', $data);
    }

    // Yeni Sayfa Ekleme ve Otomatik Controller Üretici (Sihirbaz)
    public function pageCreate() {
        $menuModel = $this->model('Menu');
        $pageModel = $this->model('Page');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $slug = trim($_POST['slug'], '/');
            $title = $_POST['title'];
            $pageType = $_POST['page_type'];
            $icon = !empty($_POST['menu_icon']) ? $_POST['menu_icon'] : 'fas fa-file-alt';
            $addToMenu = isset($_POST['add_to_menu']);
            $menuId = null;

            // 1. EĞER SAYFA TİPİ 'controller' İSE SİHİRBAZ ÇALIŞSIN VE DOSYAYI ÜRETSİN
            if ($pageType === 'controller' && !empty($_POST['controller_name'])) {
                $controllerName = trim($_POST['controller_name']);
                $actionName = !empty($_POST['action_name']) ? trim($_POST['action_name']) : 'index';
                
                $controllerFilePath = __DIR__ . '/' . $controllerName . '.php';
                
                if (!file_exists($controllerFilePath)) {
                    $controllerTemplate = "<?php\n" .
                        "namespace App\Controllers;\n\n" .
                        "use System\Core\Controller;\n\n" .
                        "class " . $controllerName . " extends Controller {\n\n" .
                        "    public function " . $actionName . "() {\n" .
                        "        \$data = [\n" .
                        "            'title' => '" . $title . "'\n" .
                        "        ];\n\n" .
                        "        echo '<div class=\"container mt-4\"><h3>" . $title . " Modülü</h3><p>Bu controller sihirbaz tarafından otomatik üretildi.</p></div>';\n" .
                        "    }\n" .
                        "}\n";

                    file_put_contents($controllerFilePath, $controllerTemplate);
                }
            }

            // 2. OTOMATİK MENÜ EKLEME MANTIĞI
            if ($addToMenu) {
                $allMenus = $menuModel->getAll();
                $maxOrder = !empty($allMenus) ? max(array_column($allMenus, 'menu_order')) + 1 : 1;

                $menuData = [
                    'title' => $title,
                    'url' => $slug,
                    'icon' => $icon,
                    'menu_order' => $maxOrder,
                    'status' => isset($_POST['status']) ? 1 : 0,
                    'target' => '_self'
                ];
                
                $menuModel->create($menuData);
                $db = Database::getInstance();
                $menuId = $db->lastInsertId();
            }

            // 3. SAYFAYI KAYDET
            $_POST['menu_id'] = $menuId;
            $_POST['slug'] = $slug;
            $pageModel->create($_POST);

            redirect('admin/pages');
        }

        $data = [
            'title' => 'Yeni Sayfa Oluştur'
        ];

        $this->adminView('admin/pages/create', $data);
    }

    // Sayfa Düzenleme (Menü Senkronizasyonu ile)
    public function pageEdit($id) {
        $pageModel = $this->model('Page');
        $menuModel = $this->model('Menu');
        
        $page = $pageModel->find($id);
        if (!$page) {
            redirect('admin/pages');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $slug = trim($_POST['slug'], '/');
            $title = $_POST['title'];
            $_POST['slug'] = $slug;

            // 1. Menü Senkronizasyonu
            if (!empty($page['menu_id'])) {
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE menus SET title = :title, url = :url WHERE id = :id");
                $stmt->execute([
                    'title' => $title,
                    'url'   => $slug,
                    'id'    => $page['menu_id']
                ]);
            }

            // 2. Controller Sihirbazı Kontrolü
            if ($_POST['page_type'] === 'controller' && !empty($_POST['controller_name'])) {
                $controllerName = trim($_POST['controller_name']);
                $actionName = !empty($_POST['action_name']) ? trim($_POST['action_name']) : 'index';
                $controllerFilePath = __DIR__ . '/' . $controllerName . '.php';
                
                if (!file_exists($controllerFilePath)) {
                    $controllerTemplate = "<?php\n" .
                        "namespace App\Controllers;\n\n" .
                        "use System\Core\Controller;\n\n" .
                        "class " . $controllerName . " extends Controller {\n\n" .
                        "    public function " . $actionName . "() {\n" .
                        "        \$data = [\n" .
                        "            'title' => '" . $title . "'\n" .
                        "        ];\n\n" .
                        "        echo '<div class=\"container mt-4\"><h3>" . $title . " Modülü</h3><p>Bu controller otomatik üretildi.</p></div>';\n" .
                        "    }\n" .
                        "}\n";
                    file_put_contents($controllerFilePath, $controllerTemplate);
                }
            }

            // 3. Sayfayı Güncelle
            $pageModel->update($id, $_POST);
            redirect('admin/pages');
        }

        $data = [
            'title' => 'Sayfayı Düzenle',
            'page' => $page,
            'menus' => $menuModel->getAll()
        ];

        $this->adminView('admin/pages/edit', $data);
    }

    // Sayfa Silme
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
<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;
use System\Core\ModuleGenerator;

class ModulesController extends Controller {

    public function __construct() {
        admin_check();
    }

    // Modül Listesi (Index)
    public function index() {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM modules ORDER BY menu_order ASC, id DESC");
        $modules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $data = [
            'title'   => 'Modül Yönetimi',
            'modules' => $modules
        ];

        // Güncellediğin view konumu
        $this->adminView('admin/generator/index', $data);
    }

    // Yeni Modül Oluşturma Formu (RAD Sihirbazı)
    public function create() {
        $db = Database::getInstance();
        // Üst Menü seçimi için mevcut modülleri çekelim
        $stmt = $db->query("SELECT * FROM modules WHERE parent_id IS NULL ORDER BY title ASC");
        $parentModules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $data = [
            'title'          => 'Yeni Modül Üret',
            'parent_modules' => $parentModules
        ];

        $this->adminView('admin/generator/create', $data);
    }

    // Modül Düzenleme Formu
    public function edit($id) {
        $db = Database::getInstance();
        
        // Düzenlenecek modülü bul
        $stmt = $db->prepare("SELECT * FROM modules WHERE id = ?");
        $stmt->execute([(int)$id]);
        $module = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$module) {
            redirect('admin/modules');
        }

        // Üst menü seçimi için ana modüller
        $stmtParent = $db->query("SELECT * FROM modules WHERE parent_id IS NULL AND id != " . (int)$id . " ORDER BY title ASC");
        $parentModules = $stmtParent->fetchAll(\PDO::FETCH_ASSOC);

        $data = [
            'title'          => 'Modülü Düzenle: ' . $module['title'],
            'module'         => $module,
            'parent_modules' => $parentModules
        ];

        $this->adminView('admin/generator/edit', $data);
    }

    // Düzenlenen Verileri Kaydetme (Update)
    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $title  = $_POST['title'] ?? '';
            $icon   = $_POST['icon'] ?? 'fas fa-folder';
            $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

            // Veritabanı güncellemesi
            $stmt = $db->prepare("UPDATE modules SET title = :title, icon = :icon, parent_id = :parent_id WHERE id = :id");
            $stmt->execute([
                'title'     => $title,
                'icon'      => $icon,
                'parent_id' => $parentId,
                'id'        => (int)$id
            ]);

            redirect('admin/modules');
        }
        redirect('admin/modules');
    }

    // Formdan gelen verilerle modül üretme tetikleyicisi
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'module_name'   => $_POST['module_name'] ?? '',
                'title'         => $_POST['title'] ?? '',
                'slug'          => $_POST['slug'] ?? '',
                'icon'          => $_POST['icon'] ?? 'fas fa-folder',
                'parent_id'     => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                'template_type' => $_POST['template_type'] ?? 'crud',
                'fields'        => $_POST['fields'] ?? []
            ];

            ModuleGenerator::generate($data);

            redirect('admin/modules');
        }
        redirect('admin/modules/create');
    }

    // Sürükle-bırak sıralama AJAX endpoint'i
    public function sort() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
            $db = Database::getInstance();
            $order = $_POST['order'];

            $stmt = $db->prepare("UPDATE modules SET menu_order = :menu_order WHERE id = :id");
            
            foreach ($order as $index => $id) {
                $stmt->execute([
                    'menu_order' => $index + 1,
                    'id'         => (int)$id
                ]);
            }

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        }
    }

    // Modülü ve tüm dosyalarını silme
    public function delete($id) {
        ModuleGenerator::deleteModule((int)$id);
        redirect('admin/modules');
    }
}
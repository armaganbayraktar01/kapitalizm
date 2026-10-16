<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\ModuleGenerator;
use App\Models\Module;

class GeneratorController extends Controller {

    public function __construct() {
        admin_check();
    }

    public function index() {
        $moduleModel = new Module();
        
        $data = [
            'title' => 'Modül Yönetimi',
            'menus' => $moduleModel->getAll()
        ];

        $this->adminView('admin/generator/index', $data);
    }

    public function create() {
        $moduleModel = new Module();

        $data = [
            'title' => 'Yeni Modül Üret (RAD Sihirbazı)',
            'parentMenus' => $moduleModel->getAll()
        ];

        $this->adminView('admin/generator/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $moduleName = trim($_POST['module_name'] ?? '');
            
            if (!empty($moduleName)) {
                // Fiziksel dosya üretimi
                ModuleGenerator::generate($_POST);
                
                // Veritabanına kayıt (Status ve Target dahil)
                $moduleModel = new Module();
                $allModules = $moduleModel->getAll();
                $maxOrder = !empty($allModules) ? max(array_column($allModules, 'menu_order')) + 1 : 1;

                $slug = !empty($_POST['slug']) ? trim($_POST['slug'], '/') : 'admin/' . strtolower($moduleName);

                $moduleData = [
                    'title' => $_POST['title'],
                    'url' => $slug,
                    'icon' => $_POST['icon'] ?? 'fas fa-folder',
                    'parent_id' => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
                    'menu_order' => $maxOrder,
                    'status' => isset($_POST['status']) ? 1 : 0,
                    'target' => $_POST['target'] ?? '_self'
                ];

                $moduleModel->create($moduleData);
                redirect('admin/modules');
            }
        }
        redirect('admin/modules/create');
    }

    public function edit($id) {
        $moduleModel = new Module();
        $module = $moduleModel->find($id);

        if (!$module) {
            redirect('admin/modules');
        }

        $data = [
            'title' => 'Modülü Düzenle',
            'module' => $module,
            'parentMenus' => $moduleModel->getAll()
        ];

        $this->adminView('admin/generator/edit', $data);
    }

    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $moduleModel = new Module();
            
            $updateData = [
                'title' => $_POST['title'] ?? '',
                'url' => trim($_POST['slug'] ?? '', '/'),
                'icon' => $_POST['icon'] ?? 'fas fa-folder',
                'parent_id' => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
                'status' => isset($_POST['status']) ? 1 : 0, // 0 veya 1 garantisi
                'target' => $_POST['target'] ?? '_self'
            ];

            $moduleModel->update($id, $updateData);
            redirect('admin/modules');
        }
        redirect('admin/modules');
    }

    public function delete($id) {
        $moduleModel = new Module();
        $moduleModel->delete($id);
        redirect('admin/modules');
    }

    public function sort() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
            $moduleModel = new Module();
            $order = $_POST['order'];

            foreach ($order as $index => $menuId) {
                $moduleModel->updateOrder((int)$menuId, $index + 1);
            }

            echo json_encode(['status' => 'success', 'message' => 'Sıralama güncellendi.']);
            exit;
        }
    }
}
<?php
namespace System\Core;

class ModuleGenerator {

    /**
     * Yeni bir modül iskeleti üretir (Controller, Model, Views ve Menu kaydı)
     */
    public static function generate(array $data): bool {
        // Gelen verileri normalize edelim
        $rawName = trim($data['module_name']); // Örn: CreditCard veya Budget
        $className = ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', $rawName));
        $lowerName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)); // credit_card
        $slug = trim($data['slug'] ?? 'admin/' . $lowerName, '/');
        $title = $data['title'] ?? $className;
        $icon = $data['icon'] ?? 'fas fa-folder';
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;

        // 1. Controller Dosyasını Üret
        self::createController($className, $lowerName, $title);

        // 2. Model Dosyasını Üret
        self::createModel($className, $lowerName);

        // 3. View Klasörünü ve Şablonlarını Üret
        self::createViews($lowerName, $title);

        // 4. Veritabanına Menü Kaydını Ekle
        self::createMenuRecord($title, $slug, $icon, $parentId);

        return true;
    }

    protected static function createController(string $className, string $lowerName, string $title) {
        $controllerPath = __DIR__ . '/../../app/Controllers/' . $className . 'Controller.php';

        if (!file_exists($controllerPath)) {
            $content = "<?php\n" .
                "namespace App\Controllers;\n\n" .
                "use System\Core\Controller;\n\n" .
                "class " . $className . "Controller extends Controller {\n\n" .
                "    public function __construct() {\n" .
                "        admin_check();\n" .
                "    }\n\n" .
                "    public function index() {\n" .
                "        \$model = \$this->model('" . $className . "');\n" .
                "        \$data = [\n" .
                "            'title' => '" . $title . " Listesi',\n" .
                "            'items' => \$model->getAll()\n" .
                "        ];\n" .
                "        \$this->adminView('admin/" . $lowerName . "/index', \$data);\n" .
                "    }\n\n" .
                "    public function create() {\n" .
                "        if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n" .
                "            \$model = \$this->model('" . $className . "');\n" .
                "            \$model->create(\$_POST);\n" .
                "            redirect('admin/" . $lowerName . "');\n" .
                "        }\n" .
                "        \$data = ['title' => 'Yeni " . $title . " Ekle'];\n" .
                "        \$this->adminView('admin/" . $lowerName . "/create', \$data);\n" .
                "    }\n\n" .
                "    public function edit(\$id) {\n" .
                "        \$model = \$this->model('" . $className . "');\n" .
                "        \$item = \$model->find(\$id);\n" .
                "        if (!\$item) { redirect('admin/" . $lowerName . "'); }\n\n" .
                "        if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n" .
                "            \$model->update(\$id, \$_POST);\n" .
                "            redirect('admin/" . $lowerName . "');\n" .
                "        }\n" .
                "        \$data = ['title' => '" . $title . " Düzenle', 'item' => \$item];\n" .
                "        \$this->adminView('admin/" . $lowerName . "/edit', \$data);\n" .
                "    }\n\n" .
                "    public function delete(\$id) {\n" .
                "        \$model = \$this->model('" . $className . "');\n" .
                "        \$model->delete(\$id);\n" .
                "        redirect('admin/" . $lowerName . "');\n" .
                "    }\n" .
                "}\n";

            file_put_contents($controllerPath, $content);
        }
    }

    protected static function createModel(string $className, string $lowerName) {
        $modelPath = __DIR__ . '/../../app/Models/' . $className . '.php';
        $tableName = $lowerName . 's'; // Örn: credit_cards

        if (!file_exists($modelPath)) {
            $content = "<?php\n" .
                "namespace App\Models;\n\n" .
                "use System\Core\Database;\n\n" .
                "class " . $className . " {\n" .
                "    protected \$db;\n\n" .
                "    public function __construct() {\n" .
                "        \$this->db = Database::getInstance();\n" .
                "    }\n\n" .
                "    public function getAll() {\n" .
                "        \$stmt = \$this->db->query(\"SELECT * FROM " . $tableName . " ORDER BY id DESC\");\n" .
                "        return \$stmt->fetchAll(\\PDO::FETCH_ASSOC);\n" .
                "    }\n\n" .
                "    public function find(int \$id) {\n" .
                "        \$stmt = \$this->db->prepare(\"SELECT * FROM " . $tableName . " WHERE id = :id\");\n" .
                "        \$stmt->execute(['id' => \$id]);\n" .
                "        return \$stmt->fetch(\\PDO::FETCH_ASSOC);\n" .
                "    }\n\n" .
                "    public function create(array \$data): bool {\n" .
                "        // Varsayılan dinamik alan ekleme mantığı\n" .
                "        unset(\$_POST['form_type']);\n" .
                "        \$keys = array_keys(\$data);\n" .
                "        \$fields = implode(', ', \$keys);\n" .
                "        \$placeholders = ':' . implode(', :', \$keys);\n\n" .
                "        \$stmt = \$this->db->prepare(\"INSERT INTO " . $tableName . " (\$fields) VALUES (\$placeholders)\");\n" .
                "        return \$stmt->execute(\$data);\n" .
                "    }\n\n" .
                "    public function update(int \$id, array \$data): bool {\n" .
                "        unset(\$_POST['form_type']);\n" .
                "        \$fields = '';\n" .
                "        foreach (\$data as \$key => \$value) {\n" .
                "            \$fields .= \"\$key = :\$key, \";\n" .
                "        }\n" .
                "        \$fields = rtrim(\$fields, ', ');\n\n" .
                "        \$data['id'] = \$id;\n" .
                "        \$stmt = \$this->db->prepare(\"UPDATE " . $tableName . " SET \$fields WHERE id = :id\");\n" .
                "        return \$stmt->execute(\$data);\n" .
                "    }\n\n" .
                "    public function delete(int \$id): bool {\n" .
                "        \$stmt = \$this->db->prepare(\"DELETE FROM " . $tableName . " WHERE id = :id\");\n" .
                "        return \$stmt->execute(['id' => \$id]);\n" .
                "    }\n" .
                "}\n";

            file_put_contents($modelPath, $content);
        }
    }

    protected static function createViews(string $lowerName, string $title) {
        $viewDir = __DIR__ . '/../../app/Views/admin/' . $lowerName;
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }

        // 1. index.php (Listeleme)
        $indexPath = $viewDir . '/index.php';
        if (!file_exists($indexPath)) {
            $indexContent = "<div class=\"d-flex justify-content-between align-items-center mb-4\">\n" .
                "    <h2>" . $title . "</h2>\n" .
                "    <a href=\"<?= base_url('admin/" . $lowerName . "/create') ?>\" class=\"btn btn-primary\"><i class=\"fas fa-plus me-1\"></i> Yeni Ekle</a>\n" .
                "</div>\n" .
                "<div class=\"card shadow-sm border-0\">\n" .
                "    <div class=\"card-body\">\n" .
                "        <table class=\"table table-hover align-middle\">\n" .
                "            <thead class=\"table-dark\">\n" .
                "                <tr>\n" .
                "                    <th>#ID</th>\n" .
                "                    <th>Başlık / Bilgi</th>\n" .
                "                    <th class=\"text-end\">İşlemler</th>\n" .
                "                </tr>\n" .
                "            </thead>\n" .
                "            <tbody>\n" .
                "                <?php if (!empty(\$items)): foreach (\$items as \$item): ?>\n" .
                "                <tr>\n" .
                "                    <td><?= \$item['id'] ?></td>\n" .
                "                    <td><?= htmlspecialchars(\$item['title'] ?? (\$item['name'] ?? 'Kayıt #' . \$item['id'])) ?></td>\n" .
                "                    <td class=\"text-end\">\n" .
                "                        <a href=\"<?= base_url('admin/" . $lowerName . "/edit/' . \$item['id']) ?>\" class=\"btn btn-sm btn-warning text-white\"><i class=\"fas fa-edit\"></i></a>\n" .
                "                        <a href=\"<?= base_url('admin/" . $lowerName . "/delete/' . \$item['id']) ?>\" class=\"btn btn-sm btn-danger\" onclick=\"return confirm('Bu kaydı silmek istediğinize emin misiniz?');\"><i class=\"fas fa-trash\"></i></a>\n" .
                "                    </td>\n" .
                "                </tr>\n" .
                "                <?php endforeach; else: ?>\n" .
                "                <tr><td colspan=\"3\" class=\"text-center text-muted py-4\">Henüz kayıt bulunmuyor.</td></tr>\n" .
                "                <?php endif; ?>\n" .
                "            </tbody>\n" .
                "        </table>\n" .
                "    </div>\n" .
                "</div>\n";
            file_put_contents($indexPath, $indexContent);
        }

        // 2. create.php (Form)
        $createPath = $viewDir . '/create.php';
        if (!file_exists($createPath)) {
            $createContent = "<div class=\"card shadow-sm border-0 col-md-8 mx-auto\">\n" .
                "    <div class=\"card-header bg-white py-3\">\n" .
                "        <h4 class=\"m-0 fw-bold\">Yeni " . $title . " Ekle</h4>\n" .
                "    </div>\n" .
                "    <div class=\"card-body\">\n" .
                "        <form action=\"\" method=\"POST\">\n" .
                "            <div class=\"mb-3\">\n" .
                "                <label class=\"form-label fw-semibold\">Başlık / Ad</label>\n" .
                "                <input type=\"text\" name=\"title\" class=\"form-control\" required>\n" .
                "            </div>\n" .
                "            <div class=\"d-flex justify-content-between\">\n" .
                "                <a href=\"<?= base_url('admin/" . $lowerName . "') ?>\" class=\"btn btn-secondary\">Geri Dön</a>\n" .
                "                <button type=\"submit\" class=\"btn btn-success\">Kaydet</button>\n" .
                "            </div>\n" .
                "        </form>\n" .
                "    </div>\n" .
                "</div>\n";
            file_put_contents($createPath, $createContent);
        }

        // 3. edit.php (Düzenleme Formu)
        $editPath = $viewDir . '/edit.php';
        if (!file_exists($editPath)) {
            $editContent = "<div class=\"card shadow-sm border-0 col-md-8 mx-auto\">\n" .
                "    <div class=\"card-header bg-white py-3\">\n" .
                "        <h4 class=\"m-0 fw-bold\">" . $title . " Düzenle</h4>\n" .
                "    </div>\n" .
                "    <div class=\"card-body\">\n" .
                "        <form action=\"\" method=\"POST\">\n" .
                "            <div class=\"mb-3\">\n" .
                "                <label class=\"form-label fw-semibold\">Başlık / Ad</label>\n" .
                "                <input type=\"text\" name=\"title\" class=\"form-control\" value=\"<?= htmlspecialchars(\$item['title'] ?? (\$item['name'] ?? '')) ?>\" required>\n" .
                "            </div>\n" .
                "            <div class=\"d-flex justify-content-between\">\n" .
                "                <a href=\"<?= base_url('admin/" . $lowerName . "') ?>\" class=\"btn btn-secondary\">Geri Dön</a>\n" .
                "                <button type=\"submit\" class=\"btn btn-primary\">Güncelle</button>\n" .
                "            </div>\n" .
                "        </form>\n" .
                "    </div>\n" .
                "</div>\n";
            file_put_contents($editPath, $editContent);
        }
    }

    protected static function createMenuRecord(string $title, string $slug, string $icon, ?int $parentId) {
        $db = Database::getInstance();
        
        // Aynı URL ile kayıt var mı kontrol et
        $check = $db->prepare("SELECT id FROM menus WHERE url = :url");
        $check->execute(['url' => $slug]);
        if ($check->fetch()) {
            return; // Zaten varsa tekrar ekleme
        }

        // Sıralama tespiti
        $maxOrderStmt = $db->query("SELECT MAX(menu_order) as max_order FROM menus");
        $maxOrder = $maxOrderStmt->fetch(\PDO::FETCH_ASSOC)['max_order'] ?? 0;

        $stmt = $db->prepare("INSERT INTO menus (parent_id, title, url, icon, menu_order, status, target) VALUES (:parent_id, :title, :url, :icon, :menu_order, 1, '_self')");
        $stmt->execute([
            'parent_id'  => $parentId,
            'title'      => $title,
            'url'        => $slug,
            'icon'       => $icon,
            'menu_order' => $maxOrder + 1
        ]);
    }
}
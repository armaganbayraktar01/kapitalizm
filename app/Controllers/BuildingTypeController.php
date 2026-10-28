<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class BuildingTypeController extends Controller {

    private string $uploadDir = __DIR__ . '/../../public/uploads/buildings/';

    private function slugify(string $text): string {
        $tr = ['ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç', 'ı', 'İ'];
        $en = ['s', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c', 'i', 'i'];
        $text = str_replace($tr, $en, $text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }

    public function index() {
        $db = Database::getInstance();
        
        $buildings = $db->query("SELECT bt.* FROM building_types bt ORDER BY bt.id DESC")->fetchAll(\PDO::FETCH_ASSOC);

        // Her bina için girdi ve çıktıları detaylıca çekelim
        foreach ($buildings as &$b) {
            $b['inputs'] = $db->prepare("SELECT bi.*, p.name as product_name FROM building_inputs bi JOIN products p ON bi.product_id = p.id WHERE bi.building_type_id = ?");
            $b['inputs']->execute([$b['id']]);
            $b['inputs'] = $b['inputs']->fetchAll(\PDO::FETCH_ASSOC);

            $b['outputs'] = $db->prepare("SELECT bo.*, p.name as product_name FROM building_outputs bo JOIN products p ON bo.product_id = p.id WHERE bo.building_type_id = ?");
            $b['outputs']->execute([$b['id']]);
            $b['outputs'] = $b['outputs']->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->adminView('admin/buildings/index', ['buildings' => $buildings]);
    }

    public function create() {
        $db = Database::getInstance();
        $products = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->adminView('admin/buildings/create', ['products' => $products]);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $name              = trim($_POST['name'] ?? '');
            $category          = trim($_POST['category'] ?? 'factory');
            $complexityType    = trim($_POST['complexity_type'] ?? 'semi');
            $unitType          = trim($_POST['unit_type'] ?? 'Adet');
            $baseCost          = (float)($_POST['base_cost'] ?? 50000.00);
            $storageType       = trim($_POST['storage_type'] ?? 'raw');
            
            $imageName = 'default.png';
            if (!empty($name)) {
                $slugName = $this->slugify($name);
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    if (!is_dir($this->uploadDir)) {
                        mkdir($this->uploadDir, 0755, true);
                    }
                    $fileTmpPath   = $_FILES['image']['tmp_name'];
                    $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $imageName = $slugName . '.' . $fileExtension;
                        move_uploaded_file($fileTmpPath, $this->uploadDir . $imageName);
                    }
                }
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("INSERT INTO building_types 
                    (name, category, complexity_type, unit_type, base_cost, storage_type, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $name, $category, $complexityType, $unitType, $baseCost, $storageType, $imageName
                ]);

                $buildingTypeId = $db->lastInsertId();

                // Çoklu Girdileri Kaydet
                if (!empty($_POST['inputs']) && is_array($_POST['inputs'])) {
                    $inStmt = $db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
                    foreach ($_POST['inputs'] as $row) {
                        $prodId = (int)($row['product_id'] ?? 0);
                        $amount = (float)($row['amount'] ?? 0);
                        if ($prodId > 0 && $amount > 0) {
                            $inStmt->execute([$buildingTypeId, $prodId, $amount]);
                        }
                    }
                }

                // Çoklu Çıktıları Kaydet
                if (!empty($_POST['outputs']) && is_array($_POST['outputs'])) {
                    $outStmt = $db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
                    foreach ($_POST['outputs'] as $row) {
                        $prodId = (int)($row['product_id'] ?? 0);
                        $amount = (float)($row['amount'] ?? 0);
                        if ($prodId > 0 && $amount > 0) {
                            $outStmt->execute([$buildingTypeId, $prodId, $amount]);
                        }
                    }
                }

                // Level 1 Matris Kaydı
                $matrixStmt = $db->prepare("INSERT INTO matrix_level_building 
                    (building_type_id, level, production_rate, consumption_amount, upgrade_cost, maintenance_cost, energy_consumption, water_consumption, labor_required, required_xp, wear_rate) 
                    VALUES (?, 1, 8.00, 0.00, ?, 100.00, 50.00, 10.00, 10, 0, 2.00)");
                $matrixStmt->execute([$buildingTypeId, $baseCost]);

                $db->commit();
                redirect('admin/buildingtype');
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                die("Bina Tipi Kayıt Hatası: " . $e->getMessage());
            }
        }
    }   

    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $buildingId = (int)$id;

            $name              = trim($_POST['name'] ?? '');
            $category          = trim($_POST['category'] ?? 'factory');
            $complexityType    = trim($_POST['complexity_type'] ?? 'semi');
            $unitType          = trim($_POST['unit_type'] ?? 'Adet');
            $baseCost          = (float)($_POST['base_cost'] ?? 50000.00);
            $storageType       = trim($_POST['storage_type'] ?? 'raw');

            try {
                $db->beginTransaction();

                // Girdileri Güncelle
                $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingId]);
                if (!empty($_POST['inputs']) && is_array($_POST['inputs'])) {
                    $inStmt = $db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
                    foreach ($_POST['inputs'] as $row) {
                        $prodId = (int)($row['product_id'] ?? 0);
                        $amount = (float)($row['amount'] ?? 0);
                        if ($prodId > 0 && $amount > 0) {
                            $inStmt->execute([$buildingId, $prodId, $amount]);
                        }
                    }
                }

                // Çıktıları Güncelle
                $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingId]);
                if (!empty($_POST['outputs']) && is_array($_POST['outputs'])) {
                    $outStmt = $db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
                    foreach ($_POST['outputs'] as $row) {
                        $prodId = (int)($row['product_id'] ?? 0);
                        $amount = (float)($row['amount'] ?? 0);
                        if ($prodId > 0 && $amount > 0) {
                            $outStmt->execute([$buildingId, $prodId, $amount]);
                        }
                    }
                }

                // Seviye Matrisi Güncellemeleri
                if (isset($_POST['levels']) && is_array($_POST['levels'])) {
                    foreach ($_POST['levels'] as $lvl => $data) {
                        $lvlNum = (int)$lvl;
                        $lProd = (float)($data['production_rate'] ?? 8);
                        $lCons = (float)($data['consumption_amount'] ?? 0);
                        $lCost = (float)($data['upgrade_cost'] ?? 0);
                        $lMaint = (float)($data['maintenance_cost'] ?? 0);
                        $lEnergy = (float)($data['energy_consumption'] ?? 50);
                        $lWater = (float)($data['water_consumption'] ?? 10);
                        $lLabor = (int)($data['labor_required'] ?? 10);
                        $lXp = (int)($data['required_xp'] ?? 0);
                        $lWear = (float)($data['wear_rate'] ?? 2.0);

                        $check = $db->prepare("SELECT id FROM matrix_level_building WHERE building_type_id = ? AND level = ?");
                        $check->execute([$buildingId, $lvlNum]);
                        if ($check->fetch()) {
                            $upd = $db->prepare("UPDATE matrix_level_building SET production_rate = ?, consumption_amount = ?, upgrade_cost = ?, maintenance_cost = ?, energy_consumption = ?, water_consumption = ?, labor_required = ?, required_xp = ?, wear_rate = ? WHERE building_type_id = ? AND level = ?");
                            $upd->execute([$lProd, $lCons, $lCost, $lMaint, $lEnergy, $lWater, $lLabor, $lXp, $lWear, $buildingId, $lvlNum]);
                        } else {
                            $ins = $db->prepare("INSERT INTO matrix_level_building (building_type_id, level, production_rate, consumption_amount, upgrade_cost, maintenance_cost, energy_consumption, water_consumption, labor_required, required_xp, wear_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $ins->execute([$buildingId, $lvlNum, $lProd, $lCons, $lCost, $lMaint, $lEnergy, $lWater, $lLabor, $lXp, $lWear]);
                        }
                    }
                }

                // Görsel ve Temel Bilgiler
                $stmtOld = $db->prepare("SELECT image FROM building_types WHERE id = ?");
                $stmtOld->execute([$buildingId]);
                $oldBuilding = $stmtOld->fetch(\PDO::FETCH_ASSOC);
                $imageName = $oldBuilding['image'] ?? 'default.png';

                if (!empty($name)) {
                    $slugName = $this->slugify($name);
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        if (!is_dir($this->uploadDir)) {
                            mkdir($this->uploadDir, 0755, true);
                        }
                        $fileTmpPath   = $_FILES['image']['tmp_name'];
                        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                        if (in_array($fileExtension, $allowedExtensions)) {
                            $newImageName = $slugName . '.' . $fileExtension;
                            if ($imageName !== 'default.png' && $imageName !== $newImageName && file_exists($this->uploadDir . $imageName)) {
                                @unlink($this->uploadDir . $imageName);
                            }
                            if (move_uploaded_file($fileTmpPath, $this->uploadDir . $newImageName)) {
                                $imageName = $newImageName;
                            }
                        }
                    } elseif ($imageName !== 'default.png') {
                        $fileExtension = pathinfo($imageName, PATHINFO_EXTENSION);
                        $newImageName = $slugName . '.' . $fileExtension;
                        if ($imageName !== $newImageName && file_exists($this->uploadDir . $imageName)) {
                            rename($this->uploadDir . $imageName, $this->uploadDir . $newImageName);
                            $imageName = $newImageName;
                        }
                    }
                }

                $stmt = $db->prepare("UPDATE building_types SET 
                    name = ?, category = ?, complexity_type = ?, 
                    unit_type = ?, base_cost = ?, storage_type = ?, image = ? 
                    WHERE id = ?");
                
                $stmt->execute([
                    $name, $category, $complexityType, $unitType, $baseCost, $storageType, $imageName, $buildingId
                ]);

                $db->commit();
                redirect('admin/buildingtype');
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                die("Güncelleme Hatası: " . $e->getMessage());
            }
        }
    }

    public function edit($id) {
        $db = Database::getInstance();
        $buildingId = (int)$id;

        $stmt = $db->prepare("SELECT * FROM building_types WHERE id = ?");
        $stmt->execute([$buildingId]);
        $building = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$building) {
            redirect('admin/buildingtype');
        }

        // Mevcut Girdiler ve Çıktılar
        $inputs = $db->prepare("SELECT * FROM building_inputs WHERE building_type_id = ?");
        $inputs->execute([$buildingId]);
        $buildingInputs = $inputs->fetchAll(\PDO::FETCH_ASSOC);

        $outputs = $db->prepare("SELECT * FROM building_outputs WHERE building_type_id = ?");
        $outputs->execute([$buildingId]);
        $buildingOutputs = $outputs->fetchAll(\PDO::FETCH_ASSOC);

        // Seviye Matrisi
        $matrixStmt = $db->prepare("SELECT * FROM matrix_level_building WHERE building_type_id = ? ORDER BY level ASC");
        $matrixStmt->execute([$buildingId]);
        $levels = $matrixStmt->fetchAll(\PDO::FETCH_ASSOC);

        $products = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->adminView('admin/buildings/edit', [
            'building' => $building,
            'inputs' => $buildingInputs,
            'outputs' => $buildingOutputs,
            'levels' => $levels,
            'products' => $products
        ]);
    }


    public function delete($id) {
        $db = Database::getInstance();
        $buildingId = (int)$id;

        $stmt = $db->prepare("SELECT image FROM building_types WHERE id = ?");
        $stmt->execute([$buildingId]);
        $building = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($building && $building['image'] !== 'default.png') {
            $filePath = $this->uploadDir . $building['image'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingId]);
        $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingId]);
        $db->prepare("DELETE FROM matrix_level_building WHERE building_type_id = ?")->execute([$buildingId]);
        $db->prepare("DELETE FROM building_types WHERE id = ?")->execute([$buildingId]);
        
        redirect('admin/buildingtype');
    }
}
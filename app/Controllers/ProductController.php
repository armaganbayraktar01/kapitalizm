<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class ProductController extends Controller {

    private string $uploadDir = __DIR__ . '/../../public/uploads/products/';

    public function index() {
        $db = Database::getInstance();
        
        $products = $db->query("SELECT p.*, bo.building_type_id, bo.amount as output_amount, bo.production_rate, bt.name as building_type_name 
                                FROM products p 
                                LEFT JOIN building_outputs bo ON p.id = bo.product_id 
                                LEFT JOIN building_types bt ON bo.building_type_id = bt.id 
                                ORDER BY p.id DESC")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($products as &$product) {
            $product['inputs'] = [];
            if (!empty($product['building_type_id'])) {
                $inputStmt = $db->prepare("SELECT bi.*, p.name as input_product_name, p.type as input_product_type 
                                          FROM building_inputs bi 
                                          JOIN products p ON bi.product_id = p.id 
                                          WHERE bi.building_type_id = ?");
                $inputStmt->execute([$product['building_type_id']]);
                $product['inputs'] = $inputStmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        }

        $this->adminView('admin/products/index', ['products' => $products]);
    }

    public function create() {
        $db = Database::getInstance();
        $allProducts = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $buildingTypes = $db->query("SELECT * FROM building_types ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->adminView('admin/products/create', [
            'allProducts' => $allProducts,
            $buildingTypesKey = 'buildingTypes' => $buildingTypes
        ]);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();

            $name           = trim($_POST['name'] ?? '');
            $type           = trim($_POST['type'] ?? 'raw');
            $basePrice      = (float)($_POST['base_price'] ?? 10.00);
            $unitCost       = (float)($_POST['unit_cost'] ?? 100.00);
            $prodSec        = (int)($_POST['production_seconds'] ?? 600); 
            $unitType       = trim($_POST['unit_type'] ?? 'Adet');
            $buildingTypeId = !empty($_POST['building_type_id']) ? (int)$_POST['building_type_id'] : null;
            
            // BİRLEŞTİRİLEN ALAN: Döngü Başı Miktar (amount) ve Rate
            $outputAmount   = (float)($_POST['output_amount'] ?? 1.0);
            $productionRate = (float)($_POST['production_rate'] ?? 1.0);
            $inputsData     = $_POST['building_inputs'] ?? [];

            $imageName = 'default.png';

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                if (!is_dir($this->uploadDir)) {
                    mkdir($this->uploadDir, 0755, true);
                }

                $fileTmpPath   = $_FILES['image']['tmp_name'];
                $fileName      = $_FILES['image']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = uniqid('prod_') . '.' . $fileExtension;
                    if (move_uploaded_file($fileTmpPath, $this->uploadDir . $newFileName)) {
                        $imageName = $newFileName;
                    }
                }
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("INSERT INTO products (name, type, base_price, unit_cost, production_seconds, unit_type, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $type, $basePrice, $unitCost, $prodSec, $unitType, $imageName]);
                $productId = $db->lastInsertId();

                if ($buildingTypeId) {
                    $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingTypeId]);
                    
                    $outStmt = $db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount, production_rate) VALUES (?, ?, ?, ?)");
                    $outStmt->execute([$buildingTypeId, $productId, $outputAmount, $productionRate]);

                    $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingTypeId]);
                    
                    if (!empty($inputsData)) {
                        $inStmt = $db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
                        foreach ($inputsData as $reqProductId => $reqAmount) {
                            if ((float)$reqAmount > 0) {
                                $inStmt->execute([$buildingTypeId, (int)$reqProductId, (float)$reqAmount]);
                            }
                        }
                    }
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                error_log("Ürün Ekleme Hatası: " . $e->getMessage());
            }

            redirect('admin/product');
        }
    }

    public function edit($id) {
        $db = Database::getInstance();
        $productId = (int)$id;

        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$product) {
            redirect('admin/product');
        }

        $outStmt = $db->prepare("SELECT * FROM building_outputs WHERE product_id = ?");
        $outStmt->execute([$productId]);
        $buildingOutput = $outStmt->fetch(\PDO::FETCH_ASSOC);

        $buildingInputsMap = [];
        $assignedBuildingTypeId = $buildingOutput['building_type_id'] ?? null;
        $outputAmount = $buildingOutput['amount'] ?? 1.00;
        $baseProductionRate = isset($buildingOutput['production_rate']) ? (float)$buildingOutput['production_rate'] : 1.0;

        if ($assignedBuildingTypeId) {
            $inStmt = $db->prepare("SELECT product_id, amount FROM building_inputs WHERE building_type_id = ?");
            $inStmt->execute([$assignedBuildingTypeId]);
            foreach ($inStmt->fetchAll(\PDO::FETCH_ASSOC) as $input) {
                $buildingInputsMap[$input['product_id']] = (float)$input['amount'];
            }
        }

        $allProducts = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $buildingTypes = $db->query("SELECT * FROM building_types ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->adminView('admin/products/edit', [
            'product' => $product,
            'buildingOutput' => $buildingOutput,
            'outputAmount' => $outputAmount,
            'baseProductionRate' => $baseProductionRate,
            'assignedBuildingTypeId' => $assignedBuildingTypeId,
            'buildingInputsMap' => $buildingInputsMap,
            'allProducts' => $allProducts,
            'buildingTypes' => $buildingTypes
        ]);
    }

    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $productId = (int)$id;

            $name           = trim($_POST['name'] ?? '');
            $type           = trim($_POST['type'] ?? 'raw');
            $basePrice      = (float)($_POST['base_price'] ?? 10.00);
            $unitCost       = (float)($_POST['unit_cost'] ?? 100.00);
            $prodSec        = (int)($_POST['production_seconds'] ?? 600); 
            $unitType       = trim($_POST['unit_type'] ?? 'Adet');
            $buildingTypeId = !empty($_POST['building_type_id']) ? (int)$_POST['building_type_id'] : null;
            
            $outputAmount   = (float)($_POST['output_amount'] ?? 1.0);
            $productionRate = (float)($_POST['production_rate'] ?? 1.0);
            $inputsData     = $_POST['building_inputs'] ?? [];

            $stmtOld = $db->prepare("SELECT image FROM products WHERE id = ?");
            $stmtOld->execute([$productId]);
            $oldProduct = $stmtOld->fetch(\PDO::FETCH_ASSOC);
            $imageName = $oldProduct['image'] ?? 'default.png';

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                if (!is_dir($this->uploadDir)) {
                    mkdir($this->uploadDir, 0755, true);
                }

                $fileTmpPath   = $_FILES['image']['tmp_name'];
                $fileName      = $_FILES['image']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = uniqid('prod_') . '.' . $fileExtension;
                    if (move_uploaded_file($fileTmpPath, $this->uploadDir . $newFileName)) {
                        if ($imageName !== 'default.png' && file_exists($this->uploadDir . $imageName)) {
                            unlink($this->uploadDir . $imageName);
                        }
                        $imageName = $newFileName;
                    }
                }
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("UPDATE products SET name = ?, type = ?, base_price = ?, unit_cost = ?, production_seconds = ?, unit_type = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $type, $basePrice, $unitCost, $prodSec, $unitType, $imageName, $productId]);

                $db->prepare("DELETE FROM building_outputs WHERE product_id = ?")->execute([$productId]);

                if ($buildingTypeId) {
                    $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingTypeId]);

                    $outStmt = $db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount, production_rate) VALUES (?, ?, ?, ?)");
                    $outStmt->execute([$buildingTypeId, $productId, $outputAmount, $productionRate]);

                    $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingTypeId]);

                    if (!empty($inputsData)) {
                        $inStmt = $db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
                        foreach ($inputsData as $reqProductId => $reqAmount) {
                            if ((float)$reqAmount > 0) {
                                $inStmt->execute([$buildingTypeId, (int)$reqProductId, (float)$reqAmount]);
                            }
                        }
                    }
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                error_log("Ürün Güncelleme Hatası: " . $e->getMessage());
            }

            redirect('admin/product');
        }
    }

    public function delete($id) {
        $db = Database::getInstance();
        $productId = (int)$id;

        $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($product && $product['image'] !== 'default.png') {
            $filePath = $this->uploadDir . $product['image'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $db->prepare("DELETE FROM building_outputs WHERE product_id = ?")->execute([$productId]);
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);

        redirect('admin/product');
    }
}
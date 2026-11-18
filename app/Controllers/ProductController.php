<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class ProductController extends Controller {

    private string $uploadDir = __DIR__ . '/../../public/uploads/products/';

    /**
     * Ürünler Listesi ve Bağlı Bina Çıktı/Girdileri
     */
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
                $product['inputs'] = $this->fetchBuildingInputs($db, $product['building_type_id']);
            }
        }

        $this->adminView('admin/products/index', ['products' => $products]);
    }

    /**
     * Yeni Ürün Ekleme Sayfası
     */
    public function create() {
        $db = Database::getInstance();
        
        $this->adminView('admin/products/create', [
            'allProducts'   => $this->fetchAllProducts($db),
            'buildingTypes' => $this->fetchBuildingTypes($db)
        ]);
    }

    /**
     * Yeni Ürün Verisini Kaydetme
     */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $data = $this->getRequestData();
            $imageName = $this->handleImageUpload($_FILES['image'] ?? null);

            try {
                $db->beginTransaction();

                $productId = $this->insertProduct($db, $data, $imageName);

                if ($data['building_type_id']) {
                    $this->saveBuildingRelations($db, $data['building_type_id'], $productId, $data['output_amount'], $data['production_rate'], $data['inputs']);
                }

                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("Ürün Ekleme Hatası: " . $e->getMessage());
            }

            redirect('admin/product');
        }
    }

    /**
     * Ürün Düzenleme Sayfası
     */
    public function edit($id) {
        $db = Database::getInstance();
        $productId = (int)$id;

        $product = $this->fetchProductById($db, $productId);
        if (!$product) {
            redirect('admin/product');
        }

        $buildingOutput = $this->fetchBuildingOutputByProduct($db, $productId);
        $assignedBuildingTypeId = $buildingOutput['building_type_id'] ?? null;
        
        $buildingInputsMap = [];
        if ($assignedBuildingTypeId) {
            $buildingInputsMap = $this->fetchBuildingInputsMap($db, $assignedBuildingTypeId);
        }

        $this->adminView('admin/products/edit', [
            'product'                => $product,
            'buildingOutput'         => $buildingOutput,
            'outputAmount'           => $buildingOutput['amount'] ?? 1.00,
            'baseProductionRate'     => isset($buildingOutput['production_rate']) ? (float)$buildingOutput['production_rate'] : 1.0,
            'assignedBuildingTypeId' => $assignedBuildingTypeId,
            'buildingInputsMap'      => $buildingInputsMap,
            'allProducts'            => $this->fetchAllProducts($db),
            'buildingTypes'          => $this->fetchBuildingTypes($db)
        ]);
    }

    /**
     * Ürün Güncelleme İşlemi
     */
    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $productId = (int)$id;
            $data = $this->getRequestData();

            $oldProduct = $this->fetchProductById($db, $productId);
            $imageName = $this->handleImageUpload($_FILES['image'] ?? null, $oldProduct['image'] ?? 'default.png');

            try {
                $db->beginTransaction();

                $this->updateProductRecord($db, $productId, $data, $imageName);

                $db->prepare("DELETE FROM building_outputs WHERE product_id = ?")->execute([$productId]);

                if ($data['building_type_id']) {
                    $this->saveBuildingRelations($db, $data['building_type_id'], $productId, $data['output_amount'], $data['production_rate'], $data['inputs']);
                }

                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("Ürün Güncelleme Hatası: " . $e->getMessage());
            }

            redirect('admin/product');
        }
    }

    /**
     * Ürün Silme İşlemi
     */
    public function delete($id) {
        $db = Database::getInstance();
        $productId = (int)$id;

        $product = $this->fetchProductById($db, $productId);
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

    /* ==========================================================
     *  YARDIMCI ÖZEL METOTLAR (DRY & MODÜLER YAPI)
     * ========================================================== */

    private function getRequestData(): array {
        return [
            'name'              => trim($_POST['name'] ?? ''),
            'type'              => trim($_POST['type'] ?? 'raw'),
            'base_price'        => (float)($_POST['base_price'] ?? 10.00),
            'unit_cost'         => (float)($_POST['unit_cost'] ?? 100.00),
            'production_seconds'=> (int)($_POST['production_seconds'] ?? 600),
            'unit_type'         => trim($_POST['unit_type'] ?? 'Adet'),
            'building_type_id'  => !empty($_POST['building_type_id']) ? (int)$_POST['building_type_id'] : null,
            'output_amount'     => (float)($_POST['output_amount'] ?? 1.0),
            'production_rate'   => (float)($_POST['production_rate'] ?? 1.0),
            'inputs'            => $_POST['building_inputs'] ?? []
        ];
    }

    private function handleImageUpload(?array $file, string $currentImage = 'default.png'): string {
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = uniqid('prod_') . '.' . $fileExtension;
                if (move_uploaded_file($file['tmp_name'], $this->uploadDir . $newFileName)) {
                    if ($currentImage !== 'default.png' && file_exists($this->uploadDir . $currentImage)) {
                        unlink($this->uploadDir . $currentImage);
                    }
                    return $newFileName;
                }
            }
        }
        return $currentImage;
    }

    private function fetchAllProducts($db): array {
        return $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchBuildingTypes($db): array {
        return $db->query("SELECT * FROM building_types ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchProductById($db, int $productId) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function fetchBuildingInputs($db, int $buildingTypeId): array {
        $stmt = $db->prepare("SELECT bi.*, p.name as input_product_name, p.type as input_product_type 
                              FROM building_inputs bi 
                              JOIN products p ON bi.product_id = p.id 
                              WHERE bi.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchBuildingOutputByProduct($db, int $productId) {
        $stmt = $db->prepare("SELECT * FROM building_outputs WHERE product_id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function fetchBuildingInputsMap($db, int $buildingTypeId): array {
        $map = [];
        $stmt = $db->prepare("SELECT product_id, amount FROM building_inputs WHERE building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $input) {
            $map[$input['product_id']] = (float)$input['amount'];
        }
        return $map;
    }

    private function insertProduct($db, array $data, string $imageName): int {
        $stmt = $db->prepare("INSERT INTO products (name, type, base_price, unit_cost, production_seconds, unit_type, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'], $data['type'], $data['base_price'], 
            $data['unit_cost'], $data['production_seconds'], $data['unit_type'], $imageName
        ]);
        return (int)$db->lastInsertId();
    }

    private function updateProductRecord($db, int $productId, array $data, string $imageName): void {
        $stmt = $db->prepare("UPDATE products SET name = ?, type = ?, base_price = ?, unit_cost = ?, production_seconds = ?, unit_type = ?, image = ? WHERE id = ?");
        $stmt->execute([
            $data['name'], $data['type'], $data['base_price'], 
            $data['unit_cost'], $data['production_seconds'], $data['unit_type'], $imageName, $productId
        ]);
    }

    private function saveBuildingRelations($db, int $buildingTypeId, int $productId, float $outputAmount, float $productionRate, array $inputsData): void {
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
}


// namespace App\Controllers;

// use System\Core\Controller;
// use System\Core\Database;

// class ProductController extends Controller {

//     private string $uploadDir = __DIR__ . '/../../public/uploads/products/';

//     public function index() {
//         $db = Database::getInstance();
        
//         $products = $db->query("SELECT p.*, bo.building_type_id, bo.amount as output_amount, bo.production_rate, bt.name as building_type_name 
//                                 FROM products p 
//                                 LEFT JOIN building_outputs bo ON p.id = bo.product_id 
//                                 LEFT JOIN building_types bt ON bo.building_type_id = bt.id 
//                                 ORDER BY p.id DESC")->fetchAll(\PDO::FETCH_ASSOC);

//         foreach ($products as &$product) {
//             $product['inputs'] = [];
//             if (!empty($product['building_type_id'])) {
//                 $inputStmt = $db->prepare("SELECT bi.*, p.name as input_product_name, p.type as input_product_type 
//                                           FROM building_inputs bi 
//                                           JOIN products p ON bi.product_id = p.id 
//                                           WHERE bi.building_type_id = ?");
//                 $inputStmt->execute([$product['building_type_id']]);
//                 $product['inputs'] = $inputStmt->fetchAll(\PDO::FETCH_ASSOC);
//             }
//         }

//         $this->adminView('admin/products/index', ['products' => $products]);
//     }

//     public function create() {
//         $db = Database::getInstance();
//         $allProducts = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
//         $buildingTypes = $db->query("SELECT * FROM building_types ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

//         $this->adminView('admin/products/create', [
//             'allProducts' => $allProducts,
//             $buildingTypesKey = 'buildingTypes' => $buildingTypes
//         ]);
//     }

//     public function store() {
//         if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//             $db = Database::getInstance();

//             $name           = trim($_POST['name'] ?? '');
//             $type           = trim($_POST['type'] ?? 'raw');
//             $basePrice      = (float)($_POST['base_price'] ?? 10.00);
//             $unitCost       = (float)($_POST['unit_cost'] ?? 100.00);
//             $prodSec        = (int)($_POST['production_seconds'] ?? 600); 
//             $unitType       = trim($_POST['unit_type'] ?? 'Adet');
//             $buildingTypeId = !empty($_POST['building_type_id']) ? (int)$_POST['building_type_id'] : null;
            
//             // BİRLEŞTİRİLEN ALAN: Döngü Başı Miktar (amount) ve Rate
//             $outputAmount   = (float)($_POST['output_amount'] ?? 1.0);
//             $productionRate = (float)($_POST['production_rate'] ?? 1.0);
//             $inputsData     = $_POST['building_inputs'] ?? [];

//             $imageName = 'default.png';

//             if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
//                 if (!is_dir($this->uploadDir)) {
//                     mkdir($this->uploadDir, 0755, true);
//                 }

//                 $fileTmpPath   = $_FILES['image']['tmp_name'];
//                 $fileName      = $_FILES['image']['name'];
//                 $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
//                 $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
//                 if (in_array($fileExtension, $allowedExtensions)) {
//                     $newFileName = uniqid('prod_') . '.' . $fileExtension;
//                     if (move_uploaded_file($fileTmpPath, $this->uploadDir . $newFileName)) {
//                         $imageName = $newFileName;
//                     }
//                 }
//             }

//             try {
//                 $db->beginTransaction();

//                 $stmt = $db->prepare("INSERT INTO products (name, type, base_price, unit_cost, production_seconds, unit_type, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
//                 $stmt->execute([$name, $type, $basePrice, $unitCost, $prodSec, $unitType, $imageName]);
//                 $productId = $db->lastInsertId();

//                 if ($buildingTypeId) {
//                     $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingTypeId]);
                    
//                     $outStmt = $db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount, production_rate) VALUES (?, ?, ?, ?)");
//                     $outStmt->execute([$buildingTypeId, $productId, $outputAmount, $productionRate]);

//                     $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingTypeId]);
                    
//                     if (!empty($inputsData)) {
//                         $inStmt = $db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
//                         foreach ($inputsData as $reqProductId => $reqAmount) {
//                             if ((float)$reqAmount > 0) {
//                                 $inStmt->execute([$buildingTypeId, (int)$reqProductId, (float)$reqAmount]);
//                             }
//                         }
//                     }
//                 }

//                 $db->commit();
//             } catch (\Exception $e) {
//                 $db->rollBack();
//                 error_log("Ürün Ekleme Hatası: " . $e->getMessage());
//             }

//             redirect('admin/product');
//         }
//     }

//     public function edit($id) {
//         $db = Database::getInstance();
//         $productId = (int)$id;

//         $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
//         $stmt->execute([$productId]);
//         $product = $stmt->fetch(\PDO::FETCH_ASSOC);

//         if (!$product) {
//             redirect('admin/product');
//         }

//         $outStmt = $db->prepare("SELECT * FROM building_outputs WHERE product_id = ?");
//         $outStmt->execute([$productId]);
//         $buildingOutput = $outStmt->fetch(\PDO::FETCH_ASSOC);

//         $buildingInputsMap = [];
//         $assignedBuildingTypeId = $buildingOutput['building_type_id'] ?? null;
//         $outputAmount = $buildingOutput['amount'] ?? 1.00;
//         $baseProductionRate = isset($buildingOutput['production_rate']) ? (float)$buildingOutput['production_rate'] : 1.0;

//         if ($assignedBuildingTypeId) {
//             $inStmt = $db->prepare("SELECT product_id, amount FROM building_inputs WHERE building_type_id = ?");
//             $inStmt->execute([$assignedBuildingTypeId]);
//             foreach ($inStmt->fetchAll(\PDO::FETCH_ASSOC) as $input) {
//                 $buildingInputsMap[$input['product_id']] = (float)$input['amount'];
//             }
//         }

//         $allProducts = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
//         $buildingTypes = $db->query("SELECT * FROM building_types ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
//         // echo "<pre>";
//         // print_r($buildingTypes); // Debugging line to check building types
//         // echo "</pre>";
//         // exit; // Stop execution to inspect the output

//         $this->adminView('admin/products/edit', [
//             'product' => $product,
//             'buildingOutput' => $buildingOutput,
//             'outputAmount' => $outputAmount,
//             'baseProductionRate' => $baseProductionRate,
//             'assignedBuildingTypeId' => $assignedBuildingTypeId,
//             'buildingInputsMap' => $buildingInputsMap,
//             'allProducts' => $allProducts,
//             'buildingTypes' => $buildingTypes
//         ]);
//     }

//     public function update($id) {
//         if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//             $db = Database::getInstance();
//             $productId = (int)$id;

//             $name           = trim($_POST['name'] ?? '');
//             $type           = trim($_POST['type'] ?? 'raw');
//             $basePrice      = (float)($_POST['base_price'] ?? 10.00);
//             $unitCost       = (float)($_POST['unit_cost'] ?? 100.00);
//             $prodSec        = (int)($_POST['production_seconds'] ?? 600); 
//             $unitType       = trim($_POST['unit_type'] ?? 'Adet');
//             $buildingTypeId = !empty($_POST['building_type_id']) ? (int)$_POST['building_type_id'] : null;
            
//             $outputAmount   = (float)($_POST['output_amount'] ?? 1.0);
//             $productionRate = (float)($_POST['production_rate'] ?? 1.0);
//             $inputsData     = $_POST['building_inputs'] ?? [];

//             $stmtOld = $db->prepare("SELECT image FROM products WHERE id = ?");
//             $stmtOld->execute([$productId]);
//             $oldProduct = $stmtOld->fetch(\PDO::FETCH_ASSOC);
//             $imageName = $oldProduct['image'] ?? 'default.png';

//             if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
//                 if (!is_dir($this->uploadDir)) {
//                     mkdir($this->uploadDir, 0755, true);
//                 }

//                 $fileTmpPath   = $_FILES['image']['tmp_name'];
//                 $fileName      = $_FILES['image']['name'];
//                 $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
//                 $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
//                 if (in_array($fileExtension, $allowedExtensions)) {
//                     $newFileName = uniqid('prod_') . '.' . $fileExtension;
//                     if (move_uploaded_file($fileTmpPath, $this->uploadDir . $newFileName)) {
//                         if ($imageName !== 'default.png' && file_exists($this->uploadDir . $imageName)) {
//                             unlink($this->uploadDir . $imageName);
//                         }
//                         $imageName = $newFileName;
//                     }
//                 }
//             }

//             try {
//                 $db->beginTransaction();

//                 $stmt = $db->prepare("UPDATE products SET name = ?, type = ?, base_price = ?, unit_cost = ?, production_seconds = ?, unit_type = ?, image = ? WHERE id = ?");
//                 $stmt->execute([$name, $type, $basePrice, $unitCost, $prodSec, $unitType, $imageName, $productId]);

//                 $db->prepare("DELETE FROM building_outputs WHERE product_id = ?")->execute([$productId]);

//                 if ($buildingTypeId) {
//                     $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingTypeId]);

//                     $outStmt = $db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount, production_rate) VALUES (?, ?, ?, ?)");
//                     $outStmt->execute([$buildingTypeId, $productId, $outputAmount, $productionRate]);

//                     $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingTypeId]);

//                     if (!empty($inputsData)) {
//                         $inStmt = $db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
//                         foreach ($inputsData as $reqProductId => $reqAmount) {
//                             if ((float)$reqAmount > 0) {
//                                 $inStmt->execute([$buildingTypeId, (int)$reqProductId, (float)$reqAmount]);
//                             }
//                         }
//                     }
//                 }

//                 $db->commit();
//             } catch (\Exception $e) {
//                 $db->rollBack();
//                 error_log("Ürün Güncelleme Hatası: " . $e->getMessage());
//             }

//             redirect('admin/product');
//         }
//     }

//     public function delete($id) {
//         $db = Database::getInstance();
//         $productId = (int)$id;

//         $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
//         $stmt->execute([$productId]);
//         $product = $stmt->fetch(\PDO::FETCH_ASSOC);

//         if ($product && $product['image'] !== 'default.png') {
//             $filePath = $this->uploadDir . $product['image'];
//             if (file_exists($filePath)) {
//                 unlink($filePath);
//             }
//         }

//         $db->prepare("DELETE FROM building_outputs WHERE product_id = ?")->execute([$productId]);
//         $db->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);

//         redirect('admin/product');
//     }
// }
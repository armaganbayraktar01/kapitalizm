<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class ProductController extends Controller {

    private string $uploadDir = __DIR__ . '/../../public/uploads/products/';

    public function index() {
        $db = Database::getInstance();
        
        // Ürünleri ve varsa reçete detaylarını çekelim
        $products = $db->query("SELECT p.*, pr.id as recipe_id, pr.output_quantity 
                                FROM products p 
                                LEFT JOIN product_recipes pr ON p.id = pr.product_id 
                                ORDER BY p.id DESC")->fetchAll(\PDO::FETCH_ASSOC);

        // Her ürünün reçete girdilerini (maddelerini) ekleyelim
        foreach ($products as &$product) {
            $product['recipe_items'] = [];
            if ($product['recipe_id']) {
                $itemStmt = $db->prepare("SELECT pri.*, prod.name as item_name, prod.type as item_type 
                                          FROM product_recipe_items pri 
                                          JOIN products prod ON pri.product_id = prod.id 
                                          WHERE pri.product_recipe_id = ?");
                $itemStmt->execute([$product['recipe_id']]);
                $product['recipe_items'] = $itemStmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        }

        $this->adminView('admin/products/index', ['products' => $products]);
    }

    public function create() {
        $db = Database::getInstance();
        // Reçeteye girdi seçebilmek için sistemdeki tüm ürünleri çekelim
        $allProducts = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->adminView('admin/products/create', ['allProducts' => $allProducts]);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();

            $name       = trim($_POST['name'] ?? '');
            $type       = trim($_POST['type'] ?? 'raw');
            $basePrice  = (float)($_POST['base_price'] ?? 10.00);
            $outputQty  = (int)($_POST['output_quantity'] ?? 1);
            $recipeItems = $_POST['recipe_items'] ?? []; // [product_id => quantity]

            $imageName = 'default.png';

            // Görsel Yükleme
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

                // 1. Ürünü Kaydet
                $stmt = $db->prepare("INSERT INTO products (name, type, base_price, image) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $type, $basePrice, $imageName]);
                $productId = $db->lastInsertId();

                // 2. Eğer ürün hammaddeden farklıysa ve girdileri varsa reçete oluştur
                if ($type !== 'raw' && !empty($recipeItems)) {
                    $recipeStmt = $db->prepare("INSERT INTO product_recipes (product_id, output_quantity) VALUES (?, ?)");
                    $recipeStmt->execute([$productId, $outputQty]);
                    $recipeId = $db->lastInsertId();

                    $itemStmt = $db->prepare("INSERT INTO product_recipe_items (product_recipe_id, product_id, quantity_required) VALUES (?, ?, ?)");
                    foreach ($recipeItems as $reqProductId => $qty) {
                        if ((int)$qty > 0) {
                            $itemStmt->execute([$recipeId, (int)$reqProductId, (int)$qty]);
                        }
                    }
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
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

        // Ürünün mevcut reçetesini çek
        $recipeStmt = $db->prepare("SELECT * FROM product_recipes WHERE product_id = ?");
        $recipeStmt->execute([$productId]);
        $recipe = $recipeStmt->fetch(\PDO::FETCH_ASSOC);

        $recipeItemsMap = [];
        if ($recipe) {
            $itemsStmt = $db->prepare("SELECT product_id, quantity_required FROM product_recipe_items WHERE product_recipe_id = ?");
            $itemsStmt->execute([$recipe['id']]);
            foreach ($itemsStmt->fetchAll(\PDO::FETCH_ASSOC) as $item) {
                $recipeItemsMap[$item['product_id']] = $item['quantity_required'];
            }
        }

        $allProducts = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->adminView('admin/products/edit', [
            'product' => $product,
            'recipe' => $recipe,
            'recipeItemsMap' => $recipeItemsMap,
            'allProducts' => $allProducts
        ]);
    }

    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $productId = (int)$id;

            $name       = trim($_POST['name'] ?? '');
            $type       = trim($_POST['type'] ?? 'raw');
            $basePrice  = (float)($_POST['base_price'] ?? 10.00);
            $outputQty  = (int)($_POST['output_quantity'] ?? 1);
            $recipeItems = $_POST['recipe_items'] ?? [];

            // Eski görseli bul
            $stmtOld = $db->prepare("SELECT image FROM products WHERE id = ?");
            $stmtOld->execute([$productId]);
            $oldProduct = $stmtOld->fetch(\PDO::FETCH_ASSOC);
            $imageName = $oldProduct['image'] ?? 'default.png';

            // Yeni görsel yüklendiyse
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

                // 1. Ürünü Güncelle
                $stmt = $db->prepare("UPDATE products SET name = ?, type = ?, base_price = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $type, $basePrice, $imageName, $productId]);

                // 2. Reçete İşlemleri
                // Önce mevcut reçeteyi bulalım
                $recipeStmt = $db->prepare("SELECT id FROM product_recipes WHERE product_id = ?");
                $recipeStmt->execute([$productId]);
                $recipe = $recipeStmt->fetch(\PDO::FETCH_ASSOC);

                if ($type === 'raw' || $type === 'utility') {
                    // Hammadde veya utility olduysa ve eski reçetesi varsa tamamen sil
                    if ($recipe) {
                        $db->prepare("DELETE FROM product_recipes WHERE id = ?")->execute([$recipe['id']]);
                    }
                } else {
                    // Semi veya Finished ise reçeteyi güncelle/oluştur
                    if ($recipe) {
                        $recipeId = $recipe['id'];
                        $db->prepare("UPDATE product_recipes SET output_quantity = ? WHERE id = ?")->execute([$outputQty, $recipeId]);
                        // Eski girdileri temizle
                        $db->prepare("DELETE FROM product_recipe_items WHERE product_recipe_id = ?")->execute([$recipeId]);
                    } else {
                        $insRecipe = $db->prepare("INSERT INTO product_recipes (product_id, output_quantity) VALUES (?, ?)");
                        $insRecipe->execute([$productId, $outputQty]);
                        $recipeId = $db->lastInsertId();
                    }

                    // Yeni girdileri ekle
                    $itemStmt = $db->prepare("INSERT INTO product_recipe_items (product_recipe_id, product_id, quantity_required) VALUES (?, ?, ?)");
                    foreach ($recipeItems as $reqProductId => $qty) {
                        if ((int)$qty > 0 && (int)$reqProductId !== $productId) {
                            $itemStmt->execute([$recipeId, (int)$reqProductId, (int)$qty]);
                        }
                    }
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
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

        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);
        redirect('admin/product');
    }
}
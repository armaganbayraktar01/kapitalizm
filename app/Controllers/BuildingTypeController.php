<?php

namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;
use Exception;
use PDO;

class BuildingTypeController extends Controller
{
    private string $uploadDir = __DIR__ . '/../../public/uploads/buildings/';

    public const CATEGORIES = [
        'farms'           => 'Tarlalar',
        'livestock-farms' => 'Çiftlikler',
        'mines'           => 'Madenler',
        'wells'           => 'Kuyular',
        'workshops'       => 'İmalathaneler',
        'refineries'      => 'İşleme Tesisleri',
        'manufactories'   => 'Atölyeler',
        'factories'       => 'Fabrikalar',
        'assembly-plants' => 'Montaj Tesisleri',
        'infrastructure'  => 'Altyapı Tesisleri'
    ];

    /**
     * Key: ENUM Veritabanı Değeri
     * Format: [ENUM Key, Açıklama, Min Girdi, Max Girdi, Min Çıktı, Max Çıktı]
     */
    public const COMPLEXITY_TYPES = [
        'raw'              => ['raw', 'Hammaddesiz, tek çıktı (Toplama / Doğal Kaynak)', 0, 0, 1, 1],
        'semi'             => ['semi', 'İşlenmiş / Standart Dönüşüm (1 Girdi ➔ 1 Çıktı)', 1, 1, 1, 1],
        'zero_in_one_out'  => ['zero_in_one_out', 'Hammaddesiz, tek çıktı (Sonsuz kaynak / Toplama)', 0, 0, 1, 1],
        'zero_in_two_out'  => ['zero_in_two_out', 'Hammaddesiz, iki çıktı (Yan ürün veren toplama)', 0, 0, 2, 2],
        'one_in_one_out'   => ['one_in_one_out', '1 Hammadde ➔ 1 Çıktı (Temel dönüşüm)', 1, 1, 1, 1],
        'one_in_two_out'   => ['one_in_two_out', '1 Hammadde ➔ 2 Çıktı (Ayrıştırma / Rafine)', 1, 1, 2, 2],
        'two_in_one_out'   => ['two_in_one_out', '2 Hammadde ➔ 1 Çıktı (Klasik üretim)', 2, 2, 1, 1],
        'two_in_two_out'   => ['two_in_two_out', '2 Hammadde ➔ 2 Çıktı (Kimyasal / Endüstriyel reaksiyon)', 2, 2, 2, 2],
        'two_in_three_out' => ['two_in_three_out', '2 Hammadde ➔ 3 Çıktı (Gelişmiş ağır sanayi)', 2, 2, 3, 3],
        'three_plus_in'    => ['three_plus_in', '3+ Hammadde ➔ Çoklu Çıktı (Karmaşık montaj)', 3, 99, 1, 99]
    ];

    private function slugify(string $text): string
    {
        $tr = ['ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç', 'ı', 'İ', ' '];
        $en = ['s', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c', 'i', 'i', '-'];
        $text = str_replace($tr, $en, mb_strtolower($text, 'UTF-8'));
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }

    private function validateRecipeComplexity(string $complexityType, array $inputs, array $outputs): ?string
    {
        $rule = self::COMPLEXITY_TYPES[$complexityType] ?? null;

        if (!$rule) {
            return "Geçersiz komplekslik tipi seçildi.";
        }

        $inputCount = count($inputs);
        $outputCount = count($outputs);

        list($enumKey, $label, $minIn, $maxIn, $minOut, $maxOut) = $rule;

        if ($inputCount < $minIn || $inputCount > $maxIn) {
            return "'{$enumKey}' kuralına göre girdi sayısı {$minIn} ile {$maxIn} arasında olmalıdır. (Girilen: {$inputCount})";
        }

        if ($outputCount < $minOut || $outputCount > $maxOut) {
            return "'{$enumKey}' kuralına göre çıktı sayısı {$minOut} ile {$maxOut} arasında olmalıdır. (Girilen: {$outputCount})";
        }

        return null;
    }

    public function index()
    {
        $db = Database::getInstance();
        $buildings = $db->query("SELECT bt.* FROM building_types bt ORDER BY bt.id DESC")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($buildings as &$b) {
            $inStmt = $db->prepare("
                SELECT bi.*, p.name as product_name, p.type as product_type, p.unit_type 
                FROM building_inputs bi 
                JOIN products p ON bi.product_id = p.id 
                WHERE bi.building_type_id = ?
            ");
            $inStmt->execute([$b['id']]);
            $b['inputs'] = $inStmt->fetchAll(PDO::FETCH_ASSOC);

            $outStmt = $db->prepare("
                SELECT bo.*, p.name as product_name, p.type as product_type, p.unit_type 
                FROM building_outputs bo 
                JOIN products p ON bo.product_id = p.id 
                WHERE bo.building_type_id = ?
            ");
            $outStmt->execute([$b['id']]);
            $b['outputs'] = $outStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->adminView('admin/buildings/index', [
            'buildings'       => $buildings,
            'categories'      => self::CATEGORIES,
            'complexityTypes' => self::COMPLEXITY_TYPES
        ]);
    }

    public function create()
    {
        $db = Database::getInstance();
        $products = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $provinces = $db->query("SELECT id, name FROM provinces ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $this->adminView('admin/buildings/create', [
            'products'        => $products,
            'provinces'       => $provinces,
            'categories'      => self::CATEGORIES,
            'complexityTypes' => self::COMPLEXITY_TYPES
        ]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/buildingtype');
        }

        $db = Database::getInstance();

        $name              = trim($_POST['name'] ?? '');
        $category          = trim($_POST['category'] ?? 'factories');
        $complexityType    = trim($_POST['complexity_type'] ?? 'two_in_one_out');
        $unitType          = trim($_POST['unit_type'] ?? 'Adet');
        $baseCost          = (float) ($_POST['base_cost'] ?? 50000.00);
        $storageType       = trim($_POST['storage_type'] ?? 'raw');
        $requiredScore     = (int) ($_POST['required_score'] ?? 0);

        $energyConsumption = (float) ($_POST['energy_consumption'] ?? 50.00);
        $waterConsumption  = (float) ($_POST['water_consumption'] ?? 10.00);
        $laborRequired     = (int) ($_POST['labor_required'] ?? 10);
        $productionRate    = (float) ($_POST['production_rate'] ?? 8.00);
        $consumptionAmount = (float) ($_POST['consumption_amount'] ?? 0.00);

        $validInputs = [];
        if (!empty($_POST['inputs']) && is_array($_POST['inputs'])) {
            foreach ($_POST['inputs'] as $row) {
                $pId = (int) ($row['product_id'] ?? 0);
                $amt = (float) ($row['amount'] ?? 0);
                if ($pId > 0 && $amt > 0) {
                    $validInputs[] = ['product_id' => $pId, 'amount' => $amt];
                }
            }
        }

        $validOutputs = [];
        if (!empty($_POST['outputs']) && is_array($_POST['outputs'])) {
            foreach ($_POST['outputs'] as $row) {
                $pId = (int) ($row['product_id'] ?? 0);
                $amt = (float) ($row['amount'] ?? 0);
                if ($pId > 0 && $amt > 0) {
                    $validOutputs[] = ['product_id' => $pId, 'amount' => $amt];
                }
            }
        }

        $validationError = $this->validateRecipeComplexity($complexityType, $validInputs, $validOutputs);
        if ($validationError) {
            die("Hata: " . $validationError);
        }

        $consumesProductId = !empty($validInputs) ? $validInputs[0]['product_id'] : null;
        $producesProductId = !empty($validOutputs) ? $validOutputs[0]['product_id'] : null;

        $imageName = 'default.png';
        if (!empty($name) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $slugName = $this->slugify($name);
            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0755, true);
            }
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $imageName = $slugName . '-' . time() . '.' . $fileExtension;
                move_uploaded_file($fileTmpPath, $this->uploadDir . $imageName);
            }
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO building_types (
                    name, category, complexity_type, produces_product_id, consumes_product_id,
                    consumption_amount, production_rate, unit_type, base_cost, required_score,
                    storage_type, energy_consumption, water_consumption, labor_required, image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $category, $complexityType, $producesProductId, $consumesProductId,
                $consumptionAmount,$productionRate, $unitType,$baseCost, $requiredScore,$storageType, $energyConsumption,$waterConsumption, $laborRequired,$imageName
            ]);
            $buildingTypeId = (int)$db->lastInsertId();

            $inStmt =$db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
            foreach ($validInputs as$in) {
                $inStmt->execute([$buildingTypeId, $in['product_id'],$in['amount']]);
            }

            $outStmt =$db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
            foreach ($validOutputs as$out) {
                $outStmt->execute([$buildingTypeId, $out['product_id'],$out['amount']]);
            }

            $matrixStmt =$db->prepare("
                INSERT INTO matrix_level_building (
                    building_type_id, level, production_rate, consumption_amount, upgrade_cost,
                    maintenance_cost, energy_consumption, water_consumption, labor_required,
                    required_xp, wear_rate, base_labor_cost, base_transport_cost, base_tax_rate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!empty($_POST['levels']) && is_array($_POST['levels'])) {
                foreach ($_POST['levels'] as $lvl =>$data) {
                    $lvlNum   = (int)$lvl;
                    $mProd    = (float) ($data['production_rate'] ?? 8.00);$mCons    = (float) ($data['consumption_amount'] ?? 0.00);$mCost    = (float) ($data['upgrade_cost'] ?? ($baseCost * $lvlNum * 1.5));$mMaint   = (float) ($data['maintenance_cost'] ?? (100.00 * $lvlNum));
                    $mEnergy  = (float) ($data['energy_consumption'] ?? (50.00 + ($lvlNum * 5)));$mWater   = (float) ($data['water_consumption'] ?? (10.00 + ($lvlNum * 20)));
                    $mLabor   = (int)   ($data['labor_required'] ?? (10 + ($lvlNum * 2)));$mXp      = (int)   ($data['required_xp'] ?? ($lvlNum * 500));
                    $mWear    = (float) ($data['wear_rate'] ?? 2.00);
                    $mBLabor  = (float) ($data['base_labor_cost'] ?? 150.00);
                    $mBTrans  = (float) ($data['base_transport_cost'] ?? 50.00);
                    $mBTax    = (float) ($data['base_tax_rate'] ?? 18.00);

                    $matrixStmt->execute([
                        $buildingTypeId,$lvlNum, $mProd,$mCons, $mCost,$mMaint, $mEnergy,$mWater, $mLabor,$mXp,
                        $mWear,$mBLabor, $mBTrans,$mBTax
                    ]);
                }
            } else {
                $matrixStmt->execute([$buildingTypeId, 1, $productionRate,$consumptionAmount, $baseCost,
                    100.00,$energyConsumption, $waterConsumption,$laborRequired, 0,
                    2.00, 150.00, 50.00, 18.00
                ]);
            }

            $db->commit();
            redirect('admin/buildingtype');
        } catch (Exception $e) {
            if ($db->inTransaction()) {$db->rollBack();
            }
            die("Bina Tipi Kayıt Hatası: " . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $db = Database::getInstance();
        $buildingId = (int)$id;

        $stmt =$db->prepare("SELECT * FROM building_types WHERE id = ?");
        $stmt->execute([$buildingId]);
        $building =$stmt->fetch(PDO::FETCH_ASSOC);

        if (!$building) {
            redirect('admin/buildingtype');
        }

        $inStmt =$db->prepare("SELECT * FROM building_inputs WHERE building_type_id = ?");
        $inStmt->execute([$buildingId]);
        $buildingInputs =$inStmt->fetchAll(PDO::FETCH_ASSOC);

        $outStmt =$db->prepare("SELECT * FROM building_outputs WHERE building_type_id = ?");
        $outStmt->execute([$buildingId]);
        $buildingOutputs =$outStmt->fetchAll(PDO::FETCH_ASSOC);

        $matrixStmt =$db->prepare("SELECT * FROM matrix_level_building WHERE building_type_id = ? ORDER BY level ASC");
        $matrixStmt->execute([$buildingId]);
        $levels =$matrixStmt->fetchAll(PDO::FETCH_ASSOC);

        $products =$db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $provinces =$db->query("SELECT id, name FROM provinces ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $this->adminView('admin/buildings/edit', [
            'building'        => $building,
            'inputs'          => $buildingInputs,
            'outputs'         => $buildingOutputs,
            'levels'          => $levels,
            'products'        => $products,
            'provinces'       => $provinces,
            'categories'      => self::CATEGORIES,
            'complexityTypes' => self::COMPLEXITY_TYPES
        ]);
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin/buildingtype');
        }

        $db = Database::getInstance();
        $buildingId = (int)$id;

        $name           = trim($_POST['name'] ?? '');
        $category       = trim($_POST['category'] ?? 'factories');
        $complexityType = trim($_POST['complexity_type'] ?? 'two_in_one_out');
        $unitType       = trim($_POST['unit_type'] ?? 'Adet');
        $baseCost       = (float) ($_POST['base_cost'] ?? 50000.00);
        $storageType    = trim($_POST['storage_type'] ?? 'raw');
        $requiredScore  = (int) ($_POST['required_score'] ?? 0);

        $validInputs = [];
        if (!empty($_POST['inputs']) && is_array($_POST['inputs'])) {
            foreach ($_POST['inputs'] as$row) {
                $pId = (int) ($row['product_id'] ?? 0);
                $amt = (float) ($row['amount'] ?? 0);
                if ($pId > 0 && $amt > 0) {$validInputs[] = ['product_id' => $pId, 'amount' =>$amt];
                }
            }
        }

        $validOutputs = [];
        if (!empty($_POST['outputs']) && is_array($_POST['outputs'])) {
            foreach ($_POST['outputs'] as$row) {
                $pId = (int) ($row['product_id'] ?? 0);
                $amt = (float) ($row['amount'] ?? 0);
                if ($pId > 0 && $amt > 0) {$validOutputs[] = ['product_id' => $pId, 'amount' =>$amt];
                }
            }
        }

        $validationError = $this->validateRecipeComplexity($complexityType, $validInputs,$validOutputs);
        if ($validationError) {
            die("Güncelleme Hatası: " . $validationError);
        }

        $consumesProductId = !empty($validInputs) ?$validInputs[0]['product_id'] : null;
        $producesProductId = !empty($validOutputs) ?$validOutputs[0]['product_id'] : null;

        try {
            $db->beginTransaction();

            $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingId]);
            $inStmt =$db->prepare("INSERT INTO building_inputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
            foreach ($validInputs as$in) {
                $inStmt->execute([$buildingId, $in['product_id'],$in['amount']]);
            }

            $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingId]);
            $outStmt =$db->prepare("INSERT INTO building_outputs (building_type_id, product_id, amount) VALUES (?, ?, ?)");
            foreach ($validOutputs as$out) {
                $outStmt->execute([$buildingId, $out['product_id'],$out['amount']]);
            }

            if (isset($_POST['levels']) && is_array($_POST['levels'])) {
                foreach ($_POST['levels'] as $lvl =>$data) {
                    $lvlNum   = (int)$lvl;
                    $mProd    = (float) ($data['production_rate'] ?? 8.00);
                    $mCons    = (float) ($data['consumption_amount'] ?? 0.00);
                    $mCost    = (float) ($data['upgrade_cost'] ?? 0.00);
                    $mMaint   = (float) ($data['maintenance_cost'] ?? 0.00);
                    $mEnergy  = (float) ($data['energy_consumption'] ?? 50.00);
                    $mWater   = (float) ($data['water_consumption'] ?? 10.00);
                    $mLabor   = (int)   ($data['labor_required'] ?? 10);
                    $mXp      = (int)   ($data['required_xp'] ?? 0);
                    $mWear    = (float) ($data['wear_rate'] ?? 2.00);
                    $mBLabor  = (float) ($data['base_labor_cost'] ?? 150.00);
                    $mBTrans  = (float) ($data['base_transport_cost'] ?? 50.00);
                    $mBTax    = (float) ($data['base_tax_rate'] ?? 18.00);

                    $check =$db->prepare("SELECT id FROM matrix_level_building WHERE building_type_id = ? AND level = ?");
                    $check->execute([$buildingId,$lvlNum]);

                    if ($check->fetch()) {
                        $upd =$db->prepare("
                            UPDATE matrix_level_building SET
                                production_rate = ?, consumption_amount = ?, upgrade_cost = ?,
                                maintenance_cost = ?, energy_consumption = ?, water_consumption = ?,
                                labor_required = ?, required_xp = ?, wear_rate = ?,
                                base_labor_cost = ?, base_transport_cost = ?, base_tax_rate = ?
                            WHERE building_type_id = ? AND level = ?
                        ");
                        $upd->execute([
                            $mProd,$mCons, $mCost,$mMaint, $mEnergy,$mWater,
                            $mLabor,$mXp, $mWear,$mBLabor, $mBTrans,$mBTax,
                            $buildingId,$lvlNum
                        ]);
                    } else {
                        $ins =$db->prepare("
                            INSERT INTO matrix_level_building (
                                building_type_id, level, production_rate, consumption_amount, upgrade_cost,
                                maintenance_cost, energy_consumption, water_consumption, labor_required,
                                required_xp, wear_rate, base_labor_cost, base_transport_cost, base_tax_rate
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            $buildingId,$lvlNum, $mProd,$mCons, $mCost,$mMaint,
                            $mEnergy,$mWater, $mLabor,$mXp, $mWear,$mBLabor, $mBTrans,$mBTax
                        ]);
                    }
                }
            }

            $stmtOld =$db->prepare("SELECT image FROM building_types WHERE id = ?");
            $stmtOld->execute([$buildingId]);
            $oldBuilding =$stmtOld->fetch(PDO::FETCH_ASSOC);
            $imageName =$oldBuilding['image'] ?? 'default.png';

            if (!empty($name)) {$slugName = $this->slugify($name);

                if (isset($_FILES['image']) &&$_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    if (!is_dir($this->uploadDir)) {
                        mkdir($this->uploadDir, 0755, true);
                    }

                    $fileTmpPath = $_FILES['image']['tmp_name'];$fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                    if (in_array($fileExtension, $allowedExtensions)) {$newImageName = $slugName . '-' . time() . '.' . $fileExtension;

                        if ($imageName !== 'default.png' && file_exists($this->uploadDir .$imageName)) {
                            @unlink($this->uploadDir .$imageName);
                        }

                        if (move_uploaded_file($fileTmpPath, $this->uploadDir .$newImageName)) {
                            $imageName =$newImageName;
                        }
                    }
                }
            }

            $stmt =$db->prepare("
                UPDATE building_types SET
                    name = ?, category = ?, complexity_type = ?, produces_product_id = ?,
                    consumes_product_id = ?, unit_type = ?, base_cost = ?, required_score = ?,
                    storage_type = ?, image = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $category,$complexityType, $producesProductId,$consumesProductId, $unitType,$baseCost, $requiredScore,$storageType, $imageName,$buildingId
            ]);

            $db->commit();
            redirect('admin/buildingtype');
        } catch (Exception $e) {
            if ($db->inTransaction()) {$db->rollBack();
            }
            die("Güncelleme Hatası: " . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $db = Database::getInstance();
        $buildingId = (int)$id;

        try {
            $db->beginTransaction();

            $stmt =$db->prepare("SELECT image FROM building_types WHERE id = ?");
            $stmt->execute([$buildingId]);
            $building =$stmt->fetch(PDO::FETCH_ASSOC);

            if ($building && !empty($building['image']) && $building['image'] !== 'default.png') {$filePath = $this->uploadDir .$building['image'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $db->prepare("DELETE FROM building_inputs WHERE building_type_id = ?")->execute([$buildingId]);
            $db->prepare("DELETE FROM building_outputs WHERE building_type_id = ?")->execute([$buildingId]);
            $db->prepare("DELETE FROM matrix_level_building WHERE building_type_id = ?")->execute([$buildingId]);
            $db->prepare("DELETE FROM building_types WHERE id = ?")->execute([$buildingId]);

            $db->commit();
            redirect('admin/buildingtype');
        } catch (Exception $e) {
            if ($db->inTransaction()) {$db->rollBack();
            }
            die("Silme İşlemi Başarısız: " . $e->getMessage());
        }
    }
}
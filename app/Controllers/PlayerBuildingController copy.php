<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class PlayerBuildingController extends Controller {


    public function index() {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $activeCategory = $_GET['category'] ?? 'Tesis ve Fabrika';
        
        $timeStmt = $db->query("SELECT UNIX_TIMESTAMP(NOW()) as db_now");
        $now = (int)$timeStmt->fetchColumn();
        if (!$now) $now = time();

        $playerStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $playerStmt->execute([$userId]);
        $player = $playerStmt->fetch(\PDO::FETCH_ASSOC);
        if (!isset($player['warehouse_level'])) $player['warehouse_level'] = 1;

        // Arka plandaki ara depo ve döngü motoru tetiklenir
        $this->processProductionCycles($db, $userId, $now);

        $playerStmt->execute([$userId]);
        $player = $playerStmt->fetch(\PDO::FETCH_ASSOC);
        $warehouseLevel = (int)($player['warehouse_level'] ?? 1);
        $productSlotLimit = $warehouseLevel * 500.00;
        $nextWarehouseLevel = $warehouseLevel + 1;

        $matrixStmt = $db->prepare("SELECT * FROM warehouse_level_matrix WHERE level = ?");
        $matrixStmt->execute([$nextWarehouseLevel]);
        $nextWarehouseMeta = $matrixStmt->fetch(\PDO::FETCH_ASSOC);
        $warehouseUpgradeCost = $nextWarehouseMeta ? (float)$nextWarehouseMeta['upgrade_cost'] : ($warehouseLevel * 10000);

        $researches = $this->getPlayerResearches($db, $userId);

        $query = "SELECT pb.*, pb.id as building_id,
                         UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
                         UNIX_TIMESTAMP(pb.paused_at) as paused_ts,
                         UNIX_TIMESTAMP(pb.created_at) as created_ts,
                         bt.id as building_type_id, bt.name as building_name,
                         bt.category, bt.complexity_type,
                         bt.base_cost, bt.unit_type, bt.image as building_image,
                         bt.produces_product_id,
                         p.name as product_name, p.image as product_image,
                         p.unit_cost as product_base_cost,
                         prov.name as province_name, prov.population,
                         prov.labor_cost_multiplier,
                         prov.agri_multiplier, prov.ind_multiplier,
                         prov.mine_multiplier, prov.latitude, prov.longitude,
                         mlb.water_consumption, mlb.labor_required, mlb.wear_rate,
                         mlb.base_tax_rate,
                         mlb.production_rate, mlb.energy_consumption,
                         mlb.base_labor_cost, mlb.base_transport_cost
                  FROM user_buildings pb
                  JOIN building_types bt ON pb.building_type_id = bt.id
                  LEFT JOIN products p ON bt.produces_product_id = p.id
                  JOIN provinces prov ON pb.province_id = prov.id
                  LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
                  WHERE pb.user_id = ?
                  ORDER BY pb.id DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        $rawBuildings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $invStmt = $db->prepare("SELECT product_id, quantity, unit_cost FROM user_inventories WHERE user_id = ?");
        $invStmt->execute([$userId]);
        $inventoryMap = [];
        $inventoryCostMap = [];
        foreach ($invStmt->fetchAll(\PDO::FETCH_ASSOC) as $invRow) {
            $inventoryMap[$invRow['product_id']] = (float)$invRow['quantity'];
            $inventoryCostMap[$invRow['product_id']] = (float)($invRow['unit_cost'] ?? 0);
        }

        $energyProdStmt = $db->prepare("SELECT id FROM products WHERE name LIKE '%Enerji%' OR name LIKE '%Elektrik%' LIMIT 1");
        $energyProdStmt->execute();
        $energyProductId = $energyProdStmt->fetchColumn() ?: 0;
        $currentEnergyStock = $inventoryMap[$energyProductId] ?? 0.0;

        $my_buildings = [];
        $productionSec = 600;

        foreach ($rawBuildings as $b) {
            $category = strtolower(trim($b['category']));
            $soilMultiplier = 1.0;
            if (str_contains($category, 'agri') || str_contains($category, 'tarım') || str_contains($category, 'bahçe')) {
                $soilMultiplier = (float)($b['agri_multiplier'] ?? 1.0);
                $soilMultiplier *= (1.0 + (($researches['rBio'] - 1) * 0.02));
            } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) {
                $soilMultiplier = (float)($b['ind_multiplier'] ?? 1.0);
            } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
                $soilMultiplier = (float)$b['mine_multiplier'] ?? 1.0;
            }
            if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

            $level = (int)($b['level'] ?? 1);
            $productionRate = (float)($b['production_rate'] ?? 1.0);
            $health = (float)($b['condition_health'] ?? 100.0);
            $healthFactor = max(0, $health / 100.0);

            $outputs = $this->getBuildingOutputs($db, $b['building_type_id']);
            $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;
            $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);
            
            $productionAmount = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);
            if ($health < 50.0) $productionAmount = 0.0;

            $unitLabel = $b['unit_type'] ?? 'Adet';
            $rawInputs = $this->getBuildingInputs($db, $b['building_type_id']);
            $inputs = [];
            $inputMaterialCost = 0;

            foreach ($rawInputs as $inp) {
                $scaledInputAmount = round($inp['required_amount'] * $productionRate, 2);
                $mCost = $inventoryCostMap[$inp['product_id']] ?? 0;
                if ($mCost <= 0) {
                    $matCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                    $matCostStmt->execute([$inp['product_id']]);
                    $mCost = (float)($matCostStmt->fetchColumn() ?? 0);
                }
                $rowCost = $mCost * $scaledInputAmount;
                $inputMaterialCost += $rowCost;

                $inputs[] = [
                    'product_id' => $inp['product_id'],
                    'product_name' => $inp['product_name'],
                    'amount' => $scaledInputAmount,
                    'unit_cost' => $mCost,
                    'total_cost' => $rowCost
                ];
            }

            $energyRequired = (float)($b['energy_consumption'] ?? 50);
            $laborReq = (float)($b['labor_required'] ?? 10);
            $baseLaborCost = (float)($b['base_labor_cost'] ?? 50.0);
            $laborMult = (float)($b['labor_cost_multiplier'] ?? 1.0);
            $baseTransport = (float)($b['base_transport_cost'] ?? 20.0);
            $baseTax = (float)($b['base_tax_rate'] ?? 10.0);

            $logDiscount = max(0.5, 1.0 - (($researches['rLog'] - 1) * 0.05));
            $finDiscount = max(0.6, 1.0 - (($researches['rFin'] - 1) * 0.04));

            $totalLaborExpense = round($laborReq * $baseLaborCost * $laborMult, 2);
            $nakliyeCost = round($baseTransport * $logDiscount, 2);
            $vergiCost = round($baseTax * $finDiscount, 2);
            $totalProductionCost = round($inputMaterialCost + $totalLaborExpense + $nakliyeCost + $vergiCost, 2);
            $cashDeductionCost = round($totalLaborExpense + $nakliyeCost + $vergiCost, 2);
            $calculatedUnitCost = $productionAmount > 0 ? round($totalProductionCost / $productionAmount, 2) : 0;

            $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
            if ($phaseStartedTs <= 0) $phaseStartedTs = $now;
            
            $pausedTs = (int)($b['paused_ts'] ?? 0);
            $isPaused = (int)($b['is_paused'] ?? 0) === 1;

            if ($isPaused && $pausedTs > 0) {
                $elapsedTime = $pausedTs - $phaseStartedTs;
            } else {
                $elapsedTime = $now - $phaseStartedTs;
            }

            $producesId = $b['produces_product_id'];
            $currentProductStock = $inventoryMap[$producesId] ?? 0.0;
            $isProductSlotFull = ($currentProductStock + $productionAmount) > $productSlotLimit;

            $hasInput = !empty($inputs);
            $hasEnoughInput = true;
            $missingInputsList = [];

            // Ara depo (buffer) kontrolü veya aktiflik kontrolü
            $cycleProgressTime = $elapsedTime % $productionSec;
            $isCurrentlyProducing = ($elapsedTime < $productionSec && !empty($b['buffer_inventory']));

            if (!$isCurrentlyProducing) {
                foreach ($inputs as $input) {
                    $stock = $inventoryMap[$input['product_id']] ?? 0.0;
                    if ($stock < $input['amount']) {
                        $hasEnoughInput = false;
                        $missingInputsList[] = "{$input['product_name']} (Gereken: {$input['amount']}, Mevcut: {$stock})";
                    }
                }
            }

            $hasEnoughEnergy = ($currentEnergyStock >= $energyRequired);

            $warningMessage = '';
            if ($health < 50.0) {
                $warningMessage = "Kritik Kondisyon! Sağlık %50 altına düştüğü için üretim durduruldu.";
            } elseif ($isProductSlotFull) {
                $warningMessage = "Depo Dolu! Bu ürün için slot sınırı aşıldı.";
            } elseif (!$hasEnoughEnergy) {
                $warningMessage = "Yetersiz Enerji! Depoda gereken {$energyRequired} birim enerji yok.";
            } elseif ($hasInput && !$hasEnoughInput && !$isCurrentlyProducing) {
                $warningMessage = "Hammadde Bekleniyor: " . implode(', ', $missingInputsList);
            }

            $remSec = 0;
            $progress = 0;
            if ($health < 50.0 || $isProductSlotFull || !$hasEnoughEnergy || ($hasInput && !$hasEnoughInput && !$isCurrentlyProducing)) {
                $remSec = 0;
                $progress = 0;
                if ($health < 50.0) {
                    $barText = "ÜRETİM DURDURULDU (BAKIM GEREKLİ)";
                } elseif ($isProductSlotFull) {
                    $barText = "ÜRETİM DURDURULDU (DEPO DOLU)";
                } elseif (!$hasEnoughEnergy) {
                    $barText = "ÜRETİM DURDURULDU (ENERJİ YOK)";
                } else {
                    $barText = "HAMMADDE BEKLENİYOR...";
                }
                $barColor = "bg-danger";
            } elseif ($isPaused) {
                $remSec = max(0, $productionSec - ($elapsedTime % $productionSec));
                $progress = (($productionSec - $remSec) / $productionSec) * 100;
                $barText = "DURAKLATILDI";
                $barColor = "bg-warning";
            } else {
                $remSec = max(0, $productionSec - $cycleProgressTime);
                $progress = ($cycleProgressTime / $productionSec) * 100;
                $barText = "Üretim Aktif";
                $barColor = "bg-success progress-bar-animated progress-bar-striped";
            }

            // Dizi atamaları ve formula detayları
            $b['id'] = $b['building_id'];
            $b['formula_details'] = [
                'production_rate' => $productionRate,
                'soil_multiplier' => $soilMultiplier,
                'health_factor' => $healthFactor,
                'base_output_amount' => $baseOutputAmount,
                'unit_label' => $unitLabel,
                'has_enough_input' => ($hasEnoughInput || $isCurrentlyProducing),
                'inputs' => $inputs,
                'unit_cost' => $calculatedUnitCost,
                'production_cost' => $totalProductionCost,
                'cash_deduction_cost' => $cashDeductionCost,
                'warning_message' => $warningMessage,
                'energy_consumption' => $energyRequired,
                'labor_required' => (int)($b['labor_required'] ?? 10),
                'wear_rate' => (float)($b['wear_rate'] ?? 2.0),
                'total_labor_expense' => $totalLaborExpense,
                'base_transport_cost' => $nakliyeCost,
                'base_tax_rate' => $vergiCost,
                'input_material_cost' => $inputMaterialCost,
                'researches' => $researches
            ];

            $b['production_amount'] = $productionAmount;
            $b['repair_cost'] = round(((float)($b['base_cost'] ?? 1000) * $level) * ((100.0 - $health) / 100.0), 2);
            if ($b['repair_cost'] < 100 && $health < 100) $b['repair_cost'] = 100;

            $b['is_paused'] = $isPaused;
            $b['condition_health'] = $health;
            $b['image'] = $b['building_image'];
            $b['rem_sec'] = $remSec;
            $b['progress'] = $progress;
            $b['bar_text'] = $barText;
            $b['bar_color'] = $barColor;
            $b['cycle_seconds'] = $productionSec;

            // Loglar
            $logStmt = $db->prepare("SELECT bl.*, p.name as product_name FROM building_logs bl LEFT JOIN products p ON bl.product_id = p.id WHERE bl.building_id = ? ORDER BY bl.id DESC LIMIT 50");
            $logStmt->execute([$b['building_id']]);
            $allLogs = $logStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            
            $b['input_logs'] = array_filter($allLogs, fn($l) => $l['action_type'] === 'consumption');
            $b['cost_logs'] = array_filter($allLogs, fn($l) => in_array($l['action_type'], ['cost', 'repair']));
            $b['output_logs'] = array_filter($allLogs, fn($l) => $l['action_type'] === 'production');
            $b['wear_logs'] = array_filter($allLogs, fn($l) => $l['action_type'] === 'wear');

            $b['totals'] = [
                'consumed' => array_sum(array_column($b['input_logs'], 'amount')),
                'cost' => array_sum(array_column($b['cost_logs'], 'amount')),
                'produced' => array_sum(array_column($b['output_logs'], 'amount')),
                'wear' => count($b['wear_logs'])
            ];

            $my_buildings[] = $b;
        }

        $inventoryStmt = $db->prepare("SELECT inv.*, p.name as product_name FROM user_inventories inv JOIN products p ON inv.product_id = p.id WHERE inv.user_id = ?");
        $inventoryStmt->execute([$userId]);
        $inventory = $inventoryStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $building_types = $db->query("SELECT bt.*, p.name as product_name, p.image as product_image, p.unit_cost as product_base_cost FROM building_types bt LEFT JOIN products p ON bt.produces_product_id = p.id ORDER BY bt.name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $provinces = $db->query("SELECT * FROM provinces ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM game_settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $this->view('app/playerbuilding/index', [
            'my_buildings' => $my_buildings,
            'building_types' => $building_types,
            'provinces' => $provinces,
            'player' => $player,
            'game_settings' => $settings,
            'inventory' => $inventory,
            'active_category' => $activeCategory,
            'now' => $now,
            'warehouse_summary' => [
                'level' => $warehouseLevel,
                'slot_limit' => $productSlotLimit,
                'upgrade_cost' => $warehouseUpgradeCost
            ]
        ]);
    }

    private function processProductionCycles($db, $userId, $now) {
        $productionSeconds = 600;

        $playerRow = $db->prepare("SELECT warehouse_level, money FROM users WHERE id = ?");
        $playerRow->execute([$userId]);
        $playerRowData = $playerRow->fetch(\PDO::FETCH_ASSOC);
        $warehouseLevel = (int)($playerRowData['warehouse_level'] ?? 1);
        $productSlotLimit = $warehouseLevel * 500.00;

        $researches = $this->getPlayerResearches($db, $userId);

        $energyProdStmt = $db->prepare("SELECT id FROM products WHERE name LIKE '%Enerji%' OR name LIKE '%Elektrik%' LIMIT 1");
        $energyProdStmt->execute();
        $energyProductId = $energyProdStmt->fetchColumn() ?: 0;

        $stmt = $db->prepare("SELECT pb.*, pb.id as building_id, 
                                     UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
                                     bt.produces_product_id, bt.category,
                                     bt.id as building_type_id,
                                     p.name as product_name, p.unit_cost as product_base_cost,
                                     prov.mine_multiplier, prov.labor_cost_multiplier,
                                     prov.agri_multiplier, prov.ind_multiplier,
                                     mlb.production_rate, mlb.energy_consumption, mlb.wear_rate, mlb.labor_required, mlb.base_labor_cost,
                                     mlb.base_transport_cost, mlb.base_tax_rate
                              FROM user_buildings pb 
                              JOIN building_types bt ON pb.building_type_id = bt.id 
                              LEFT JOIN products p ON bt.produces_product_id = p.id
                              JOIN provinces prov ON pb.province_id = prov.id 
                              LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
                              WHERE pb.user_id = ?");
        $stmt->execute([$userId]);
        $buildings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($buildings as $b) {
            if ((int)($b['is_paused'] ?? 0) === 1) continue;
            
            $health = (float)($b['condition_health'] ?? 100.0);
            $bId = $b['building_id'];

            if ($health < 50.0) {
                $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
                continue; 
            }

            $producesId = $b['produces_product_id'];
            $productionRate = (float)($b['production_rate'] ?? 1.0);
            $energyRequired = (float)($b['energy_consumption'] ?? 50);

            $rawInputs = $this->getBuildingInputs($db, $b['building_type_id']);
            $outputs = $this->getBuildingOutputs($db, $b['building_type_id']);
            $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;

            $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
            if ($phaseStartedTs <= 0) $phaseStartedTs = $now;
            $elapsed = $now - $phaseStartedTs;

            $bufferData = !empty($b['buffer_inventory']) ? json_decode($b['buffer_inventory'], true) : [];

            // 1. ADIM: Ara depo (buffer) tamamen boşsa VE tesisin girdisi varsa -> Merkez depodan hammadde çekmeyi dene
            if (empty($bufferData) && !empty($rawInputs)) {
                $hasAllInputsNow = true;
                $tempBuffer = [];
                foreach ($rawInputs as $inp) {
                    $scaledReq = round($inp['required_amount'] * $productionRate, 2);
                    $chkStock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $chkStock->execute([$userId, $inp['product_id']]);
                    $stockVal = (float)$chkStock->fetchColumn();
                    
                    if ($stockVal < $scaledReq) {
                        $hasAllInputsNow = false;
                        break;
                    }
                    $tempBuffer[$inp['product_id']] = $scaledReq;
                }

                if (!$hasAllInputsNow) {
                    // Yetersiz hammadde (Merkezde yok): Süreyi güncel tut ve bekle
                    $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
                    continue;
                }

                // Hammaddeleri merkez depodan düş ve tesise (buffer'a) kaydet
                foreach ($tempBuffer as $pId => $qty) {
                    $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                       ->execute([$qty, $userId, $pId]);

                    $prodNameStmt = $db->prepare("SELECT name FROM products WHERE id = ?");
                    $prodNameStmt->execute([$pId]);
                    $inName = $prodNameStmt->fetchColumn();

                    $consumeLog = "{$qty} adet {$inName} tesise çekilerek ara depoya alındı.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
                       ->execute([$bId, $pId, $qty, $consumeLog]);
                }

                // Buffer alanını doldur ve sayacın sıfırdan başlamasını sağla
                $db->prepare("UPDATE user_buildings SET buffer_inventory = ?, phase_started_at = NOW() WHERE id = ?")
                   ->execute([json_encode($tempBuffer), $bId]);
                
                // Süreyi sıfırladığımız için bu turda üretim yapmadan devam et (yeni sayaç başladı)
                continue; 
            }

            // Eğer tesiste girdi var ama merkezde hammadde yok VE buffer da boşsa -> Bekle
            if (!empty($rawInputs) && empty($bufferData)) {
                continue;
            }

            if ($elapsed <= 0) continue;
            $completedCycles = floor($elapsed / $productionSeconds);
            if ($completedCycles <= 0) continue;

            $category = strtolower(trim($b['category'] ?? ''));
            $soilMultiplier = 1.0;
            if (str_contains($category, 'agri') || str_contains($category, 'tarım')) {
                $soilMultiplier = (float)$b['agri_multiplier'] * (1.0 + (($researches['rBio'] - 1) * 0.02));
            } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) {
                $soilMultiplier = (float)$b['ind_multiplier'] ?? 1.0;
            } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
                $soilMultiplier = (float)$b['mine_multiplier'] ?? 1.0;
            }
            if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

            $healthFactor = max(0, $health / 100.0);
            $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);
            $prodAmountPerCycle = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);

            $laborReq = (float)($b['labor_required'] ?? 10);
            $baseLaborCost = (float)($b['base_labor_cost'] ?? 50.0);
            $laborMult = (float)($b['labor_cost_multiplier'] ?? 1.0);
            $baseTransport = (float)($b['base_transport_cost'] ?? 20.0);
            $baseTax = (float)($b['base_tax_rate'] ?? 10.0);

            $logDiscount = max(0.5, 1.0 - (($researches['rLog'] - 1) * 0.05));
            $finDiscount = max(0.6, 1.0 - (($researches['rFin'] - 1) * 0.04));
            $cashCostPerCycle = round(($laborReq * $baseLaborCost * $laborMult) + ($baseTransport * $logDiscount) + ($baseTax * $finDiscount), 2);
            $wearRatePerCycle = (float)($b['wear_rate'] ?? 2.0);

            try {
                $db->beginTransaction();
                $processedCyclesCount = 0;

                for ($i = 0; $i < $completedCycles; $i++) {
                    $currentHealth = (float)$db->query("SELECT condition_health FROM user_buildings WHERE id = {$bId}")->fetchColumn();
                    if ($currentHealth < 50.0) break;

                    // Enerji Kontrolü
                    if ($energyProductId > 0 && $energyRequired > 0) {
                        $enLock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                        $enLock->execute([$userId, $energyProductId]);
                        $enStock = (float)$enLock->fetchColumn();
                        if ($enStock < $energyRequired) break;

                        $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$energyRequired, $userId, $energyProductId]);

                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
                           ->execute([$bId, $energyProductId, $energyRequired, "{$energyRequired} birim enerji harcandı."]);
                    }

                    // Depo Kapasite Kontrolü
                    $invCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                    $invCheck->execute([$userId, $producesId]);
                    $currentStock = (float)($invCheck->fetchColumn() ?? 0);

                    if (($currentStock + $prodAmountPerCycle) > $productSlotLimit) {
                        break; 
                    }

                    // Bütçe Kontrolü
                    $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                    if ($cashCostPerCycle > 0 && $playerMoney < $cashCostPerCycle) break; 

                    // Maliyet Hesaplama (Buffer'daki hammadde zaten merkezden düşülmüştü, maliyetini envanterimsi maliyetten hesaplıyoruz)
                    $inputMaterialCost = 0;
                    foreach ($rawInputs as $inp) {
                        $scaledReq = round($inp['required_amount'] * $productionRate, 2);
                        $itemCostQuery = $db->prepare("SELECT unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                        $itemCostQuery->execute([$userId, $inp['product_id']]);
                        $itemCostRow = $itemCostQuery->fetch(\PDO::FETCH_ASSOC);
                        $mCost = (float)($itemCostRow['unit_cost'] ?? 0);
                        if ($mCost <= 0) {
                            $pCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                            $pCostStmt->execute([$inp['product_id']]);
                            $mCost = (float)$pCostStmt->fetchColumn();
                        }
                        $inputMaterialCost += ($mCost * $scaledReq);
                    }

                    $totalProductionCost = round($inputMaterialCost + $cashCostPerCycle, 2);
                    $calculatedUnitCost = $prodAmountPerCycle > 0 ? round($totalProductionCost / $prodAmountPerCycle, 2) : 0;

                    // Ürünü Envantere Ekle / Güncelle
                    $invRow = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $invRow->execute([$userId, $producesId]);
                    $existingInv = $invRow->fetch(\PDO::FETCH_ASSOC);

                    if ($existingInv) {
                        $oldQty = (float)$existingInv['quantity'];
                        $oldCost = (float)$existingInv['unit_cost'];
                        $newTotalQty = $oldQty + $prodAmountPerCycle;
                        $blendedUnitCost = $newTotalQty > 0 ? (($oldQty * $oldCost) + ($prodAmountPerCycle * $calculatedUnitCost)) / $newTotalQty : $calculatedUnitCost;

                        $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$newTotalQty, $blendedUnitCost, $userId, $producesId]);
                    } else {
                        $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                           ->execute([$userId, $producesId, $prodAmountPerCycle, $calculatedUnitCost]);
                    }

                    // Nakit Gideri Kes
                    if ($cashCostPerCycle > 0) {
                        $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$cashCostPerCycle, $userId]);
                        $costLog = "{$prodAmountPerCycle} adet {$b['product_name']} üretimi için işletme gideri kesildi: {$cashCostPerCycle}₺.";
                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                           ->execute([$bId, $producesId, $cashCostPerCycle, $costLog]);
                    }

                    // Bina Sağlığı
                    $newHealth = max(0.0, $health - $wearRatePerCycle);
                    $db->prepare("UPDATE user_buildings SET condition_health = ? WHERE id = ?")->execute([$newHealth, $bId]);

                    // Üretim Logu
                    $logDesc = "Üretim tamamlandı: {$prodAmountPerCycle} adet {$b['product_name']} depoya eklendi.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                       ->execute([$bId, $producesId, $prodAmountPerCycle, $logDesc]);

                    // KRİTİK: Döngü bittiği için buffer'ı temizle ki yeni döngüde yeniden merkezden hammadde çekebilsin
                    $db->prepare("UPDATE user_buildings SET buffer_inventory = NULL WHERE id = ?")->execute([$bId]);
                    $bufferData = []; // Bellekteki buffer'ı da sıfırla

                    $processedCyclesCount++;
                }

                if ($processedCyclesCount > 0) {
                    $advancedSeconds = $processedCyclesCount * $productionSeconds;
                    $db->prepare("UPDATE user_buildings SET phase_started_at = DATE_ADD(phase_started_at, INTERVAL ? SECOND) WHERE id = ?")
                       ->execute([$advancedSeconds, $bId]);
                } else {
                    $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
                }

                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
            }
        }
    }

    // Hammadde Detay / Yönetim Verilerini JSON olarak döndüren metot (Modal için)
    public function getInventoryItemDetail() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['product_id'])) {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

            // 1. Ürün ve envanter bilgisi
            $invStmt = $db->prepare("SELECT inv.*, p.name as product_name, p.unit_cost as base_cost, p.type FROM user_inventories inv JOIN products p ON inv.product_id = p.id WHERE inv.user_id = ? AND inv.product_id = ?");
            $invStmt->execute([$userId, $productId]);
            $item = $invStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                echo json_encode(['status' => 'error', 'message' => 'Ürün bulunamadı.']);
                exit;
            }

            // 2. SADECE BU ÜRÜNE AİT log kayıtları (Üretim, Tüketim/Ara Depoya Çekilme vb.)
            $logStmt = $db->prepare("SELECT bl.*, b.id as b_id, bt.name as building_name 
                                     FROM building_logs bl 
                                     JOIN user_buildings b ON bl.building_id = b.id 
                                     JOIN building_types bt ON b.building_type_id = bt.id 
                                     WHERE b.user_id = ? AND bl.product_id = ? 
                                     ORDER BY bl.id DESC LIMIT 25");
            $logStmt->execute([$userId, $productId]);
            $logs = $logStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Cost (Harcanan Para) logları
            $costLogStmt = $db->prepare("SELECT bl.* FROM building_logs bl 
                                         JOIN user_buildings b ON bl.building_id = b.id 
                                         WHERE b.user_id = ? AND bl.product_id = ? AND (bl.action_type = 'cost' OR bl.action_type = 'repair') 
                                         ORDER BY bl.id DESC LIMIT 10");
            $costLogStmt->execute([$userId, $productId]);
            $costLogs = $costLogStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Enerji Tüketim logları
            $energyLogStmt = $db->prepare("SELECT bl.* FROM building_logs bl 
                                           JOIN user_buildings b ON bl.building_id = b.id 
                                           WHERE b.user_id = ? AND bl.product_id = ? AND bl.action_type = 'consumption' 
                                           ORDER BY bl.id DESC LIMIT 10");
            $energyLogStmt->execute([$userId, $productId]);
            $energyLogs = $energyLogStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Grafik 1 Verileri (Üretim vs Ara Depoya Çekilme/Consumption)
            $ch1Labels = []; $ch1Prod = []; $ch1Cons = [];
            foreach (array_reverse($logs) as $log) {
                $ch1Labels[] = date('H:i:s', strtotime($log['created_at']));
                
                if ($log['action_type'] === 'production') {
                    $ch1Prod[] = (float)$log['amount'];
                    $ch1Cons[] = 0;
                } elseif ($log['action_type'] === 'consumption') {
                    $ch1Prod[] = 0;
                    $ch1Cons[] = (float)$log['amount']; // Tesise çekilen miktar
                } else {
                    $ch1Prod[] = 0;
                    $ch1Cons[] = 0;
                }
            }

            // Grafik 2 Verileri (Cost / Harcanan Para)
            $ch2Labels = []; $ch2Data = [];
            foreach (array_reverse($costLogs) as $clog) {
                $ch2Labels[] = date('H:i:s', strtotime($clog['created_at']));
                $ch2Data[] = (float)$clog['amount'];
            }

            // Grafik 3 Verileri (Enerji / Tüketim)
            $ch3Labels = []; $ch3Data = [];
            foreach (array_reverse($energyLogs) as $elog) {
                $ch3Labels[] = date('H:i:s', strtotime($elog['created_at']));
                $ch3Data[] = (float)$elog['amount'];
            }

            echo json_encode([
                'status' => 'success',
                'item' => $item,
                'logs' => $logs,
                'charts' => [
                    'prod_cons' => ['labels' => $ch1Labels, 'production' => $ch1Prod, 'consumption' => $ch1Cons],
                    'cost' => ['labels' => $ch2Labels, 'data' => $ch2Data],
                    'energy' => ['labels' => $ch3Labels, 'data' => $ch3Data]
                ]
            ]);
            exit;
        }
    }

    // Hammadde Al / Sat İşlemi
    public function tradeInventoryItem() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $productId = (int)($_POST['product_id'] ?? 0);
            $actionType = $_POST['action_type'] ?? ''; // 'buy' veya 'sell'
            $amount = (float)($_POST['amount'] ?? 0);

            if ($amount <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz miktar!']);
                exit;
            }

            try {
                $db->beginTransaction();

                // Ürün baz fiyatını al
                $pStmt = $db->prepare("SELECT unit_cost, name FROM products WHERE id = ?");
                $pStmt->execute([$productId]);
                $prod = $pStmt->fetch(\PDO::FETCH_ASSOC);
                if (!$prod) throw new \Exception('Ürün bulunamadı.');

                $basePrice = (float)($prod['unit_cost'] > 0 ? $prod['unit_cost'] : 10.00);

                // Kullanıcı bütçesini al
                $uStmt = $db->prepare("SELECT money FROM users WHERE id = ? FOR UPDATE");
                $uStmt->execute([$userId]);
                $userMoney = (float)$uStmt->fetchColumn();

                // Envanter kontrolü
                $iStmt = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                $iStmt->execute([$userId, $productId]);
                $inv = $iStmt->fetch(\PDO::FETCH_ASSOC);
                $currentStock = (float)($inv['quantity'] ?? 0);

                if ($actionType === 'buy') {
                    $totalCost = $basePrice * $amount * 1.15; // Alışta %15 piyasa kâr marjı
                    if ($userMoney < $totalCost) throw new \Exception('Yetersiz bütçe!');

                    $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$totalCost, $userId]);

                    if ($inv) {
                        $newQty = $currentStock + $amount;
                        $newCost = (($currentStock * (float)$inv['unit_cost']) + ($totalCost)) / $newQty;
                        $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$newQty, $newCost, $userId, $productId]);
                    } else {
                        $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                           ->execute([$userId, $productId, $amount, $basePrice * 1.15]);
                    }
                } elseif ($actionType === 'sell') {
                    if ($currentStock < $amount) throw new \Exception('Depoda yeterli ürün yok!');

                    $totalRevenue = $basePrice * $amount * 0.85; // Satışta %15 komisyon düşümü
                    $db->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$totalRevenue, $userId]);

                    $newQty = $currentStock - $amount;
                    if ($newQty <= 0) {
                        $db->prepare("DELETE FROM user_inventories WHERE user_id = ? AND product_id = ?")->execute([$userId, $productId]);
                    } else {
                        $db->prepare("UPDATE user_inventories SET quantity = ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$newQty, $userId, $productId]);
                    }
                } else {
                    throw new \Exception('Geçersiz işlem tipi!');
                }

                $db->commit();
                echo json_encode(['status' => 'success', 'message' => 'İşlem başarıyla gerçekleştirildi.']);
                exit;
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
        }
    }

    private function getBuildingInputs($db, $buildingTypeId) {
        $stmt = $db->prepare("SELECT bi.*, p.name as product_name FROM building_inputs bi JOIN products p ON bi.product_id = p.id WHERE bi.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $inputs = [];
        foreach ($rows as $row) {
            $inputs[] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'required_amount' => (float)$row['amount']
            ];
        }
        return $inputs;
    }

    private function getBuildingOutputs($db, $buildingTypeId) {
        $stmt = $db->prepare("SELECT bo.*, p.name as product_name FROM building_outputs bo JOIN products p ON bo.product_id = p.id WHERE bo.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $outputs = [];
        foreach ($rows as $row) {
            $outputs[] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'amount' => (float)$row['amount']
            ];
        }
        return $outputs;
    }

    private function getPlayerResearches($db, $userId) {
        $stmt = $db->prepare("SELECT * FROM user_researches WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'rBio'  => (int)($row['bio_level'] ?? 1),
            'rInd'  => (int)($row['ind_level'] ?? 1),
            'rTech' => (int)($row['tech_level'] ?? 1),
            'rLog'  => (int)($row['log_level'] ?? 1),
            'rFin'  => (int)($row['fin_level'] ?? 1),
        ];
    }

    public function togglePause() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);

            $stmt = $db->prepare("SELECT is_paused, paused_at FROM user_buildings WHERE id = ? AND user_id = ?");
            $stmt->execute([$buildingId, $userId]);
            $b = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($b) {
                $isPaused = (int)$b['is_paused'];
                if ($isPaused === 1) {
                    $db->prepare("UPDATE user_buildings 
                                  SET is_paused = 0, 
                                      phase_started_at = DATE_ADD(phase_started_at, INTERVAL TIMESTAMPDIFF(SECOND, paused_at, NOW()) SECOND),
                                      paused_at = NULL 
                                  WHERE id = ?")
                       ->execute([$buildingId]);
                } else {
                    $db->prepare("UPDATE user_buildings 
                                  SET is_paused = 1, 
                                      paused_at = NOW() 
                                  WHERE id = ?")
                       ->execute([$buildingId]);
                }
            }
            redirect('/app/playerbuilding');
        }
    }

    public function repair() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);

            $stmt = $db->prepare("SELECT ub.*, bt.base_cost FROM user_buildings ub JOIN building_types bt ON ub.building_type_id = bt.id WHERE ub.id = ? AND ub.user_id = ?");
            $stmt->execute([$buildingId, $userId]);
            $building = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($building) {
                $health = (float)$building['condition_health'];
                if ($health < 100.0) {
                    $repairCost = round(((float)$building['base_cost'] * (int)$building['level']) * ((100.0 - $health) / 100.0), 2);
                    if ($repairCost < 100) $repairCost = 100;

                    $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                    if ($playerMoney >= $repairCost) {
                        try {
                            $db->beginTransaction();
                            $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$repairCost, $userId]);
                            $db->prepare("UPDATE user_buildings SET condition_health = 100.00, phase_started_at = NOW() WHERE id = ?")->execute([$buildingId]);
                            
                            $repairLogDesc = "Tesis bakımı yapıldı, kondisyon %100'e çıkarıldı. Ödenen Tamirat Bedeli: {$repairCost}₺";
                            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'repair', NULL, ?, ?, NOW())")
                               ->execute([$buildingId, $repairCost, $repairLogDesc]);

                            $db->commit();
                        } catch (\Exception $e) {
                            if ($db->inTransaction()) $db->rollBack();
                        }
                    }
                }
            }
            redirect('/app/playerbuilding');
        }
    }

    public function upgradeWarehouse() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            $player = $db->prepare("SELECT money, warehouse_level FROM users WHERE id = ?");
            $player->execute([$userId]);
            $p = $player->fetch(\PDO::FETCH_ASSOC);

            if ($p) {
                $currentLevel = (int)$p['warehouse_level'];
                $nextLevel = $currentLevel + 1;

                $meta = $db->prepare("SELECT * FROM warehouse_level_matrix WHERE level = ?");
                $meta->execute([$nextLevel]);
                $metaRow = $meta->fetch(\PDO::FETCH_ASSOC);

                $upgradeCost = $metaRow ? (float)$metaRow['upgrade_cost'] : ($currentLevel * 10000);

                if ((float)$p['money'] >= $upgradeCost) {
                    try {
                        $db->beginTransaction();
                        $db->prepare("UPDATE users SET money = money - ?, warehouse_level = ? WHERE id = ?")
                           ->execute([$upgradeCost, $nextLevel, $userId]);
                        $db->commit();
                    } catch (\Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                    }
                }
            }
            redirect('/app/playerbuilding');
        }
    }

    public function finish() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);

            // İstek AJAX mı kontrol edelim ki doğru yanıt dönebilelim
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            $stmt = $db->prepare("SELECT pb.*, bt.produces_product_id, bt.category,
                                         prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, prov.labor_cost_multiplier,
                                         mlb.production_rate, mlb.labor_required, mlb.base_labor_cost, mlb.base_transport_cost, mlb.base_tax_rate
                                  FROM user_buildings pb 
                                  JOIN building_types bt ON pb.building_type_id = bt.id 
                                  JOIN provinces prov ON pb.province_id = prov.id 
                                  LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
                                  WHERE pb.id = ? AND pb.user_id = ?");
            $stmt->execute([$buildingId, $userId]);
            $b = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$b) {
                if ($isAjax) { echo json_encode(['status' => 'error', 'message' => 'Tesis bulunamadı.']); exit; }
                redirect('/app/playerbuilding');
            }

            try {
                $db->beginTransaction();
                $researches = $this->getPlayerResearches($db, $userId);

                $category = strtolower(trim($b['category'] ?? ''));
                $soilMultiplier = 1.0;
                if (str_contains($category, 'agri') || str_contains($category, 'tarım')) {
                    $soilMultiplier = (float)$b['agri_multiplier'] * (1.0 + (($researches['rBio'] - 1) * 0.02));
                }
                elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) $soilMultiplier = (float)$b['ind_multiplier'];
                elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) $soilMultiplier = (float)$b['mine_multiplier'];
                if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

                $health = (float)($b['condition_health'] ?? 100.0);
                if ($health < 50.0) throw new \Exception('Tesis sağlığı %50 altında, işlem yapılamaz!');

                $healthFactor = max(0, $health / 100.0);
                $productionRate = (float)$b['production_rate'];
                $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);

                $outputs = $this->getBuildingOutputs($db, $b['building_type_id']);
                $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;

                $prodAmount = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);
                
                $laborReq = (float)$b['labor_required'];
                $baseLaborCost = (float)$b['base_labor_cost'];
                $laborMult = (float)$b['labor_cost_multiplier'];
                $baseTransport = (float)$b['base_transport_cost'];
                $baseTax = (float)$b['base_tax_rate'];

                // 1. HAMMADDE KONTROLÜ (Ara Depo veya Merkez Envanter)
                $rawInputs = $this->getBuildingInputs($db, $b['building_type_id']);
                $inputMaterialCost = 0;
                $bufferData = !empty($b['buffer_inventory']) ? json_decode($b['buffer_inventory'], true) : [];

                foreach ($rawInputs as $input) {
                    $scaledReq = round($input['required_amount'] * $productionRate, 2);

                    // Eğer ara depoda (buffer) hammadde zaten çekilmişse oradan düş, yoksa merkez depodan kontrol edip düş
                    if (isset($bufferData[$input['product_id']]) && $bufferData[$input['product_id']] >= $scaledReq) {
                        // Hammadde zaten döngü başında buffer'a alınmış, ekstra düşmeye gerek yok
                    } else {
                        // Merkez depodan kontrol et ve düş
                        $chk = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                        $chk->execute([$userId, $input['product_id']]);
                        $invItem = $chk->fetch(\PDO::FETCH_ASSOC);
                        $currentStock = (float)($invItem['quantity'] ?? 0);
                        
                        if ($currentStock < $scaledReq) {
                            throw new \Exception('Yetersiz Hammadde! Merkez depoda gerekli girdi bulunmuyor.');
                        }

                        $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$scaledReq, $userId, $input['product_id']]);
                    }

                    // Maliyet hesabı için ürün maliyetini al
                    $itemCostQuery = $db->prepare("SELECT unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $itemCostQuery->execute([$userId, $input['product_id']]);
                    $invItemCost = $itemCostQuery->fetch(\PDO::FETCH_ASSOC);
                    $itemCost = (float)($invItemCost['unit_cost'] ?? 0);
                    
                    if ($itemCost <= 0) {
                        $pCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                        $pCostStmt->execute([$input['product_id']]);
                        $itemCost = (float)$pCostStmt->fetchColumn();
                    }
                    $inputMaterialCost += ($itemCost * $scaledReq);
                }

                $logDiscount = max(0.5, 1.0 - (($researches['rLog'] - 1) * 0.05));
                $finDiscount = max(0.6, 1.0 - (($researches['rFin'] - 1) * 0.04));

                $totalLaborExpense = round($laborReq * $baseLaborCost * $laborMult, 2);
                $nakliyeCost = round($baseTransport * $logDiscount, 2);
                $vergiCost = round($baseTax * $finDiscount, 2);
                
                $totalProductionCost = round($inputMaterialCost + $totalLaborExpense + $nakliyeCost + $vergiCost, 2);
                $calculatedUnitCost = $prodAmount > 0 ? round($totalProductionCost / $prodAmount, 2) : 0;

                $cashDeductionCost = round($totalLaborExpense + $nakliyeCost + $vergiCost, 2);
                $finishCost = round($cashDeductionCost * 0.20, 2);

                $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                if ($playerMoney < $finishCost) throw new \Exception('Yetersiz bütçe!');

                $pInfo = $db->prepare("SELECT warehouse_level FROM users WHERE id = ?");
                $pInfo->execute([$userId]);
                $wLevel = (int)($pInfo->fetchColumn() ?? 1);
                $slotLimit = $wLevel * 500.00;

                $producesId = $b['produces_product_id'];
                $invCheck = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                $invCheck->execute([$userId, $producesId]);
                $invRow = $invCheck->fetch(\PDO::FETCH_ASSOC);
                $currentStock = (float)($invRow['quantity'] ?? 0);

                if (($currentStock + $prodAmount) > $slotLimit) {
                    throw new \Exception('Depo Slot Kapasitesi Yetersiz!');
                }

                if ($finishCost > 0) {
                    $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$finishCost, $userId]);
                    $costLog = "{$prodAmount} adet ürün hızlı bitirmek için maliyet ({$finishCost}₺) ödendi.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                       ->execute([$buildingId, $producesId, $finishCost, $costLog]);
                }

                if (!empty($producesId) && $prodAmount > 0) {
                    if ($invRow) {
                        $oldQty = (float)$invRow['quantity'];
                        $oldCost = (float)$invRow['unit_cost'];
                        $newTotalQty = $oldQty + $prodAmount;
                        $blendedUnitCost = $newTotalQty > 0 ? (($oldQty * $oldCost) + ($prodAmount * $calculatedUnitCost)) / $newTotalQty : $calculatedUnitCost;

                        $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$newTotalQty, $blendedUnitCost, $userId, $producesId]);
                    } else {
                        $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                           ->execute([$userId, $producesId, $prodAmount, $calculatedUnitCost]);
                    }

                    $logDesc = "Üretim tamamlandı (Hızlı): {$prodAmount} adet ürün depoya eklendi.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                       ->execute([$buildingId, $producesId, $prodAmount, $logDesc]);
                }

                // Sayaç zamanını yenile ve ara depoyu (buffer) temizle
                $db->prepare("UPDATE user_buildings SET phase_started_at = NOW(), buffer_inventory = NULL WHERE id = ?")->execute([$buildingId]);
                
                $db->commit();

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Üretim başarıyla tamamlandı.']);
                    exit;
                }
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                    exit;
                }
                // Hata mesajını yönetmek isterseniz buraya ekleme yapabilirsiniz
            }
            redirect('/app/playerbuilding');
        }
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            $buildingTypeId = (int)($_POST['building_id'] ?? $_POST['building_type_id'] ?? 0);
            $provinceId = (int)($_POST['province_id'] ?? 0);

            try {
                $bType = $db->prepare("SELECT * FROM building_types WHERE id = ?");
                $bType->execute([$buildingTypeId]);
                $bData = $bType->fetch(\PDO::FETCH_ASSOC);
                $cost = (float)($bData['base_cost'] ?? 1000);

                $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                if ($playerMoney < $cost) throw new \Exception('Yetersiz bütçe!');

                $db->beginTransaction();
                $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$cost, $userId]);

                $db->prepare("INSERT INTO user_buildings (user_id, building_type_id, province_id, level, condition_health, status, is_paused, production_status, phase_started_at, created_at) VALUES (?, ?, ?, 1, 100.0, 'active', 0, 'waiting_for_input', NOW(), NOW())")
                    ->execute([$userId, $buildingTypeId, $provinceId]);
                
                $db->commit();

                echo json_encode(['status' => 'success']);
                exit;
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                http_response_code(400);
                echo $e->getMessage();
                exit;
            }
        }
    }

    public function upgrade() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);

            $stmt = $db->prepare("SELECT ub.*, bt.base_cost FROM user_buildings ub JOIN building_types bt ON ub.building_type_id = bt.id WHERE ub.id = ? AND ub.user_id = ?");
            $stmt->execute([$buildingId, $userId]);
            $building = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($building) {
                $currentLevel = (int)$building['level'];
                if ($currentLevel < 10) {
                    $nextLevel = $currentLevel + 1;
                    $upgradeCost = (float)$building['base_cost'] * $nextLevel * 1.5;

                    $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                    if ($playerMoney >= $upgradeCost) {
                        try {
                            $db->beginTransaction();
                            $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$upgradeCost, $userId]);
                            $db->prepare("UPDATE user_buildings SET level = ? WHERE id = ?")->execute([$nextLevel, $buildingId]);
                            $db->commit();
                        } catch (\Exception $e) {
                            if ($db->inTransaction()) $db->rollBack();
                        }
                    }
                }
            }
            redirect('/app/playerbuilding');
        }
    }

    public function sell($id = null) {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $buildingId = (int)($id ?? $_POST['building_id'] ?? 0);

        $stmt = $db->prepare("SELECT ub.*, bt.base_cost FROM user_buildings ub JOIN building_types bt ON ub.building_type_id = bt.id WHERE ub.id = ? AND ub.user_id = ?");
        $stmt->execute([$buildingId, $userId]);
        $building = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($building) {
            $refundMoney = (float)($building['base_cost'] / 2);

            try {
                $db->beginTransaction();
                $db->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$refundMoney, $userId]);
                $db->prepare("DELETE FROM building_logs WHERE building_id = ?")->execute([$buildingId]);
                $db->prepare("DELETE FROM user_buildings WHERE id = ?")->execute([$buildingId]);
                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
            }
        }
        redirect('/app/playerbuilding');
    }
}
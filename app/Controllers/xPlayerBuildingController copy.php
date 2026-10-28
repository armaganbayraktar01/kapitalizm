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
                         bt.id as building_type_id, bt.name as building_name, bt.category, bt.complexity_type, 
                         bt.base_cost, bt.unit_type, bt.image as building_image, 
                         bt.produces_product_id,
                         p.name as product_name, p.image as product_image, p.unit_cost as product_base_cost,
                         prov.name as province_name, prov.population, prov.labor_cost_multiplier,
                         prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, prov.latitude, prov.longitude,
                         mlb.production_rate, mlb.energy_consumption, mlb.water_consumption, mlb.labor_required, mlb.wear_rate,
                         mlb.base_labor_cost, mlb.base_transport_cost, mlb.base_tax_rate
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
            
            if ($health < 50.0) {
                $productionAmount = 0.0;
            }

            $unitLabel = $b['unit_type'] ?? 'Adet';
            $rawInputs = $this->getBuildingInputs($db, $b['building_type_id']);

            $inputs = [];
            $inputMaterialCost = 0;
            foreach ($rawInputs as $inp) {
                $scaledInputAmount = round($inp['required_amount'] * $productionRate, 2);
                
                // MALİYET ÇÖZÜMÜ: Oyuncunun envanterindeki kayıtlı maliyet varsa onu baz al, yoksa ürünün taban maliyetini al
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

            foreach ($inputs as $input) {
                $stock = $inventoryMap[$input['product_id']] ?? 0.0;
                if ($stock < $input['amount']) {
                    $hasEnoughInput = false;
                    $missingInputsList[] = "{$input['product_name']} (Gereken: {$input['amount']}, Mevcut: {$stock})";
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
            } elseif ($hasInput && !$hasEnoughInput) {
                $warningMessage = "Hammadde Bekleniyor: " . implode(', ', $missingInputsList);
            }

            $remSec = 0;
            $progress = 0;

            if ($health < 50.0 || $isProductSlotFull || !$hasEnoughEnergy || ($hasInput && !$hasEnoughInput)) {
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
                $cycleProgressTime = $elapsedTime % $productionSec;
                $remSec = max(0, $productionSec - $cycleProgressTime);
                $progress = ($cycleProgressTime / $productionSec) * 100;
                $barText = "Üretim Aktif";
                $barColor = "bg-success progress-bar-animated progress-bar-striped";
            }

            $b['id'] = $b['building_id'];
            $b['formula_details'] = [
                'production_rate' => $productionRate,
                'soil_multiplier' => $soilMultiplier,
                'health_factor' => $healthFactor,
                'base_output_amount' => $baseOutputAmount,
                'unit_label' => $unitLabel,
                'has_enough_input' => $hasEnoughInput,
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
                                     bt.id as building_type_id, bt.produces_product_id, bt.category,
                                     p.name as product_name, p.unit_cost as product_base_cost,
                                     prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, prov.labor_cost_multiplier,
                                     mlb.production_rate, mlb.energy_consumption, mlb.wear_rate, mlb.labor_required, mlb.base_labor_cost, mlb.base_transport_cost, mlb.base_tax_rate
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

            if ($energyProductId > 0 && $energyRequired > 0) {
                $enCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                $enCheck->execute([$userId, $energyProductId]);
                $currentEnergy = (float)$enCheck->fetchColumn();
                if ($currentEnergy < $energyRequired) {
                    $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
                    continue;
                }
            }

            $invCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
            $invCheck->execute([$userId, $producesId]);
            $currentStock = (float)($invCheck->fetchColumn() ?? 0);

            $category = strtolower(trim($b['category']));
            $soilMultiplier = 1.0;
            if (str_contains($category, 'agri') || str_contains($category, 'tarım')) {
                $soilMultiplier = (float)$b['agri_multiplier'] * (1.0 + (($researches['rBio'] - 1) * 0.02));
            }
            elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) $soilMultiplier = (float)$b['ind_multiplier'] ?? 1.0;
            elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) $soilMultiplier = (float)$b['mine_multiplier'] ?? 1.0;
            if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

            $healthFactor = max(0, $health / 100.0);
            $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);
            $prodAmountPerCycle = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);

            if (($currentStock + $prodAmountPerCycle) > $productSlotLimit) {
                $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
                continue;
            }

            // HAMMADDE KONTROLÜ (DÜZELTİLDİ: Sadece *ilk döngü* için yeterli hammadde var mı ona bakar, böylece 12 buğday varken 8'ini çeker ve çalışır)
            if (!empty($rawInputs)) {
                $hasAllInputsNow = true;
                foreach ($rawInputs as $inp) {
                    $scaledReq = round($inp['required_amount'] * $productionRate, 2);
                    $chkStock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $chkStock->execute([$userId, $inp['product_id']]);
                    $stockVal = (float)$chkStock->fetchColumn();
                    if ($stockVal < $scaledReq) {
                        $hasAllInputsNow = false;
                        break;
                    }
                }
                if (!$hasAllInputsNow) {
                    $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
                    continue;
                }
            }

            $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
            if ($phaseStartedTs <= 0) $phaseStartedTs = $now;

            $elapsed = $now - $phaseStartedTs;
            if ($elapsed <= 0) continue;

            $completedCycles = floor($elapsed / $productionSeconds);
            if ($completedCycles <= 0) continue;

            if ($prodAmountPerCycle <= 0) continue;

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

                    $invCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                    $invCheck->execute([$userId, $producesId]);
                    $currentStock = (float)($invCheck->fetchColumn() ?? 0);

                    if (($currentStock + $prodAmountPerCycle) > $productSlotLimit) {
                        break; 
                    }

                    // DÖNGÜ İÇİ HAMMADDE ÇEKİMİ (Her devirde stok düşümü ve birim maliyet aktarımı)
                    if (!empty($rawInputs)) {
                        $hasAllInputs = true;
                        foreach ($rawInputs as $inp) {
                            $scaledReq = round($inp['required_amount'] * $productionRate, 2);
                            $chkStock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                            $chkStock->execute([$userId, $inp['product_id']]);
                            $stockVal = (float)$chkStock->fetchColumn();
                            if ($stockVal < $scaledReq) {
                                $hasAllInputs = false;
                                break;
                            }
                        }
                        if (!$hasAllInputs) break; 

                        foreach ($rawInputs as $inp) {
                            $scaledReq = round($inp['required_amount'] * $productionRate, 2);
                            $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                               ->execute([$scaledReq, $userId, $inp['product_id']]);

                            $prodNameStmt = $db->prepare("SELECT name FROM products WHERE id = ?");
                            $prodNameStmt->execute([$inp['product_id']]);
                            $inName = $prodNameStmt->fetchColumn();

                            $consumeLog = "{$scaledReq} adet {$inName} depodan çekilerek işlendi.";
                            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
                               ->execute([$bId, $inp['product_id'], $scaledReq, $consumeLog]);
                        }
                    }

                    $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                    if ($cashCostPerCycle > 0 && $playerMoney < $cashCostPerCycle) break; 

                    // ÜRÜN ÜRETİMİ VE YENİ BİRİM MALİYETİN ENVANTERE KAYDI
                    $invRow = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $invRow->execute([$userId, $producesId]);
                    $existingInv = $invRow->fetch(\PDO::FETCH_ASSOC);

                    if ($existingInv) {
                        $oldQty = (float)$existingInv['quantity'];
                        $oldCost = (float)$existingInv['unit_cost'];
                        // Ağırlıklı ortalama maliyet hesaplama
                        $newTotalQty = $oldQty + $prodAmountPerCycle;
                        $blendedUnitCost = $newTotalQty > 0 ? (($oldQty * $oldCost) + ($prodAmountPerCycle * $calculatedUnitCost)) / $newTotalQty : $calculatedUnitCost;

                        $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$newTotalQty, $blendedUnitCost, $userId, $producesId]);
                    } else {
                        $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                           ->execute([$userId, $producesId, $prodAmountPerCycle, $calculatedUnitCost]);
                    }

                    if ($cashCostPerCycle > 0) {
                        $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$cashCostPerCycle, $userId]);
                        $costLog = "{$prodAmountPerCycle} adet {$b['product_name']} üretimi için işletme gideri kesildi: {$cashCostPerCycle}₺.";
                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                           ->execute([$bId, $producesId, $cashCostPerCycle, $costLog]);
                    }

                    $newHealth = max(0.0, $currentHealth - $wearRatePerCycle);
                    $db->prepare("UPDATE user_buildings SET condition_health = ? WHERE id = ?")->execute([$newHealth, $bId]);

                    $logDesc = "Üretim tamamlandı: {$prodAmountPerCycle} adet {$b['product_name']} depoya eklendi.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                       ->execute([$bId, $producesId, $prodAmountPerCycle, $logDesc]);

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

            if ($b) {
                try {
                    $db->beginTransaction();
                    $researches = $this->getPlayerResearches($db, $userId);

                    $category = strtolower(trim($b['category']));
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

                    // Malzeme maliyeti hesaplama (Envanterden maliyeti oku)
                    $rawInputs = $this->getBuildingInputs($db, $b['building_type_id']);
                    $inputMaterialCost = 0;
                    foreach ($rawInputs as $input) {
                        $scaledReq = round($input['required_amount'] * $productionRate, 2);
                        $chk = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                        $chk->execute([$userId, $input['product_id']]);
                        $invItem = $chk->fetch(\PDO::FETCH_ASSOC);
                        $currentStock = (float)($invItem['quantity'] ?? 0);
                        if ($currentStock < $scaledReq) {
                            throw new \Exception('Yetersiz Hammadde!');
                        }

                        $itemCost = (float)($invItem['unit_cost'] ?? 0);
                        if ($itemCost <= 0) {
                            $pCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                            $pCostStmt->execute([$input['product_id']]);
                            $itemCost = (float)$pCostStmt->fetchColumn();
                        }
                        $inputMaterialCost += ($itemCost * $scaledReq);

                        $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$scaledReq, $userId, $input['product_id']]);
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

                        $logDesc = "[GOD MODE] {$prodAmount} adet ürün üretilip depoya eklendi.";
                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                           ->execute([$buildingId, $producesId, $prodAmount, $logDesc]);
                    }

                    $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$buildingId]);
                    $db->commit();
                } catch (\Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                }
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
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

        $query = "SELECT pb.*, pb.id as building_id, 
                         UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
                         UNIX_TIMESTAMP(pb.paused_at) as paused_ts,
                         UNIX_TIMESTAMP(pb.created_at) as created_ts,
                         bt.id as building_type_id, bt.name as building_name, bt.category, bt.complexity_type, 
                         bt.energy_consumption, bt.water_consumption, bt.labor_required, 
                         bt.base_cost, bt.production_rate, bt.unit_type, bt.image as building_image, 
                         bt.produces_product_id, bt.consumes_product_id, bt.consumption_amount,
                         p.name as product_name, p.image as product_image, p.unit_cost as product_base_cost,
                         cp.name as input_product_name,
                         prov.name as province_name, prov.population, prov.labor_cost_multiplier,
                         prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, prov.latitude, prov.longitude
                  FROM user_buildings pb
                  JOIN building_types bt ON pb.building_type_id = bt.id
                  LEFT JOIN products p ON bt.produces_product_id = p.id
                  LEFT JOIN products cp ON bt.consumes_product_id = cp.id
                  JOIN provinces prov ON pb.province_id = prov.id
                  WHERE pb.user_id = ?
                  ORDER BY pb.id DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        $rawBuildings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $invStmt = $db->prepare("SELECT product_id, quantity FROM user_inventories WHERE user_id = ?");
        $invStmt->execute([$userId]);
        $inventoryMap = [];
        foreach ($invStmt->fetchAll(\PDO::FETCH_ASSOC) as $invRow) {
            $inventoryMap[$invRow['product_id']] = (float)$invRow['quantity'];
        }

        $my_buildings = [];
        $playerMoney = (float)($player['money'] ?? 0);

        foreach ($rawBuildings as $b) {
            $category = strtolower(trim($b['category']));
            $regionMult = 1.0;

            if (str_contains($category, 'agri') || str_contains($category, 'tarım') || str_contains($category, 'bahçe')) {
                $regionMult = (float)($b['agri_multiplier'] ?? 1.0);
            } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi')) {
                $regionMult = (float)($b['ind_multiplier'] ?? 1.0);
            } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
                $regionMult = (float)($b['mine_multiplier'] ?? 1.0);
            }
            if ($regionMult <= 0) $regionMult = 1.0;

            $level = (int)($b['level'] ?? 1);
            $baseProd = (int)($b['production_rate'] ?? 8);
            
            try {
                $matrixStmt = $db->prepare("SELECT production_rate FROM building_level_matrix WHERE building_type_id = ? AND level = ?");
                $matrixStmt->execute([$b['building_type_id'], $level]);
                $matrixRow = $matrixStmt->fetch(\PDO::FETCH_ASSOC);
                if ($matrixRow && isset($matrixRow['production_rate'])) {
                    $baseProd = (int)$matrixRow['production_rate'];
                }
            } catch (\Exception $e) {}

            $health = (float)($b['condition_health'] ?? 100.0);
            $healthFactor = max(0, $health / 100.0);
            $productionAmount = round($baseProd * $regionMult * $healthFactor, 2);
            $unitLabel = $b['unit_type'] ?? 'Adet';

            $unitCost = (float)($b['product_base_cost'] ?? 0);
            $totalProductionCost = round($productionAmount * $unitCost, 2);

            $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
            if ($phaseStartedTs <= 0) $phaseStartedTs = (int)($b['created_ts'] ?? $now);
            
            $pausedTs = (int)($b['paused_ts'] ?? 0);
            $isPaused = (int)($b['is_paused'] ?? 0) === 1;

            // Eğer tesis duraklatılmışsa, geçen süre paused_ts anında dondurulur
            if ($isPaused && $pausedTs > 0) {
                $elapsedTime = $pausedTs - $phaseStartedTs;
            } else {
                $elapsedTime = $now - $phaseStartedTs;
            }

            $producesId = $b['produces_product_id'];
            $currentProductStock = $inventoryMap[$producesId] ?? 0.0;
            
            $hasInput = !empty($b['consumes_product_id']) && (float)($b['consumption_amount'] ?? 0) > 0;
            $availableStock = $hasInput ? ($inventoryMap[$b['consumes_product_id']] ?? 0.0) : 0;
            $requiredAmount = $hasInput ? (float)$b['consumption_amount'] : 0;
            $hasEnoughInput = !$hasInput || ($availableStock >= $requiredAmount);

            $status = $b['production_status'] ?? 'waiting_for_input';
            $warningMessage = '';
            $isProductSlotFull = ($currentProductStock + $productionAmount) > $productSlotLimit;

            if ($health < 50.0) {
                $warningMessage = "Kritik Hasar! Sağlık %50 altında, üretim durdu.";
            } elseif ($isProductSlotFull) {
                $warningMessage = "Depo Dolu! Bu ürün için slot sınırı aşıldı.";
            } elseif ($hasInput && $status === 'waiting_for_input' && !$hasEnoughInput) {
                $warningMessage = "Hammadde Bekleniyor: {$b['input_product_name']} (Gereken: {$requiredAmount}, Mevcut: {$availableStock})";
            }

            // Arayüz Görsel Hesaplamaları
            $transferSec = 15;
            $productionSec = 600;

            if (!$hasInput) {
                // Girdisiz Tesis (Tarla)
                if ($elapsedTime < 0) $elapsedTime = 0;
                if ($elapsedTime > $productionSec) $elapsedTime = $productionSec;
                $progress = ($elapsedTime / $productionSec) * 100;
                $barText = $isPaused ? "DURAKLATILDI" : "Üretim İlerlemesi";
                $barColor = $isPaused ? "bg-warning" : "bg-success progress-bar-animated progress-bar-striped";
                $remSec = max(0, $productionSec - $elapsedTime);
            } else {
                // Girdili Tesis (Değirmen vb.)
                if ($status === 'waiting_for_input') {
                    $progress = 0;
                    $remSec = 0;
                    $barText = "Hammadde Bekleniyor...";
                    $barColor = "bg-danger";
                } elseif ($status === 'transferring') {
                    if ($elapsedTime < 0) $elapsedTime = 0;
                    if ($elapsedTime > $transferSec) $elapsedTime = $transferSec;
                    $progress = ($elapsedTime / $transferSec) * 100;
                    $barText = $isPaused ? "DURAKLATILDI (Transfer)" : "Hammadde Depodan Çekiliyor...";
                    $barColor = $isPaused ? "bg-warning" : "bg-info progress-bar-animated progress-bar-striped";
                    $remSec = max(0, $transferSec - $elapsedTime);
                } else { // producing
                    if ($elapsedTime < 0) $elapsedTime = 0;
                    if ($elapsedTime > $productionSec) $elapsedTime = $productionSec;
                    $progress = ($elapsedTime / $productionSec) * 100;
                    $barText = $isPaused ? "DURAKLATILDI (Üretim)" : "Üretim İlerlemesi";
                    $barColor = $isPaused ? "bg-warning" : "bg-success progress-bar-animated progress-bar-striped";
                    $remSec = max(0, $productionSec - $elapsedTime);
                }
            }

            $b['id'] = $b['building_id'];
            $b['formula_details'] = [
                'base_production' => $baseProd,
                'region_multiplier' => $regionMult,
                'unit_label' => $unitLabel,
                'has_enough_input' => $hasEnoughInput,
                'available_stock' => $availableStock,
                'production_cost' => $totalProductionCost,
                'input_product_name' => $b['input_product_name'] ?? 'Hammadde Gerekmiyor',
                'consumption_amount' => $requiredAmount,
                'health_factor' => $healthFactor,
                'warning_message' => $warningMessage
            ];

            $b['production_amount'] = $productionAmount;
            $b['repair_cost'] = round(((float)($b['base_cost'] ?? 1000) * $level) * ((100.0 - $health) / 100.0), 2);
            $b['is_paused'] = $isPaused;
            $b['condition_health'] = $health;
            $b['image'] = $b['building_image'];
            $b['rem_sec'] = $remSec;
            $b['progress'] = $progress;
            $b['bar_text'] = $barText;
            $b['bar_color'] = $barColor;
            $b['cycle_seconds'] = ($status === 'transferring') ? 15 : 600;
            $b['is_active_ready'] = (!$isPaused && $health >= 50.0 && !$isProductSlotFull && ($hasEnoughInput || !$hasInput));

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
        $transferSeconds = 15;

        $playerRow = $db->prepare("SELECT warehouse_level FROM users WHERE id = ?");
        $playerRow->execute([$userId]);
        $warehouseLevel = (int)($playerRow->fetchColumn() ?? 1);
        $productSlotLimit = $warehouseLevel * 500.00;

        $stmt = $db->prepare("SELECT pb.*, pb.id as building_id, 
                                     UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
                                     UNIX_TIMESTAMP(pb.created_at) as created_ts,
                                     bt.id as building_type_id, bt.produces_product_id, bt.consumes_product_id, bt.consumption_amount, bt.production_rate, 
                                     p.name as product_name, p.unit_cost as product_base_cost,
                                     cp.name as input_product_name,
                                     prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, bt.category 
                              FROM user_buildings pb 
                              JOIN building_types bt ON pb.building_type_id = bt.id 
                              LEFT JOIN products p ON bt.produces_product_id = p.id
                              LEFT JOIN products cp ON bt.consumes_product_id = cp.id
                              JOIN provinces prov ON pb.province_id = prov.id 
                              WHERE pb.user_id = ?");
        $stmt->execute([$userId]);
        $buildings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($buildings as $b) {
            if ((int)($b['is_paused'] ?? 0) === 1) continue;
            if ((float)($b['condition_health'] ?? 100.0) < 50.0) continue;

            $bId = $b['building_id'];
            $producesId = $b['produces_product_id'];
            $consumesId = $b['consumes_product_id'];
            $consumptionAmount = (float)($b['consumption_amount'] ?? 0);
            $hasInput = !empty($consumesId) && $consumptionAmount > 0;

            $status = $b['production_status'] ?? 'waiting_for_input';
            $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
            if ($phaseStartedTs <= 0) $phaseStartedTs = $now;

            $elapsed = $now - $phaseStartedTs;

            // --- 1. GİRDİSİZ TESİSLER (Örn: Buğday Tarlası) ---
            if (!$hasInput) {
                if ($elapsed >= $productionSeconds) {
                    try {
                        $db->beginTransaction();
                        
                        $category = strtolower(trim($b['category']));
                        $regionMult = 1.0;
                        if (str_contains($category, 'agri') || str_contains($category, 'tarım')) $regionMult = (float)$b['agri_multiplier'];
                        elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi')) $regionMult = (float)$b['ind_multiplier'];
                        elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) $regionMult = (float)$b['mine_multiplier'];
                        if ($regionMult <= 0) $regionMult = 1.0;

                        $health = (float)($b['condition_health'] ?? 100.0);
                        $healthFactor = max(0, $health / 100.0);
                        $baseProd = (int)$b['production_rate'];

                        try {
                            $matrixStmt = $db->prepare("SELECT production_rate FROM building_level_matrix WHERE building_type_id = ? AND level = ?");
                            $matrixStmt->execute([$b['building_type_id'], (int)$b['level']]);
                            $matrixRow = $matrixStmt->fetch(\PDO::FETCH_ASSOC);
                            if ($matrixRow && isset($matrixRow['production_rate'])) $baseProd = (int)$matrixRow['production_rate'];
                        } catch (\Exception $e) {}

                        $prodAmount = round($baseProd * $regionMult * $healthFactor, 2);

                        $invCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                        $invCheck->execute([$userId, $producesId]);
                        $invRow = $invCheck->fetch(\PDO::FETCH_ASSOC);
                        $currentStock = (float)($invRow['quantity'] ?? 0);

                        if (($currentStock + $prodAmount) <= $productSlotLimit && $prodAmount > 0) {
                            if ($invRow) {
                                $db->prepare("UPDATE user_inventories SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?")
                                   ->execute([$prodAmount, $userId, $producesId]);
                            } else {
                                $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity) VALUES (?, ?, ?)")
                                   ->execute([$userId, $producesId, $prodAmount]);
                            }

                            $logDesc = "Üretim tamamlandı: {$prodAmount} adet {$b['product_name']} merkez depoya kaydedildi.";
                            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                               ->execute([$bId, $producesId, $prodAmount, $logDesc]);

                            $newHealth = max(0.0, $health - 2.0);
                            $db->prepare("UPDATE user_buildings SET condition_health = ? WHERE id = ?")->execute([$newHealth, $bId]);

                            $db->prepare("UPDATE user_buildings SET phase_started_at = FROM_UNIXTIME(? + ?) WHERE id = ?")
                               ->execute([$phaseStartedTs, $productionSeconds, $bId]);
                        }
                        $db->commit();
                    } catch (\Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                    }
                }
                continue;
            }

            // --- 2. GİRDİLİ TESİSLER (Örn: Değirmen) İKİ AŞAMALI MİMARİ ---
            
            if ($status === 'waiting_for_input') {
                $chk = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                $chk->execute([$userId, $consumesId]);
                $currentStock = (float)($chk->fetchColumn() ?? 0);

                if ($currentStock >= $consumptionAmount) {
                    $db->prepare("UPDATE user_buildings SET production_status = 'transferring', phase_started_at = NOW() WHERE id = ?")
                       ->execute([$bId]);
                }
            } 
            elseif ($status === 'transferring') {
                if ($elapsed >= $transferSeconds) {
                    try {
                        $db->beginTransaction();

                        $chkStock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                        $chkStock->execute([$userId, $consumesId]);
                        $stockVal = (float)($chkStock->fetchColumn() ?? 0);

                        if ($stockVal < $consumptionAmount) {
                            $db->prepare("UPDATE user_buildings SET production_status = 'waiting_for_input', phase_started_at = NOW() WHERE id = ?")
                               ->execute([$bId]);
                            $db->commit();
                            continue;
                        }

                        $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$consumptionAmount, $userId, $consumesId]);

                        $consumeLog = "{$consumptionAmount} adet {$b['input_product_name']} depodan çekilerek işlendi.";
                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
                           ->execute([$bId, $consumesId, $consumptionAmount, $consumeLog]);

                        $db->prepare("UPDATE user_buildings SET production_status = 'producing', phase_started_at = NOW() WHERE id = ?")
                           ->execute([$bId]);

                        $db->commit();
                    } catch (\Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                    }
                }
            } 
            elseif ($status === 'producing') {
                if ($elapsed >= $productionSeconds) {
                    try {
                        $db->beginTransaction();

                        $category = strtolower(trim($b['category']));
                        $regionMult = 1.0;
                        if (str_contains($category, 'agri') || str_contains($category, 'tarım')) $regionMult = (float)$b['agri_multiplier'];
                        elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi')) $regionMult = (float)$b['ind_multiplier'];
                        elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) $regionMult = (float)$b['mine_multiplier'];
                        if ($regionMult <= 0) $regionMult = 1.0;

                        $health = (float)($b['condition_health'] ?? 100.0);
                        $healthFactor = max(0, $health / 100.0);
                        $baseProd = (int)$b['production_rate'];

                        try {
                            $matrixStmt = $db->prepare("SELECT production_rate FROM building_level_matrix WHERE building_type_id = ? AND level = ?");
                            $matrixStmt->execute([$b['building_type_id'], (int)$b['level']]);
                            $matrixRow = $matrixStmt->fetch(\PDO::FETCH_ASSOC);
                            if ($matrixRow && isset($matrixRow['production_rate'])) $baseProd = (int)$matrixRow['production_rate'];
                        } catch (\Exception $e) {}

                        $prodAmount = round($baseProd * $regionMult * $healthFactor, 2);
                        $unitCost = (float)($b['product_base_cost'] ?? 0);
                        $totalProductionCost = round($prodAmount * $unitCost, 2);

                        $invCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                        $invCheck->execute([$userId, $producesId]);
                        $invRow = $invCheck->fetch(\PDO::FETCH_ASSOC);
                        $currentProductStock = (float)($invRow['quantity'] ?? 0);

                        if (($currentProductStock + $prodAmount) <= $productSlotLimit && $prodAmount > 0) {
                            if ($invRow) {
                                $db->prepare("UPDATE user_inventories SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?")
                                   ->execute([$prodAmount, $userId, $producesId]);
                            } else {
                                $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity) VALUES (?, ?, ?)")
                                   ->execute([$userId, $producesId, $prodAmount]);
                            }

                            $logDesc = "Üretim tamamlandı: {$prodAmount} adet {$b['product_name']} merkez depoya kaydedildi.";
                            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                               ->execute([$bId, $producesId, $prodAmount, $logDesc]);

                            $newHealth = max(0.0, $health - 2.0);
                            $db->prepare("UPDATE user_buildings SET condition_health = ? WHERE id = ?")->execute([$newHealth, $bId]);

                            $wearLogDesc = "Üretim döngüsü tamamlandı, tesis sağlığı %2 azalarak %{$newHealth} seviyesine düştü.";
                            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'wear', ?, 2.0, ?, NOW())")
                               ->execute([$bId, $producesId, $wearLogDesc]);

                            $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                            if ($totalProductionCost > 0 && $playerMoney >= $totalProductionCost) {
                                $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$totalProductionCost, $userId]);
                                $costLog = "{$prodAmount} adet {$b['product_name']} üretimi için maliyet kesildi: {$totalProductionCost}₺.";
                                $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                                   ->execute([$bId, $producesId, $totalProductionCost, $costLog]);
                            }

                            $chkNext = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                            $chkNext->execute([$userId, $consumesId]);
                            $nextStock = (float)($chkNext->fetchColumn() ?? 0);

                            if ($nextStock >= $consumptionAmount) {
                                $db->prepare("UPDATE user_buildings SET production_status = 'transferring', phase_started_at = NOW() WHERE id = ?")
                                   ->execute([$bId]);
                            } else {
                                $db->prepare("UPDATE user_buildings SET production_status = 'waiting_for_input', phase_started_at = NOW() WHERE id = ?")
                                   ->execute([$bId]);
                            }
                        }

                        $db->commit();
                    } catch (\Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                    }
                }
            }
        }
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
                    // PLAY (Devam Et): Molada geçen süreyi hesapla ve phase_started_at değerini o kadar ileri kaydır
                    // Yeni Başlangıç = Eski Başlangıç + (Şu An - Duraklatıldığı An)
                    $db->prepare("UPDATE user_buildings 
                                  SET is_paused = 0, 
                                      phase_started_at = DATE_ADD(phase_started_at, INTERVAL TIMESTAMPDIFF(SECOND, paused_at, NOW()) SECOND),
                                      paused_at = NULL 
                                  WHERE id = ?")
                       ->execute([$buildingId]);
                } else {
                    // PAUSE (Duraklat): Duraklatıldığı anı (paused_at) veritabanına kaydet
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
                            $db->prepare("UPDATE user_buildings SET condition_health = 100.00 WHERE id = ?")->execute([$buildingId]);
                            
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

            $stmt = $db->prepare("SELECT pb.*, bt.produces_product_id, bt.consumes_product_id, bt.consumption_amount, bt.production_rate, 
                                         p.name as product_name, p.unit_cost as product_base_cost,
                                         cp.name as input_product_name,
                                         prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, bt.category 
                                  FROM user_buildings pb 
                                  JOIN building_types bt ON pb.building_type_id = bt.id 
                                  LEFT JOIN products p ON bt.produces_product_id = p.id
                                  LEFT JOIN products cp ON bt.consumes_product_id = cp.id
                                  JOIN provinces prov ON pb.province_id = prov.id 
                                  WHERE pb.id = ? AND pb.user_id = ?");
            $stmt->execute([$buildingId, $userId]);
            $b = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($b) {
                try {
                    $db->beginTransaction();

                    $category = strtolower(trim($b['category']));
                    $regionMult = 1.0;
                    if (str_contains($category, 'agri') || str_contains($category, 'tarım')) $regionMult = (float)$b['agri_multiplier'];
                    elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi')) $regionMult = (float)$b['ind_multiplier'];
                    elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) $regionMult = (float)$b['mine_multiplier'];
                    if ($regionMult <= 0) $regionMult = 1.0;

                    $health = (float)($b['condition_health'] ?? 100.0);
                    $healthFactor = max(0, $health / 100.0);
                    $baseProd = (int)$b['production_rate'];
                    
                    try {
                        $matrixStmt = $db->prepare("SELECT production_rate FROM building_level_matrix WHERE building_type_id = ? AND level = ?");
                        $matrixStmt->execute([$b['building_type_id'], (int)$b['level']]);
                        $matrixRow = $matrixStmt->fetch(\PDO::FETCH_ASSOC);
                        if ($matrixRow && isset($matrixRow['production_rate'])) {
                            $baseProd = (int)$matrixRow['production_rate'];
                        }
                    } catch (\Exception $e) {}

                    $prodAmount = round($baseProd * $regionMult * $healthFactor, 2);
                    $unitCost = (float)($b['product_base_cost'] ?? 0);
                    $totalProductionCost = round($prodAmount * $unitCost, 2);
                    $finishCost = round($totalProductionCost * 0.20, 2);

                    $consumesId = $b['consumes_product_id'];
                    $consumptionAmount = (float)($b['consumption_amount'] ?? 0);
                    if (!empty($consumesId) && $consumptionAmount > 0) {
                        $chk = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                        $chk->execute([$userId, $consumesId]);
                        $stockRow = $chk->fetch(\PDO::FETCH_ASSOC);
                        $currentStock = (float)($stockRow['quantity'] ?? 0);
                        if ($currentStock < $consumptionAmount) {
                            throw new \Exception('Yetersiz Hammadde!');
                        }
                        $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                           ->execute([$consumptionAmount, $userId, $consumesId]);
                    }

                    $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                    if ($playerMoney < $finishCost) throw new \Exception('Yetersiz bütçe!');

                    $pInfo = $db->prepare("SELECT warehouse_level FROM users WHERE id = ?");
                    $pInfo->execute([$userId]);
                    $wLevel = (int)($pInfo->fetchColumn() ?? 1);
                    $slotLimit = $wLevel * 500.00;

                    $producesId = $b['produces_product_id'];
                    $invCheck = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $invCheck->execute([$userId, $producesId]);
                    $invRow = $invCheck->fetch(\PDO::FETCH_ASSOC);
                    $currentStock = (float)($invRow['quantity'] ?? 0);

                    if (($currentStock + $prodAmount) > $slotLimit) {
                        throw new \Exception('Depo Slot Kapasitesi Yetersiz!');
                    }

                    if ($finishCost > 0) {
                        $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$finishCost, $userId]);
                        $costLog = "{$prodAmount} adet {$b['product_name']} hızlı bitirmek için maliyet ({$finishCost}₺) ödendi.";
                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                           ->execute([$buildingId, $producesId, $finishCost, $costLog]);
                    }

                    if (!empty($producesId) && $prodAmount > 0) {
                        if ($invRow) {
                            $db->prepare("UPDATE user_inventories SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?")
                               ->execute([$prodAmount, $userId, $producesId]);
                        } else {
                            $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity) VALUES (?, ?, ?)")
                               ->execute([$userId, $producesId, $prodAmount]);
                        }

                        $logDesc = "[GOD MODE] {$prodAmount} adet {$b['product_name']} üretilip depoya eklendi.";
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
<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class PlayerBuildingController extends Controller {

     /*** Ana Index Fonksiyonu (Akış Yöneticisi - Orchestrator) */
    public function index() {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $activeCategory = basename($_GET['url'] ?? ''); 

        // 1. Oyun Ayarlarını ve Sabitleri Yükle
        $settings = $this->loadGameSettings($db);
        $productionSec               = (int)($settings['production_seconds'] ?? 600);
        $defaultWarehouseLvl         = (int)($settings['default_warehouse_lvl'] ?? 1);
        $slotMultiplier              = (float)($settings['slot_multiplier'] ?? 500.00);
        $defaultWarehouseUpgradeCost = (float)($settings['warehouse_upgrade_base_cost'] ?? 10000.00);
        $amortismanBedeli            = (float)($settings['amortisman_rate'] ?? 2.0);

        // 2. Zaman ve Oyuncu Profili
        $now = $this->getCurrentDatabaseTime($db);
        $player = $this->getPlayerProfile($db, $userId, $defaultWarehouseLvl);

        // Arka plan üretim ve AOM maliyet motorunu tetikle
        $this->processProductionCycles($db, $userId, $now);

        // Güncel verileri tazele
        $player = $this->getPlayerProfile($db, $userId, $defaultWarehouseLvl);
        $warehouseLevel = (int)$player['warehouse_level'];
        $productSlotLimit = $warehouseLevel * $slotMultiplier;
        $warehouseUpgradeCost = $this->getWarehouseUpgradeCost($db, $warehouseLevel + 1, $warehouseLevel, $defaultWarehouseUpgradeCost);

        $researches = $this->getPlayerResearches($db, $userId);

        // 3. Binaları ve Envanteri Çek
        $rawBuildings = $this->getRawBuildings($db, $userId, $activeCategory);
        [$inventoryMap, $inventoryCostMap] = $this->getInventoryMaps($db, $userId);
        $currentEnergyStock = $this->getEnergyStock($db, $inventoryMap);

        $my_buildings = [];

        // 4. Her Bir Bina İçin Hesaplama ve İşleme Döngüsü
        foreach ($rawBuildings as $b) {
            $buildingTypeKey = $b['building_type_id'];
            $level = (int)($b['level'] ?? 1);
            $health = (float)($b['condition_health'] ?? 100.0);
            $healthFactor = max(0, $health / 100.0);

            // Çarpanlar ve AR-GE Bonusları
            $soilMultiplier = $this->calculateSoilMultiplier($b, $researches);
            $productionRate = (float)($b['production_rate'] ?? 1.0);
            $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);

            $outputs = $this->getBuildingOutputs($db, $buildingTypeKey);
            $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;

            $productionAmount = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);
            if ($health < 50.0) $productionAmount = 0.0;

            // Hammadde Girdileri ve Maliyet Hesabı
            $rawInputs = $this->getBuildingInputs($db, $buildingTypeKey);
            [$inputs, $inputMaterialCost] = $this->processBuildingInputs($db, $rawInputs, $productionRate, $inventoryCostMap);

            // Finansal ve Operasyonel Giderler (Birim Maliyet dahil)
            $financeData = $this->calculateOperationalExpenses($b, $researches, $amortismanBedeli, $inputMaterialCost, $productionAmount);

            // Zaman, Durum ve İlerleme Kontrolleri
            $statusData = $this->evaluateBuildingStatus($db, $b, $inventoryMap, $currentEnergyStock, $inputs, $productionSec, $now);

            // Bina Dizi Paketlemesi (Arayüz ve Modal İçin)
            $b['id'] = $b['building_id'];
            $b['production_amount'] = $productionAmount;
            $b['repair_cost'] = $this->calculateRepairCost($b, $level, $health);
            $b['is_paused'] = $statusData['is_paused'];
            $b['condition_health'] = $health;
            $b['image'] = $b['building_image'];
            $b['rem_sec'] = $statusData['rem_sec'];
            $b['progress'] = $statusData['progress'];
            $b['bar_text'] = $statusData['bar_text'];
            $b['bar_color'] = $statusData['bar_color'];
            $b['cycle_seconds'] = $productionSec;

            // Formül Detayları ve Gider Kalemleri Cetveli Verileri
            $b['formula_details'] = array_merge($financeData, [
                'production_rate' => $productionRate,
                'product_image' => $b['product_image'],
                'unit_type' => $b['unit_type'],
                'soil_multiplier' => $soilMultiplier,
                'health_factor' => $healthFactor,
                'base_output_amount' => $baseOutputAmount,
                'unit_label' => $b['unit_type'] ?? 'Adet',
                'has_enough_input' => ($statusData['has_enough_input'] || $statusData['is_currently_producing']),
                'inputs' => $inputs,
                'warning_message' => $statusData['warning_message'],
                'researches' => $researches
            ]);

            // Logları Ayrıştır ve Özetle
            $b = $this->attachBuildingLogs($db, $b);

            $my_buildings[] = $b;
        }

        // 5. Envanter, Bina Tipleri ve Provinsleri Yükle
        $inventory = $this->getFullInventory($db, $userId);
        $building_types = $db->query("SELECT bt.*, p.name as product_name, p.image as product_image, p.unit_cost as product_base_cost 
                                    FROM building_types bt 
                                    LEFT JOIN products p ON bt.produces_product_id = p.id 
                                    ORDER BY bt.name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $provinces = $db->query("SELECT * FROM provinces ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        // 6. View'e Aktar
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

    /*** Ana üretim döngüsü motoru (Bütünleşik, Buffer-Destekli ve Güvenli Batch Mimarisi)*/
    private function processProductionCycles($db, $userId, $now) {
        $config = [
            'production_seconds'   => 600,   
            'default_warehouse_lvl'=> 1,     
            'slot_multiplier'      => 500.00,
            'min_health_threshold' => 50.0,  
            'default_energy_req'   => 50,    
            'default_labor_req'    => 10,    
            'default_labor_cost'   => 50.0,  
            'default_transport'    => 20.0,  
            'default_tax'          => 10.0,  
            'default_wear_rate'    => 2.0,   
        ];

        $playerRowData = $this->getPlayerRowData($db, $userId);
        $warehouseLevel = (int)($playerRowData['warehouse_level'] ?? $config['default_warehouse_lvl']);
        $productSlotLimit = $warehouseLevel * $config['slot_multiplier'];

        $researches = $this->getPlayerResearches($db, $userId);
        $energyProductId = $this->getEnergyProductId($db);
        $buildings = $this->getUserBuildingsWithDetails($db, $userId);

        foreach ($buildings as $building) {
            if ((int)($building['is_paused'] ?? 0) === 1) continue;

            $bId = $building['building_id'];
            $health = (float)($building['condition_health'] ?? 100.0);

            if ($health < $config['min_health_threshold']) {
                $this->resetBuildingPhase($db, $bId);
                continue;
            }

            $producesId = $building['produces_product_id'];
            $productionRate = (float)($building['production_rate'] ?? 1.0);
            $energyRequired = (float)($building['energy_consumption'] ?? $config['default_energy_req']);

            $rawInputs = $this->getBuildingInputs($db, $building['building_type_id']);
            $outputs = $this->getBuildingOutputs($db, $building['building_type_id']);
            $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;

            $phaseStartedTs = (int)($building['phase_started_ts'] ?? 0);
            if ($phaseStartedTs <= 0) $phaseStartedTs = $now;
            $elapsed = $now - $phaseStartedTs;

            if ($elapsed <= 0) continue;
            $completedCycles = floor($elapsed / $config['production_seconds']);
            if ($completedCycles <= 0) continue;

            $prodAmountPerCycle = $this->calculateProductionAmount($building, $researches, $baseOutputAmount, $productionRate, $health);
            $cashCostPerCycle = $this->calculateCashCost($building, $researches, $config);
            $wearRatePerCycle = (float)($building['wear_rate'] ?? $config['default_wear_rate']);

            $this->executeIntegratedBatchTransaction(
                $db, $userId, $bId, $producesId, $completedCycles, $prodAmountPerCycle,
                $cashCostPerCycle, $wearRatePerCycle, $energyProductId, $energyRequired,
                $productSlotLimit, $rawInputs, $productionRate, $building, $config
            );
        }
    }

    private function executeIntegratedBatchTransaction(
        $db, $userId, $bId, $producesId, $completedCycles, $prodAmountPerCycle,
        $cashCostPerCycle, $wearRatePerCycle, $energyProductId, $energyRequired,
        $productSlotLimit, $rawInputs, $productionRate, $building, $config
        ) {
        if (empty($producesId) || $producesId <= 0) {
            return;
        }

        try {
            $db->beginTransaction();

            // 1. ADIM: Bina Bilgisini ve Sağlığı Güvenli Kilitle
            $buildingStmt = $db->prepare("SELECT buffer_inventory, condition_health FROM user_buildings WHERE id = ? FOR UPDATE");
            $buildingStmt->execute([$bId]);
            $buildingData = $buildingStmt->fetch(\PDO::FETCH_ASSOC);
            
            $currentHealth = (float)($buildingData['condition_health'] ?? 100.0);
            if ($currentHealth < 50.0) {
                $db->commit();
                return;
            }

            $bufferData = !empty($buildingData['buffer_inventory']) ? json_decode($buildingData['buffer_inventory'], true) : [];

            // 2. ADIM: Hammadde Buffer Yönetimi (Eksikse Tedarik Et, Tamamsa Üretime Geç)
            if (!empty($rawInputs) && empty($bufferData)) {
                $tempBuffer = [];
                foreach ($rawInputs as $inp) {
                    $scaledReqPerCycle = round($inp['required_amount'] * $productionRate, 2);
                    $totalNeededForCycles = $scaledReqPerCycle * max(1, $completedCycles);

                    $chkStock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                    $chkStock->execute([$userId, $inp['product_id']]);
                    $stockVal = (float)$chkStock->fetchColumn();

                    // Eğer 1 döngülük bile hammadde yoksa işlemi beklet, zamanı bozma
                    if ($stockVal < $scaledReqPerCycle) {
                        $db->commit();
                        return;
                    }

                    // Ne kadar döngü yapabilecekse o kadar hammaddeyi buffer'a al
                    $allotableCycles = min($completedCycles, floor($stockVal / $scaledReqPerCycle));
                    $allotableCycles = max(1, $allotableCycles); // En az 1 döngü
                    
                    $usedInputQty = $scaledReqPerCycle * $allotableCycles;
                    
                    // Hammaddeyi oyuncu deposundan düş
                    $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                    ->execute([$usedInputQty, $userId, $inp['product_id']]);

                    $tempBuffer[$inp['product_id']] = $usedInputQty;
                }

                $bufferData = $tempBuffer;
            }

            // 3. ADIM: Kaynak Sınırları (Depo, Enerji, Para) ile Hedef Döngüyü Belirle
            $targetCycles = $completedCycles > 0 ? $completedCycles : 1;

            // Depo Kapasite Sınırı
            $invCheck = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
            $invCheck->execute([$userId, $producesId]);
            $existingInv = $invCheck->fetch(\PDO::FETCH_ASSOC);
            $currentStock = (float)($existingInv['quantity'] ?? 0);

            $availableSpace = max(0, $productSlotLimit - $currentStock);
            $maxCyclesBySpace = $prodAmountPerCycle > 0 ? floor($availableSpace / $prodAmountPerCycle) : 0;
            
            if ($maxCyclesBySpace <= 0) {
                $db->commit();
                return; // Depo tamamen dolu, üretim bekler
            }
            $targetCycles = min($targetCycles, $maxCyclesBySpace);

            // Enerji Sınırı
            if ($energyProductId > 0 && $energyRequired > 0) {
                $enLock = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                $enLock->execute([$userId, $energyProductId]);
                $enStock = (float)$enLock->fetchColumn();
                $maxCyclesByEnergy = floor($enStock / $energyRequired);
                $targetCycles = min($targetCycles, max(0, $maxCyclesByEnergy));
            }

            // Para Sınırı
            if ($cashCostPerCycle > 0) {
                $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId} FOR UPDATE")->fetchColumn();
                $maxCyclesByMoney = floor($playerMoney / $cashCostPerCycle);
                $targetCycles = min($targetCycles, max(0, $maxCyclesByMoney));
            }

            if ($targetCycles <= 0) {
                $db->commit();
                return; // Para veya enerji yetersiz, üretim bekler
            }

            // 4. ADIM: Toplam Değerlerin Hesaplandıktan Sonra İşlenmesi
            $totalProdAmount = round($targetCycles * $prodAmountPerCycle, 2);
            $totalCashCost = round($targetCycles * $cashCostPerCycle, 2);
            $totalEnergyReq = round($targetCycles * $energyRequired, 2);
            $totalWear = round($targetCycles * $wearRatePerCycle, 2);

            // Hammadde Maliyet Toplamı
            $inputMaterialCostTotal = 0;
            if (!empty($bufferData)) {
                foreach ($bufferData as $pId => $qtyUsed) {
                    $itemCostQuery = $db->prepare("SELECT unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $itemCostQuery->execute([$userId, $pId]);
                    $itemCostRow = $itemCostQuery->fetch(\PDO::FETCH_ASSOC);
                    
                    $mCost = (float)($itemCostRow['unit_cost'] ?? 0);
                    if ($mCost <= 0) {
                        $pCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                        $pCostStmt->execute([$pId]);
                        $mCost = (float)$pCostStmt->fetchColumn();
                    }
                    $inputMaterialCostTotal += round($mCost * $qtyUsed, 2);
                }
            }

            // Enerji Düşümü
            if ($energyProductId > 0 && $totalEnergyReq > 0) {
                $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                ->execute([$totalEnergyReq, $userId, $energyProductId]);

                $energyLogDesc = "{$targetCycles} parti {$building['product_name']} ({$totalProdAmount} {$building['unit_type']}) için {$totalEnergyReq} kW/h enerji alımı yapıldı.";
                $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
                ->execute([$bId, $energyProductId, $totalEnergyReq, $energyLogDesc]);
            }

            // Para Düşümü
            if ($totalCashCost > 0) {
                $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$totalCashCost, $userId]);

                $costLog = "{$targetCycles} parti {$building['product_name']} ({$totalProdAmount} {$building['unit_type']}) için {$totalCashCost}₺ maliyet aktarıldı.";
                $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                ->execute([$bId, $producesId, $totalCashCost, $costLog]);
            }


            // Amortisman Payı ve Birim Maliyet (AOM) Hesabı
            $settingsStmt = $db->query("SELECT setting_value FROM game_settings WHERE setting_key = 'amortisman_rate'");
            $amortismanRate = $settingsStmt ? (float)($settingsStmt->fetchColumn() ?? 2.0) : 2.0;
            $calculatedWearCost = round($wearRatePerCycle * $amortismanRate, 2) * $targetCycles;

            $grandTotalProductionCost = round($inputMaterialCostTotal + $totalCashCost + $calculatedWearCost, 2);
            $calculatedUnitCost = ($totalProdAmount > 0) ? round($grandTotalProductionCost / $totalProdAmount, 2) : 0.00;

            // 5. ADIM: Depoya Ürünü Ekleyip AOM Güncellemesi Yapma
            if ($existingInv) {
                $oldQty = (float)$existingInv['quantity'];
                $oldCost = (float)$existingInv['unit_cost'];
                $newTotalQty = round($oldQty + $totalProdAmount, 2);
                
                $blendedUnitCost = $newTotalQty > 0 ? (($oldQty * $oldCost) + ($totalProdAmount * $calculatedUnitCost)) / $newTotalQty : $calculatedUnitCost;

                $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                ->execute([$newTotalQty, round($blendedUnitCost, 2), $userId, $producesId]);
            } else {
                $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $producesId, $totalProdAmount, $calculatedUnitCost]);
            }

            // Bina Sağlığı Güncellemesi ve Loglar
            $newHealth = max(0.0, round($currentHealth - $totalWear, 2));
            $db->prepare("UPDATE user_buildings SET condition_health = ? WHERE id = ?")->execute([$newHealth, $bId]);

            $logDesc = "Üretim tamamlandı: {$targetCycles} parti {$building['product_name']} ({$totalProdAmount} {$building['unit_type']}) depoya eklendi.";
            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
            ->execute([$bId, $producesId, $totalProdAmount, $logDesc]);

            // 6. ADIM: Zamanı Sadece Tamamlanan Döngü Kadar İleri Sar
            $advancedSeconds = $targetCycles * $config['production_seconds'];
            $db->prepare("UPDATE user_buildings SET phase_started_at = DATE_ADD(phase_started_at, INTERVAL ? SECOND), buffer_inventory = NULL WHERE id = ?")
            ->execute([$advancedSeconds, $bId]);

            $db->commit();
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Bütünleşik Üretim Hatası (Building ID: {$bId}): " . $e->getMessage());
        }
    }


    // ==========================================
    // YARDIMCI MODÜLER FONKSİYONLAR (HELPER METHODS)
    // ==========================================

    private function loadGameSettings($db) {
        $settings = [];
        $stmt = $db->query("SELECT setting_key, setting_value FROM game_settings");
        if ($stmt) {
            foreach ($stmt as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return $settings;
    }

    private function getCurrentDatabaseTime($db) {
        $stmt = $db->query("SELECT UNIX_TIMESTAMP(NOW()) as db_now");
        $now = (int)$stmt->fetchColumn();
        return $now ? $now : time();
    }

    private function getPlayerProfile($db, $userId, $defaultWarehouseLvl) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!isset($player['warehouse_level'])) $player['warehouse_level'] = $defaultWarehouseLvl;
        return $player;
    }

    private function getWarehouseUpgradeCost($db, $nextLevel, $warehouseLevel, $defaultCost) {
        $stmt = $db->prepare("SELECT * FROM warehouse_level_matrix WHERE level = ?");
        $stmt->execute([$nextLevel]);
        $meta = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $meta ? (float)$meta['upgrade_cost'] : ($warehouseLevel * $defaultCost);
    }

    private function calculateSoilMultiplier($b, $researches) {
        $category = strtolower(trim($b['category'] ?? ''));
        $soilMultiplier = 1.0;

        if (str_contains($category, 'agri') || str_contains($category, 'tarım') || str_contains($category, 'bahçe')) {
            $soilMultiplier = (float)($b['agri_multiplier'] ?? 1.0);
            $soilMultiplier *= (1.0 + (($researches['rBio'] - 1) * 0.02));
        } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) {
            $soilMultiplier = (float)($b['ind_multiplier'] ?? 1.0);
        } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
            $soilMultiplier = (float)($b['mine_multiplier'] ?? 1.0);
        }
        return $soilMultiplier <= 0 ? 1.0 : $soilMultiplier;
    }

    private function processBuildingInputs($db, $rawInputs, $productionRate, $inventoryCostMap) {
        $inputs = [];
        $inputMaterialCost = 0;

        foreach ($rawInputs as $inp) {
            $scaledInputAmount = round(($inp['required_amount'] ?? 1.0) * $productionRate, 2);
            $pId = $inp['product_id'] ?? 0;
            
            $mCost = $inventoryCostMap[$pId] ?? 0;
            if ($mCost <= 0) {
                $matCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                $matCostStmt->execute([$pId]);
                $mCost = (float)($matCostStmt->fetchColumn() ?? 0.0);
            }
            
            $rowCost = round($mCost * $scaledInputAmount, 2);
            $inputMaterialCost += $rowCost;

            $inputs[] = [
                'product_id' => $pId,
                'product_name' => $inp['product_name'] ?? 'Hammadde',
                'product_image' => $inp['product_image'] ?? '',
                'unit_type' => $inp['unit_type'] ?? 'Adet',
                'amount' => $scaledInputAmount,
                'unit_cost' => (float)$mCost,          
                'total_cost' => (float)$rowCost        
            ];
        }
        return [$inputs, $inputMaterialCost];
    }

    private function calculateOperationalExpenses($b, $researches, $amortismanBedeli, $inputMaterialCost, $productionAmount) {
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

        $wearRateVal = (float)($b['wear_rate'] ?? 2.0);
        $calculatedWearCost = round($wearRateVal * $amortismanBedeli, 2);

        $totalProductionCost = round($inputMaterialCost + $totalLaborExpense + $nakliyeCost + $vergiCost + $calculatedWearCost, 2);
        $cashDeductionCost = round($totalLaborExpense + $nakliyeCost + $vergiCost, 2);
        
        $calculatedUnitCost = ($productionAmount > 0) ? round($totalProductionCost / $productionAmount, 2) : 0.00;

        return [
            'energy_consumption' => $energyRequired,
            'labor_required' => (int)$laborReq,
            'wear_rate' => $wearRateVal,
            'amortisman_bedeli' => $amortismanBedeli,
            'calculated_wear_cost' => $calculatedWearCost,
            'total_labor_expense' => $totalLaborExpense,
            'base_transport_cost' => $nakliyeCost,
            'base_tax_rate' => $vergiCost,
            'input_material_cost' => $inputMaterialCost,
            'production_cost' => $totalProductionCost,      
            'cash_deduction_cost' => $cashDeductionCost,
            'unit_cost' => $calculatedUnitCost              
        ];
    }

    private function evaluateBuildingStatus($db, $b, $inventoryMap, $currentEnergyStock, $inputs, $productionSec, $now) {
        $health = (float)($b['condition_health'] ?? 100.0);
        $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
        if ($phaseStartedTs <= 0) $phaseStartedTs = $now;

        $pausedTs = (int)($b['paused_ts'] ?? 0);
        $isPaused = (int)($b['is_paused'] ?? 0) === 1;
        $elapsedTime = ($isPaused && $pausedTs > 0) ? ($pausedTs - $phaseStartedTs) : ($now - $phaseStartedTs);

        $bufferData = !empty($b['buffer_inventory']) ? json_decode($b['buffer_inventory'], true) : [];
        $isCurrentlyProducing = !empty($bufferData);
        $hasEnoughInput = true;
        $missingInputsList = [];

        if (!$isCurrentlyProducing && !empty($inputs)) {
            foreach ($inputs as $input) {
                $stock = $inventoryMap[$input['product_id']] ?? 0.0;
                if ($stock < $input['amount']) {
                    $hasEnoughInput = false;
                    $missingInputsList[] = "{$input['product_name']} (Gereken: {$input['amount']}, Mevcut: {$stock})";
                }
            }
        }

        $energyRequired = (float)($b['energy_consumption'] ?? 50);
        $hasEnoughEnergy = ($currentEnergyStock >= $energyRequired);
        $warningMessage = '';

        if ($health < 50.0) {
            $warningMessage = "Kritik Kondisyon! Sağlık %50 altına düştüğü için üretim durduruldu.";
        } elseif (!$hasEnoughEnergy) {
            $warningMessage = "Yetersiz Enerji! Depoda gereken {$energyRequired} kwh enerji yok.";
        } elseif (!empty($inputs) && !$hasEnoughInput && !$isCurrentlyProducing) {
            $warningMessage = "Hammadde Bekleniyor: " . implode(', ', $missingInputsList);
        }

        $cycleProgressTime = $elapsedTime % $productionSec;

        if ($health < 50.0 || !$hasEnoughEnergy || (!empty($inputs) && !$hasEnoughInput && !$isCurrentlyProducing)) {
            $remSec = 0;
            $progress = 0;
            if ($health < 50.0) $barText = "ÜRETİM DURDURULDU (BAKIM GEREKLİ)";
            elseif (!$hasEnoughEnergy) $barText = "ÜRETİM DURDURULDU (ENERJİ YOK)";
            else $barText = "HAMMADDE BEKLENİYOR...";
            $barColor = "bg-danger";
        } elseif ($isPaused) {
            $remSec = max(0, $productionSec - $cycleProgressTime);
            $progress = (($productionSec - $remSec) / $productionSec) * 100;
            $barText = "DURAKLATILDI";
            $barColor = "bg-warning";
        } else {
            $remSec = max(0, $productionSec - $cycleProgressTime);
            $progress = ($cycleProgressTime / $productionSec) * 100;
            $barText = "Üretim Aktif";
            $barColor = "bg-success progress-bar-animated progress-bar-striped";
        }

        return [
            'is_paused' => $isPaused,
            'is_currently_producing' => $isCurrentlyProducing,
            'has_enough_input' => $hasEnoughInput,
            'warning_message' => $warningMessage,
            'rem_sec' => $remSec,
            'progress' => $progress,
            'bar_text' => $barText,
            'bar_color' => $barColor
        ];
    }

    private function calculateRepairCost($b, $level, $health) {
        $baseBuildingCost = (float)($b['base_cost'] ?? 1000);
        $repairCost = round(($baseBuildingCost * $level) * ((100.0 - $health) / 100.0), 2);
        return ($repairCost < 100 && $health < 100) ? 100 : $repairCost;
    }

    private function attachBuildingLogs($db, $b) {
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

        return $b;
    }

    private function getPlayerRowData($db, $userId) {
        $stmt = $db->prepare("SELECT warehouse_level, money FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $player ? $player : ['warehouse_level' => 1, 'money' => 0];
    }

    private function getEnergyProductId($db) {
        $stmt = $db->prepare("SELECT id FROM products WHERE name LIKE '%Enerji%' OR name LIKE '%Elektrik%' LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 0;
    }

    private function getUserBuildingsWithDetails($db, $userId) {
        $stmt = $db->prepare("SELECT pb.*, pb.id as building_id, 
                                     UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
                                     bt.produces_product_id, bt.category,
                                     bt.id as building_type_id,
                                     p.name as product_name, p.unit_type as unit_type, p.unit_cost as product_base_cost,
                                     prov.mine_multiplier, prov.labor_cost_multiplier,
                                     prov.agri_multiplier, prov.ind_multiplier,
                                     mlb.production_rate, mlb.energy_consumption, mlb.wear_rate, mlb.labor_required, mlb.base_labor_cost,
                                     mlb.base_transport_cost, mlb.base_tax_rate
                              FROM user_buildings pb 
                              JOIN building_types bt ON pb.building_type_id = bt.id 
                              LEFT JOIN products p ON bt.produces_product_id = p.id
                              JOIN provinces prov ON pb.province_id = prov.id 
                              LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
                              WHERE pb.user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function resetBuildingPhase($db, $bId) {
        $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ?")->execute([$bId]);
    }

    private function calculateProductionAmount($building, $researches, $baseOutputAmount, $productionRate, $health) {
        $category = strtolower(trim($building['category'] ?? ''));
        $soilMultiplier = 1.0;

        if (str_contains($category, 'agri') || str_contains($category, 'tarım') || str_contains($category, 'bahçe')) {
            $soilMultiplier = (float)($building['agri_multiplier'] ?? 1.0) * (1.0 + (($researches['rBio'] - 1) * 0.02));
        } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) {
            $soilMultiplier = (float)($building['ind_multiplier'] ?? 1.0);
        } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
            $soilMultiplier = (float)($building['mine_multiplier'] ?? 1.0);
        }
        if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

        $healthFactor = max(0, $health / 100.0);
        $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);

        return round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);
    }

    private function calculateCashCost($building, $researches, $config) {
        $laborReq = (float)($building['labor_required'] ?? $config['default_labor_req']);
        $baseLaborCost = (float)($building['base_labor_cost'] ?? $config['default_labor_cost']);
        $laborMult = (float)($building['labor_cost_multiplier'] ?? 1.0);
        $baseTransport = (float)($building['base_transport_cost'] ?? $config['default_transport']);
        $baseTax = (float)($building['base_tax_rate'] ?? $config['default_tax']);

        $logDiscount = max(0.5, 1.0 - (($researches['rLog'] - 1) * 0.05));
        $finDiscount = max(0.6, 1.0 - (($researches['rFin'] - 1) * 0.04));

        return round(($laborReq * $baseLaborCost * $laborMult) + ($baseTransport * $logDiscount) + ($baseTax * $finDiscount), 2);
    }

    private function getRawBuildings($db, $userId, $activeCategory) {
        $query = "SELECT pb.*, pb.id as building_id, 
                         UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
                         UNIX_TIMESTAMP(pb.paused_at) as paused_ts,
                         UNIX_TIMESTAMP(pb.created_at) as created_ts,
                         bt.id as building_type_id, bt.name as building_name,
                         bt.category, bt.complexity_type, bt.base_cost, bt.unit_type, bt.image as building_image,
                         bt.produces_product_id, p.name as product_name, p.unit_type as product_unit_type,
                         p.image as product_image, p.unit_cost as product_base_cost,
                         prov.name as province_name, prov.population, prov.labor_cost_multiplier, 
                         prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, prov.latitude, prov.longitude,
                         mlb.wear_rate, mlb.water_consumption, mlb.labor_required, mlb.energy_consumption,
                         mlb.base_tax_rate, mlb.production_rate, mlb.base_labor_cost, mlb.base_transport_cost
                  FROM user_buildings pb 
                  JOIN building_types bt ON pb.building_type_id = bt.id 
                  LEFT JOIN products p ON bt.produces_product_id = p.id
                  JOIN provinces prov ON pb.province_id = prov.id 
                  LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
                  WHERE pb.user_id = ? AND bt.category = ?
                  ORDER BY pb.id DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId, $activeCategory]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getInventoryMaps($db, $userId) {
        $invStmt = $db->prepare("SELECT product_id, quantity, unit_cost FROM user_inventories WHERE user_id = ?");
        $invStmt->execute([$userId]);
        $inventoryMap = [];
        $inventoryCostMap = [];
        foreach ($invStmt->fetchAll(\PDO::FETCH_ASSOC) as $invRow) {
            $inventoryMap[$invRow['product_id']] = (float)$invRow['quantity'];
            $inventoryCostMap[$invRow['product_id']] = (float)($invRow['unit_cost'] ?? 0);
        }
        return [$inventoryMap, $inventoryCostMap];
    }

    private function getEnergyStock($db, $inventoryMap) {
        $energyProdStmt = $db->prepare("SELECT id FROM products WHERE name LIKE '%Enerji%' OR name LIKE '%Elektrik%' LIMIT 1");
        $energyProdStmt->execute();
        $energyProductId = $energyProdStmt->fetchColumn() ?: 0;
        return $inventoryMap[$energyProductId] ?? 0.0;
    }

    private function getFullInventory($db, $userId) {
        $invStmt = $db->prepare("SELECT inv.*, p.name as product_name, p.type, p.unit_type, p.image FROM user_inventories inv JOIN products p ON inv.product_id = p.id WHERE inv.user_id = ?");
        $invStmt->execute([$userId]);
        return $invStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /*** Üretimi Hızlı Tamamlama Fonksiyonu (Finish) */
    public function finish() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);

            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            $stmt = $db->prepare("SELECT pb.*, bt.produces_product_id, bt.category,
                                        p.name as product_name, p.unit_type as product_unit_type,
                                        prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier, prov.labor_cost_multiplier,
                                        mlb.production_rate, mlb.labor_required, mlb.base_labor_cost, mlb.base_transport_cost, mlb.base_tax_rate, mlb.wear_rate
                                  FROM user_buildings pb 
                                  JOIN building_types bt ON pb.building_type_id = bt.id 
                                  LEFT JOIN products p ON bt.produces_product_id = p.id
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
                if (str_contains($category, 'agri') || str_contains($category, 'tarım') || str_contains($category, 'bahçe')) {
                    $soilMultiplier = (float)$b['agri_multiplier'] * (1.0 + (($researches['rBio'] - 1) * 0.02));
                } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) {
                    $soilMultiplier = (float)($b['ind_multiplier'] ?? 1.0);
                } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
                    $soilMultiplier = (float)($b['mine_multiplier'] ?? 1.0);
                }
                if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

                $health = (float)($b['condition_health'] ?? 100.0);
                if ($health < 50.0) throw new \Exception('Tesis sağlığı %50 altında, işlem yapılamaz!');

                $healthFactor = max(0, $health / 100.0);
                $productionRate = (float)$b['production_rate'];
                $rndProdBonus = 1.0 + (($researches['rInd'] - 1) * 0.03) + (($researches['rTech'] - 1) * 0.02);

                $outputs = $this->getBuildingOutputs($db, $b['building_type_id']);
                $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;
                
                $prodAmount = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $rndProdBonus, 2);

                $productName = $b['product_name'] ?? 'Mamul';
                $productUnit = $b['product_unit_type'] ?? 'Adet';

                $laborReq = (float)$b['labor_required'];
                $baseLaborCost = (float)$b['base_labor_cost'];
                $laborMult = (float)$b['labor_cost_multiplier'];
                $baseTransport = (float)$b['base_transport_cost'];
                $baseTax = (float)$b['base_tax_rate'];

                $rawInputs = $this->getBuildingInputs($db, $b['building_type_id']);
                $inputMaterialCost = 0;
                $bufferData = !empty($b['buffer_inventory']) ? json_decode($b['buffer_inventory'], true) : [];

                foreach ($rawInputs as $input) {
                    $scaledReq = round($input['required_amount'] * $productionRate, 2);

                    if (!isset($bufferData[$input['product_id']]) || $bufferData[$input['product_id']] < $scaledReq) {
                        $chk = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                        $chk->execute([$userId, $input['product_id']]);
                        $invItem = $chk->fetch(\PDO::FETCH_ASSOC);
                        $currentStock = (float)($invItem['quantity'] ?? 0);

                        if ($currentStock < $scaledReq) {
                            throw new \Exception('Yetersiz Hammadde! Depoda gerekli girdi bulunmuyor.');
                        }

                        $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                        ->execute([$scaledReq, $userId, $input['product_id']]);
                    }

                    $itemCostQuery = $db->prepare("SELECT unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ?");
                    $itemCostQuery->execute([$userId, $input['product_id']]);
                    $invItemCost = $itemCostQuery->fetch(\PDO::FETCH_ASSOC);
                    $itemCost = (float)($invItemCost['unit_cost'] ?? 0);

                    if ($itemCost <= 0) {
                        $pCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                        $pCostStmt->execute([$input['product_id']]);
                        $itemCost = (float)$pCostStmt->fetchColumn();
                    }
                    $inputMaterialCost += round($itemCost * $scaledReq, 2);
                }

                $logDiscount = max(0.5, 1.0 - (($researches['rLog'] - 1) * 0.05));
                $finDiscount = max(0.6, 1.0 - (($researches['rFin'] - 1) * 0.04));

                $totalLaborExpense = round($laborReq * $baseLaborCost * $laborMult, 2);
                $nakliyeCost = round($baseTransport * $logDiscount, 2);
                $vergiCost = round($baseTax * $finDiscount, 2);

                // Amortisman payını da maliyete dahil edelim
                $settingsStmt = $db->query("SELECT setting_value FROM game_settings WHERE setting_key = 'amortisman_rate'");
                $amortismanRate = $settingsStmt ? (float)($settingsStmt->fetchColumn() ?? 2.0) : 2.0;
                $wearRatePerCycle = (float)($b['wear_rate'] ?? 2.0);
                $calculatedWearCost = round($wearRatePerCycle * $amortismanRate, 2);

                $totalProductionCost = round($inputMaterialCost + $totalLaborExpense + $nakliyeCost + $vergiCost + $calculatedWearCost, 2);
                
                // Birim maliyet artık 0 çıkmayacak, doğru şekilde hesaplanacak
                $calculatedUnitCost = ($prodAmount > 0) ? round($totalProductionCost / $prodAmount, 2) : 0.00;

                $cashDeductionCost = round($totalLaborExpense + $nakliyeCost + $vergiCost, 2);
                $finishCost = round($cashDeductionCost * 1.20, 2);

                $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();
                if ($playerMoney < $finishCost) throw new \Exception('Yetersiz bütçe! Hızlı bitirme maliyeti karşılanamıyor.');

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
                    $costLog = "{$prodAmount} {$productUnit} {$productName} üretimi {$finishCost}₺ karşılığında tamamlandı.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
                    ->execute([$buildingId, $producesId, $finishCost, $costLog]);
                }

                if (!empty($producesId) && $prodAmount > 0) {
                    if ($invRow) {
                        $oldQty = (float)$invRow['quantity'];
                        $oldCost = (float)$invRow['unit_cost'];
                        $newTotalQty = round($oldQty + $prodAmount, 2);
                        
                        $blendedUnitCost = $newTotalQty > 0 ? (($oldQty * $oldCost) + ($prodAmount * $calculatedUnitCost)) / $newTotalQty : $calculatedUnitCost;

                        $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                        ->execute([$newTotalQty, round($blendedUnitCost, 2), $userId, $producesId]);
                    } else {
                        $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                        ->execute([$userId, $producesId, $prodAmount, $calculatedUnitCost]);
                    }

                    $logDesc = "Üretim tamamlandı (Hızlı): {$prodAmount} {$productUnit} {$productName} depoya eklendi.";
                    $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
                    ->execute([$buildingId, $producesId, $prodAmount, $logDesc]);
                }

                $newHealth = max(0.0, round($health - $wearRatePerCycle, 2));
                $db->prepare("UPDATE user_buildings SET condition_health = ? WHERE id = ?")->execute([$newHealth, $buildingId]);

                $wearLogDesc = "Hızlı üretim nedeniyle tesis %{$wearRatePerCycle} oranında aşındı. Yeni Kondisyon: %{$newHealth}";
                $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'wear', NULL, ?, ?, NOW())")
                ->execute([$buildingId, $wearRatePerCycle, $wearLogDesc]);

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
            }
            redirect('/app/playerbuilding');
        }
    }

    /** Envanter Detay ve Grafik Verilerini Çekme */
    public function getInventoryItemDetail() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['product_id'])) {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

            $invStmt = $db->prepare("SELECT inv.*, p.name as product_name, p.unit_cost as base_cost, p.type FROM user_inventories inv JOIN products p ON inv.product_id = p.id WHERE inv.user_id = ? AND inv.product_id = ?");
            $invStmt->execute([$userId, $productId]);
            $item = $invStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                echo json_encode(['status' => 'error', 'message' => 'Ürün bulunamadı.']);
                exit;
            }

            $logStmt = $db->prepare("SELECT bl.*, b.id as b_id, bt.name as building_name 
                                     FROM building_logs bl 
                                     JOIN user_buildings b ON bl.building_id = b.id 
                                     JOIN building_types bt ON b.building_type_id = bt.id 
                                     WHERE b.user_id = ? AND bl.product_id = ? 
                                     ORDER BY bl.id DESC LIMIT 25");
            $logStmt->execute([$userId, $productId]);
            $logs = $logStmt->fetchAll(\PDO::FETCH_ASSOC);

            $costLogStmt = $db->prepare("SELECT bl.* FROM building_logs bl JOIN user_buildings b ON bl.building_id = b.id WHERE b.user_id = ? AND bl.product_id = ? AND (bl.action_type = 'cost' OR bl.action_type = 'repair') ORDER BY bl.id DESC LIMIT 10");
            $costLogStmt->execute([$userId, $productId]);
            $costLogs = $costLogStmt->fetchAll(\PDO::FETCH_ASSOC);

            $energyLogStmt = $db->prepare("SELECT bl.* FROM building_logs bl JOIN user_buildings b ON bl.building_id = b.id WHERE b.user_id = ? AND bl.product_id = ? AND bl.action_type = 'consumption' ORDER BY bl.id DESC LIMIT 10");
            $energyLogStmt->execute([$userId, $productId]);
            $energyLogs = $energyLogStmt->fetchAll(\PDO::FETCH_ASSOC);

            $ch1Labels = []; $ch1Prod = []; $ch1Cons = [];
            foreach (array_reverse($logs) as $log) {
                $ch1Labels[] = date('H:i:s', strtotime($log['created_at']));
                if ($log['action_type'] === 'production') {
                    $ch1Prod[] = (float)$log['amount'];
                    $ch1Cons[] = 0;
                } elseif ($log['action_type'] === 'consumption') {
                    $ch1Prod[] = 0;
                    $ch1Cons[] = (float)$log['amount'];
                } else {
                    $ch1Prod[] = 0;
                    $ch1Cons[] = 0;
                }
            }

            $ch2Labels = []; $ch2Data = [];
            foreach (array_reverse($costLogs) as $clog) {
                $ch2Labels[] = date('H:i:s', strtotime($clog['created_at']));
                $ch2Data[] = (float)$clog['amount'];
            }

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

    /** Envanter Alım/Satım İşlemi (Trade) */
    public function tradeInventoryItem() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $productId = (int)($_POST['product_id'] ?? 0);
            $actionType = $_POST['action_type'] ?? '';
            $amount = (float)($_POST['amount'] ?? 0);

            if ($amount <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz miktar!']);
                exit;
            }

            try {
                $db->beginTransaction();
                $pStmt = $db->prepare("SELECT unit_cost, name FROM products WHERE id = ?");
                $pStmt->execute([$productId]);
                $prod = $pStmt->fetch(\PDO::FETCH_ASSOC);
                if (!$prod) throw new \Exception('Ürün bulunamadı.');

                $basePrice = (float)($prod['unit_cost'] > 0 ? $prod['unit_cost'] : 10.00);

                $uStmt = $db->prepare("SELECT money FROM users WHERE id = ? FOR UPDATE");
                $uStmt->execute([$userId]);
                $userMoney = (float)$uStmt->fetchColumn();

                $iStmt = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
                $iStmt->execute([$userId, $productId]);
                $inv = $iStmt->fetch(\PDO::FETCH_ASSOC);
                $currentStock = (float)($inv['quantity'] ?? 0);

                if ($actionType === 'buy') {
                    $totalCost = round($basePrice * $amount * 1.15, 2);
                    if ($userMoney < $totalCost) throw new \Exception('Yetersiz bütçe!');

                    $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$totalCost, $userId]);

                    $boughtUnitCost = round($basePrice * 1.15, 2);

                    if ($inv) {
                        $newQty = round($currentStock + $amount, 2);
                        $oldQty = (float)$inv['quantity'];
                        $oldCost = (float)$inv['unit_cost'];
                        
                        $newCost = $newQty > 0 ? (($oldQty * $oldCost) + ($amount * $boughtUnitCost)) / $newQty : $boughtUnitCost;

                        $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
                        ->execute([$newQty, round($newCost, 2), $userId, $productId]);
                    } else {
                        $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                        ->execute([$userId, $productId, $amount, $boughtUnitCost]);
                    }
                } elseif ($actionType === 'sell') {
                    if ($currentStock < $amount) throw new \Exception('Depoda yeterli ürün yok!');

                    $totalRevenue = round($basePrice * $amount * 0.85, 2);
                    $db->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$totalRevenue, $userId]);

                    $newQty = round($currentStock - $amount, 2);
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
        $stmt = $db->prepare("SELECT bi.*, p.name as product_name, p.unit_type, p.image as product_image FROM building_inputs bi JOIN products p ON bi.product_id = p.id WHERE bi.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $inputs = [];
        foreach ($rows as $row) {
            $inputs[] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'] ?? 'Ürün',
                'unit_type' => $row['unit_type'] ?? 'Birim',
                'required_amount' => (float)$row['amount'],
                'product_image' => $row['product_image'] ?? ''
            ];
        }
        return $inputs;
    }

    private function getBuildingOutputs($db, $buildingTypeId) {
        $stmt = $db->prepare("SELECT bo.*, p.name as product_name, p.unit_type, p.image as product_image 
                                FROM building_outputs bo 
                                JOIN products p ON bo.product_id = p.id 
                                WHERE bo.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $outputs = [];
        foreach ($rows as $row) {
            $outputs[] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'] ?? 'Ürün',
                'unit_type' => $row['unit_type'] ?? 'Birim',
                'amount' => (float)$row['amount'],
                'product_image' => $row['product_image'] ?? ''
            ];
        }
        return $outputs;
    }

    private function getPlayerResearches($db, $userId) {
        $stmt = $db->prepare("SELECT * FROM user_researches WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return [
            'rBio' => (int)($row['bio_level'] ?? 1),
            'rInd' => (int)($row['ind_level'] ?? 1),
            'rTech' => (int)($row['tech_level'] ?? 1),
            'rLog' => (int)($row['log_level'] ?? 1),
            'rFin' => (int)($row['fin_level'] ?? 1),
        ];
    }

    public function togglePause() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);
            
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            $stmt = $db->prepare("SELECT is_paused, paused_at FROM user_buildings WHERE id = ? AND user_id = ?");
            $stmt->execute([$buildingId, $userId]);
            $b = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($b) {
                $isPaused = (int)$b['is_paused'];
                if ($isPaused === 1) {
                    $db->prepare("UPDATE user_buildings SET is_paused = 0, phase_started_at = DATE_ADD(phase_started_at, INTERVAL TIMESTAMPDIFF(SECOND, paused_at, NOW()) SECOND), paused_at = NULL WHERE id = ?")
                    ->execute([$buildingId]);
                } else {
                    $db->prepare("UPDATE user_buildings SET is_paused = 1, paused_at = NOW() WHERE id = ?")
                    ->execute([$buildingId]);
                }

                if ($isAjax) {
                    echo json_encode(['status' => 'success']);
                    exit;
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
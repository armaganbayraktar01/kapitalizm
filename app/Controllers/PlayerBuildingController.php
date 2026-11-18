<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class PlayerBuildingController extends Controller
{
    /**
     * Ana Index Fonksiyonu (Akış Yöneticisi - Orchestrator)
     */
    public function index()
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $activeCategory = basename($_GET['url'] ?? '');

        // 1. Oyun Ayarlarını ve Sabitleri Yükle
        $settings = $this->loadGameSettings($db);
        $productionSec = (int)($settings['production_seconds'] ?? 600);
        $defaultWarehouseLvl = (int)($settings['default_warehouse_lvl'] ?? 1);
        $slotMultiplier = (float)($settings['slot_multiplier'] ?? 500.00);
        $amortismanBedeli = (float)($settings['amortisman_rate'] ?? 2.0);

        // 2. Zaman ve Arka Plan Üretim Motoru Tetikleyicisi
        $now = $this->getCurrentDatabaseTime($db);
        try {
            $this->processProductionCycles($db, $userId, $now);
        } catch (\Exception $e) {
            error_log("Üretim Motoru Tetiklenme Hatası: " . $e->getMessage());
        }

        // 3. Oyuncu, Depo, Araştırma ve Envanter Bilgileri
        $player = $this->getPlayerProfile($db, $userId, $defaultWarehouseLvl);
        $warehouseLevel = (int)$player['warehouse_level'];
        $productSlotLimit = $warehouseLevel * $slotMultiplier;
        $researches = $this->getPlayerResearches($db, $userId);

        $rawBuildings = $this->getRawBuildings($db, $userId, $activeCategory);
        [$inventoryMap, $inventoryCostMap] = $this->getInventoryMaps($db, $userId);
        $currentEnergyStock = $this->getEnergyStock($db, $inventoryMap);

        $my_buildings = [];
        foreach ($rawBuildings as $b) {
            $buildingTypeKey = $b['building_type_id'];
            $level = (int)($b['level'] ?? 1);
            $health = (float)($b['condition_health'] ?? 100.0);
            $buildingCycleSec = (int)($b['product_production_seconds'] ?? $productionSec);

            [$soilMultiplier, $healthFactor, $productionAmount, $baseOutputAmount, $productionRate] =
                $this->calculateProductionAmountWithDetails($db, $b, $researches, $health);

            if ($health < 50.0) {
                $productionAmount = 0.0;
            }

            $rawInputs = $this->getBuildingInputs($db, $buildingTypeKey);
            [$inputs, $inputMaterialCost] = $this->processBuildingInputs($db, $rawInputs, $productionRate, $inventoryCostMap, $researches);

            $financeData = $this->calculateOperationalExpenses($b, $researches, $amortismanBedeli, $inputMaterialCost, $productionAmount);
            $statusData = $this->evaluateBuildingStatus($db, $b, $inventoryMap, $currentEnergyStock, $inputs, $buildingCycleSec, $now);

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
            $b['cycle_seconds'] = $buildingCycleSec;

            $b['formula_details'] = array_merge($financeData, [
                'production_rate'    => $productionRate,
                'product_image'      => $b['product_image'],
                'unit_type'          => $b['unit_type'],
                'soil_multiplier'    => $soilMultiplier,
                'health_factor'      => $healthFactor,
                'base_output_amount' => $baseOutputAmount,
                'unit_label'         => $b['unit_type'] ?? 'Adet',
                'has_enough_input'   => ($statusData['has_enough_input'] || $statusData['is_currently_producing']),
                'inputs'             => $inputs,
                'warning_message'    => $statusData['warning_message'],
                'researches'         => $researches
            ]);

            $b = $this->attachBuildingLogs($db, $b);
            $my_buildings[] = $b;
        }

        // 4. Şirket Kasa Özeti ve Hareketleri
        $cashSummaryStmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN transaction_type = 'in' THEN amount ELSE 0 END), 0) AS total_in,
                COALESCE(SUM(CASE WHEN transaction_type = 'out' THEN amount ELSE 0 END), 0) AS total_out
            FROM company_cash_logs 
            WHERE user_id = ?
        ");
        $cashSummaryStmt->execute([$userId]);
        $company_cash_summary = $cashSummaryStmt->fetch(\PDO::FETCH_ASSOC) ?: ['total_in' => 0, 'total_out' => 0];

        $cashLogsStmt = $db->prepare("
            SELECT 
                ccl.*,
                COALESCE(rs.store_name, 'Şirket Geneli') AS store_name
            FROM company_cash_logs ccl
            LEFT JOIN retail_stores rs ON ccl.store_id = rs.id
            WHERE ccl.user_id = ?
            ORDER BY ccl.id DESC
            LIMIT 100
        ");
        $cashLogsStmt->execute([$userId]);
        $company_cash_logs = $cashLogsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 5. View'e Aktar
        $this->view('app/playerbuilding/index', [
            'my_buildings'         => $my_buildings,
            'building_types'       => $this->getBuildingTypes($db),
            'provinces'            => $this->getProvinces($db),
            'player'               => $player,
            'inventory'            => $this->getFullInventory($db, $userId),
            'active_category'      => $activeCategory,
            'now'                  => $now,
            'warehouse_summary'    => [
                'level'        => $warehouseLevel,
                'slot_limit'   => $productSlotLimit,
                'upgrade_cost' => $this->getWarehouseUpgradeCost(
                    $db,
                    $warehouseLevel + 1,
                    $warehouseLevel,
                    $settings['warehouse_upgrade_base_cost'] ?? 10000.00
                )
            ],
            'arge_cards'           => $db->query("SELECT * FROM research_cards ORDER BY price ASC")->fetchAll(\PDO::FETCH_ASSOC),
            'user_owned_cards_map' => [],
            'company_cash_summary' => $company_cash_summary,
            'company_cash_logs'    => $company_cash_logs
        ]);
    }

    /**
     * AJAX İstek Karşılayıcı (Frontend sayaç bitiminde tekil üretimi tetikler)
     */
    public function ajaxProcessProduction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse('error', 'Geçersiz istek yöntemi.');
        }

        try {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $buildingId = (int)($_POST['building_id'] ?? 0);
            $now = $this->getCurrentDatabaseTime($db);

            if ($buildingId <= 0) {
                $this->sendJsonResponse('error', 'Geçersiz tesis ID.', ['building_id' => $buildingId]);
            }

            $result = $this->processSingleBuildingCycle($db, $userId, $buildingId, $now);

            if (is_array($result) && !empty($result['success'])) {
                $this->sendJsonResponse('success', 'Üretim tamamlandı', ['building_id' => $buildingId]);
            } else {
                $this->sendJsonResponse('error', $result['message'] ?? 'Üretim gerçekleştirilemedi.', ['building_id' => $buildingId]);
            }
        } catch (\Throwable $e) {
            $this->sendJsonResponse('error', 'Kritik Hata: ' . $e->getMessage(), ['building_id' => $buildingId ?? 0]);
        }
    }

    /**
     * Hızlı Üretim Tamamlama (Finish) Endpoint'i
     */
    public function finish()
    {
        $this->ajaxProcessProduction();
    }

    /**
     * Yeni Tesis Kurulum İşlemi (Modal Form POST)
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse('error', 'Geçersiz istek yöntemi.');
        }

        try {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            $buildingTypeId = (int)($_POST['building_type_id'] ?? $_POST['building_id'] ?? 0);
            $provinceId = (int)($_POST['province_id'] ?? 0);

            if ($buildingTypeId <= 0 || $provinceId <= 0) {
                $this->sendJsonResponse('error', 'Lütfen geçerli bir tesis türü ve il seçiniz.');
            }

            $buildingTypeStmt = $db->prepare("SELECT * FROM building_types WHERE id = ?");
            $buildingTypeStmt->execute([$buildingTypeId]);
            $buildingType = $buildingTypeStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$buildingType) {
                $this->sendJsonResponse('error', 'Seçilen tesis türü sistemde bulunamadı.');
            }

            $cost = (float)($buildingType['base_cost'] ?? $buildingType['cost'] ?? 10000.00);
            $player = $this->getPlayerRowData($db, $userId);
            $playerMoney = (float)($player['money'] ?? 0);

            if ($playerMoney < $cost) {
                $this->sendJsonResponse(
                    'error',
                    'Yetersiz bütçe! Gereken: ' . number_format($cost, 2, ',', '.') . '₺, Mevcut: ' . number_format($playerMoney, 2, ',', '.') . '₺'
                );
            }

            $db->beginTransaction();

            // Merkezi Kasa Fonksiyonu ile parayı düş ve logla
            $description = "Yeni tesis yatırımı: {$buildingType['name']} (İl ID: {$provinceId})";
            $this->recordCashTransaction($db, $userId, $cost, 'out', 'investment_expense', $description, null);

            // Tesisi veritabanına kaydet
            $insertBuilding = $db->prepare("
                INSERT INTO user_buildings (user_id, building_type_id, province_id, status, created_at, phase_started_at)
                VALUES (?, ?, ?, 'producing', NOW(), NOW())
            ");
            $insertBuilding->execute([$userId, $buildingTypeId, $provinceId]);
            $newBuildingId = $db->lastInsertId();

            $db->commit();

            $this->sendJsonResponse('success', 'Tesis başarıyla kuruldu.', ['building_id' => $newBuildingId]);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->sendJsonResponse('error', 'Sunucu hatası: ' . $e->getMessage());
        }
    }

    /**
     * Tesis Duraklat / Devam Ettir (Pause / Resume)
     */
    public function togglePause()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $buildingId = (int)($_POST['building_id'] ?? 0);
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $stmt = $db->prepare("SELECT is_paused, paused_at FROM user_buildings WHERE id = ? AND user_id = ?");
        $stmt->execute([$buildingId, $userId]);
        $b = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($b) {
            if ((int)$b['is_paused'] === 1) {
                $db->prepare("UPDATE user_buildings SET is_paused = 0, phase_started_at = DATE_ADD(phase_started_at, INTERVAL TIMESTAMPDIFF(SECOND, paused_at, NOW()) SECOND), paused_at = NULL WHERE id = ?")
                   ->execute([$buildingId]);
            } else {
                $db->prepare("UPDATE user_buildings SET is_paused = 1, paused_at = NOW() WHERE id = ?")
                   ->execute([$buildingId]);
            }

            if ($isAjax) {
                $this->sendJsonResponse('success', 'Tesis durumu güncellendi.');
            }
        }

        $this->redirectBack();
    }

    /**
     * Tesis Bakım ve Onarım İşlemi
     */
    public function repair()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $buildingId = (int)($_POST['building_id'] ?? 0);

        $stmt = $db->prepare("SELECT ub.*, bt.base_cost FROM user_buildings ub JOIN building_types bt ON ub.building_type_id = bt.id WHERE ub.id = ? AND ub.user_id = ?");
        $stmt->execute([$buildingId, $userId]);
        $building = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($building) {
            $health = (float)$building['condition_health'];
            if ($health < 100.0) {
                $repairCost = $this->calculateRepairCost($building, (int)$building['level'], $health);
                $playerMoney = (float)$db->query("SELECT money FROM users WHERE id = {$userId}")->fetchColumn();

                if ($playerMoney >= $repairCost) {
                    try {
                        $db->beginTransaction();

                        // Merkezi Kasa Logu ile parayı düş
                        $this->recordCashTransaction($db, $userId, $repairCost, 'out', 'building_maintenance', "Tesis Bakım Onarımı (#{$buildingId})", null);

                        // Kondisyonu %100 yap, duraklatmayı kaldır
                        $db->prepare("UPDATE user_buildings SET condition_health = 100.00, is_paused = 0, paused_at = NULL, phase_started_at = NOW() WHERE id = ?")
                           ->execute([$buildingId]);

                        $repairLogDesc = "Tesis bakımı yapıldı, kondisyon %100'e çıkarıldı. Ödenen Bedel: {$repairCost}₺";
                        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'repair', NULL, ?, ?, NOW())")
                           ->execute([$buildingId, $repairCost, $repairLogDesc]);

                        $db->commit();
                    } catch (\Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                    }
                }
            }
        }

        $this->redirectBack();
    }

    /**
     * Merkez Depo Seviye Yükseltme
     */
    public function upgradeWarehouse()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

        $stmt = $db->prepare("SELECT money, warehouse_level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $p = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($p) {
            $currentLevel = (int)$p['warehouse_level'];
            $nextLevel = $currentLevel + 1;
            $upgradeCost = $this->getWarehouseUpgradeCost($db, $nextLevel, $currentLevel, 10000.00);

            if ((float)$p['money'] >= $upgradeCost) {
                try {
                    $db->beginTransaction();

                    // Merkezi Kasa Logu ile ücreti düş
                    $this->recordCashTransaction($db, $userId, $upgradeCost, 'out', 'warehouse_upgrade', "Merkez Depo Seviye {$nextLevel} Yükseltme Maliyeti", null);

                    // Depo seviyesini artır
                    $db->prepare("UPDATE users SET warehouse_level = ? WHERE id = ?")->execute([$nextLevel, $userId]);

                    $db->commit();
                } catch (\Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                }
            }
        }

        $this->redirectBack();
    }

    /**
     * Tesis Satış / Tasfiye İşlemi
     */
    public function sell($id = null)
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $buildingId = (int)($id ?? $_POST['building_id'] ?? 0);

        $stmt = $db->prepare("SELECT ub.*, bt.base_cost FROM user_buildings ub JOIN building_types bt ON ub.building_type_id = bt.id WHERE ub.id = ? AND ub.user_id = ?");
        $stmt->execute([$buildingId, $userId]);
        $building = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($building) {
            $refundMoney = round((float)$building['base_cost'] / 2, 2);
            try {
                $db->beginTransaction();

                // Merkezi Kasa Logu ile iadeyi kasaya ekle
                $this->recordCashTransaction($db, $userId, $refundMoney, 'in', 'building_liquidation', "Üretim Tesisi Tasfiye İadesi (#{$buildingId})", null);

                $db->prepare("DELETE FROM building_logs WHERE building_id = ?")->execute([$buildingId]);
                $db->prepare("DELETE FROM user_buildings WHERE id = ?")->execute([$buildingId]);

                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
            }
        }

        $this->redirectBack();
    }

    /**
     * Envanter Alım/Satım İşlemi (Trade)
     */
    public function tradeInventoryItem()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $productId = (int)($_POST['product_id'] ?? 0);
        $actionType = $_POST['action_type'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);

        if ($amount <= 0) {
            $this->sendJsonResponse('error', 'Geçersiz miktar!');
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

                // Merkezi Kasa Logu
                $this->recordCashTransaction($db, $userId, $totalCost, 'out', 'market_purchase', "Pazardan {$amount} adet {$prod['name']} alımı", null);

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

                // Merkezi Kasa Logu
                $this->recordCashTransaction($db, $userId, $totalRevenue, 'in', 'market_sale', "Pazara {$amount} adet {$prod['name']} satışı", null);

                $newQty = round($currentStock - $amount, 2);
                if ($newQty <= 0) {
                    $db->prepare("DELETE FROM user_inventories WHERE user_id = ? AND product_id = ?")->execute([$userId, $productId]);
                } else {
                    $db->prepare("UPDATE user_inventories SET quantity = ? WHERE user_id = ? AND product_id = ?")->execute([$newQty, $userId, $productId]);
                }
            } else {
                throw new \Exception('Geçersiz işlem tipi!');
            }

            $db->commit();
            $this->sendJsonResponse('success', 'İşlem başarıyla gerçekleştirildi.');
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->sendJsonResponse('error', $e->getMessage());
        }
    }

    /**
     * Envanter Detay ve Grafik Verilerini Çekme
     */
    public function getInventoryItemDetail()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['product_id'])) return;

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
        $productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

        $invStmt = $db->prepare("SELECT inv.*, p.name as product_name, p.unit_cost as base_cost, p.type FROM user_inventories inv JOIN products p ON inv.product_id = p.id WHERE inv.user_id = ? AND inv.product_id = ?");
        $invStmt->execute([$userId, $productId]);
        $item = $invStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$item) {
            $this->sendJsonResponse('error', 'Ürün bulunamadı.');
        }

        $logStmt = $db->prepare("SELECT bl.*, b.id as b_id, bt.name as building_name FROM building_logs bl JOIN user_buildings b ON bl.building_id = b.id JOIN building_types bt ON b.building_type_id = bt.id WHERE b.user_id = ? AND bl.product_id = ? ORDER BY bl.id DESC LIMIT 25");
        $logStmt->execute([$userId, $productId]);
        $logs = $logStmt->fetchAll(\PDO::FETCH_ASSOC);

        $costLogStmt = $db->prepare("SELECT bl.* FROM building_logs bl JOIN user_buildings b ON bl.building_id = b.id WHERE b.user_id = ? AND bl.product_id = ? AND (bl.action_type = 'cost' OR bl.action_type = 'repair') ORDER BY bl.id DESC LIMIT 10");
        $costLogStmt->execute([$userId, $productId]);
        $costLogs = $costLogStmt->fetchAll(\PDO::FETCH_ASSOC);

        $energyLogStmt = $db->prepare("SELECT bl.* FROM building_logs bl JOIN user_buildings b ON bl.building_id = b.id WHERE b.user_id = ? AND bl.product_id = ? AND bl.action_type = 'consumption' ORDER BY bl.id DESC LIMIT 10");
        $energyLogStmt->execute([$userId, $productId]);
        $energyLogs = $energyLogStmt->fetchAll(\PDO::FETCH_ASSOC);

        $chLabels = []; $ch1Prod = []; $ch1Cons = [];
        foreach (array_reverse($logs) as $log) {
            $chLabels[] = date('H:i:s', strtotime($log['created_at']));
            $ch1Prod[] = ($log['action_type'] === 'production') ? (float)$log['amount'] : 0;
            $ch1Cons[] = ($log['action_type'] === 'consumption') ? (float)$log['amount'] : 0;
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

        $this->sendJsonResponse('success', 'Detaylar yüklendi.', [
            'item'   => $item,
            'logs'   => $logs,
            'charts' => [
                'prod_cons' => ['labels' => $ch1Labels, 'production' => $ch1Prod, 'consumption' => $ch1Cons],
                'cost'      => ['labels' => $ch2Labels, 'data' => $ch2Data],
                'energy'    => ['labels' => $ch3Labels, 'data' => $ch3Data]
            ]
        ]);
    }

    // =========================================================================
    // ÜRETİM MOTORU VE ORKESTRASYON METOTLARI
    // =========================================================================

    /**
     * Tüm Binalar İçin Arka Plan Üretim Döngüsü (Sayfa açılışında tetiklenir)
     */
    private function processProductionCycles($db, $userId, $now)
    {
        $config = $this->getProductionConfig($db);
        $playerRowData = $this->getPlayerRowData($db, $userId);
        $productSlotLimit = (int)($playerRowData['warehouse_level'] ?? $config['default_warehouse_lvl']) * $config['slot_multiplier'];
        $researches = $this->getPlayerResearches($db, $userId);
        $energyProductId = $this->getEnergyProductId($db);
        $buildings = $this->getUserBuildingsWithDetails($db, $userId);

        if (empty($buildings)) return;

        foreach ($buildings as $building) {
            $bId = $building['id'] ?? $building['building_id'] ?? null;
            if (!$bId) continue;

            $isPaused = (int)($building['is_paused'] ?? 0);
            $health = (float)($building['condition_health'] ?? 100.0);

            if ($isPaused === 1 || $health < $config['min_health_threshold']) {
                if ($health < $config['min_health_threshold']) {
                    $this->resetBuildingPhase($db, $bId);
                    $this->logBuildingWarning($db, $bId, "Üretim Durduruldu: Tesis kondisyonu %50'nin altında.");
                } else {
                    $db->prepare("UPDATE user_buildings SET phase_started_at = NOW() WHERE id = ? AND (TIMESTAMPDIFF(SECOND, phase_started_at, NOW()) > 86400 OR phase_started_at IS NULL)")
                       ->execute([$bId]);
                }
                continue;
            }

            $productProductionSec = (int)($building['product_production_seconds'] ?? $config['production_seconds']);
            $phaseStartedAtStr = $building['phase_started_at'] ?? null;
            $phaseStartedTs = $phaseStartedAtStr ? strtotime($phaseStartedAtStr) : $now;
            if ($phaseStartedTs <= 0) $phaseStartedTs = $now;

            $elapsed = $now - $phaseStartedTs;
            if ($elapsed <= 0) continue;

            if ($elapsed >= ($productProductionSec + 10)) {
                $elapsed = max($elapsed, $productProductionSec);
            }

            $completedCycles = floor(($elapsed + 10) / $productProductionSec);
            if ($completedCycles <= 0) continue;

            // Güvenlik sınırı: Tek seferde en fazla 1 çevrim işlenir (aşırı bakiye/stok çekimini önler)
            $completedCycles = min($completedCycles, 1);

            $this->runBuildingBatchCycle($db, $userId, $building, $config, $researches, $completedCycles, $energyProductId, $productSlotLimit, $productProductionSec);
        }
    }

    /**
     * Tekil Bina İçin AJAX Üretim Döngüsü
     */
    private function processSingleBuildingCycle($db, $userId, $buildingId, $now)
    {
        $config = $this->getProductionConfig($db);
        $playerRowData = $this->getPlayerRowData($db, $userId);
        $productSlotLimit = (int)($playerRowData['warehouse_level'] ?? $config['default_warehouse_lvl']) * $config['slot_multiplier'];
        $researches = $this->getPlayerResearches($db, $userId);
        $energyProductId = $this->getEnergyProductId($db);

        $building = $this->getSingleBuildingWithDetails($db, $userId, $buildingId);
        if (!$building) {
            return ['success' => false, 'message' => 'Tesis bulunamadı.'];
        }

        if ((int)($building['is_paused'] ?? 0) === 1) {
            return ['success' => false, 'message' => 'Tesis duraklatılmış durumda.'];
        }

        $health = (float)($building['condition_health'] ?? 100.0);
        if ($health < $config['min_health_threshold']) {
            $this->resetBuildingPhase($db, $buildingId);
            return ['success' => false, 'message' => "Tesis kondisyonu %50'nin altında. Bakım yapmalısınız."];
        }

        $productProductionSec = (int)($building['product_production_seconds'] ?? $config['production_seconds']);
        $phaseStartedAtStr = $building['phase_started_at'] ?? null;
        $phaseStartedTs = $phaseStartedAtStr ? strtotime($phaseStartedAtStr) : $now;
        if ($phaseStartedTs <= 0) $phaseStartedTs = $now;

        $elapsed = $now - $phaseStartedTs;
        if ($elapsed <= 0) $elapsed = $productProductionSec;

        $completedCycles = max(1, (int)floor($elapsed / $productProductionSec));
        $completedCycles = min($completedCycles, 1);

        try {
            $this->runBuildingBatchCycle($db, $userId, $building, $config, $researches, $completedCycles, $energyProductId, $productSlotLimit, $productProductionSec);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Ortak Parametre Hazırlayıcı ve Transaction Yönlendirici
     */
    private function runBuildingBatchCycle($db, $userId, $building, $config, $researches, $completedCycles, $energyProductId, $productSlotLimit, $productProductionSec)
    {
        $health = (float)($building['condition_health'] ?? 100.0);
        $productionRate = (float)($building['production_rate'] ?? 1.0);
        $energyRequired = (float)($building['energy_consumption'] ?? $config['default_energy_req']);

        if (isset($building['category']) && $building['category'] === 'infrastructure' && (int)$building['produces_product_id'] === 3) {
            $energyRequired = (float)($building['bt_energy_consumption'] ?? 0.0);
        }

        $rawInputs = $this->getBuildingInputs($db, $building['building_type_id']);
        $outputs = $this->getBuildingOutputs($db, $building['building_type_id']);
        $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;

        $prodAmountPerCycle = $this->calculateProductionAmount($db, $building, $researches, $baseOutputAmount, $productionRate, $health);
        $cashCostPerCycle = $this->calculateCashCost($building, $researches, $config);
        $wearRatePerCycle = (float)($building['wear_rate'] ?? $config['default_wear_rate']);

        $this->executeIntegratedBatchTransaction(
            $db, $userId, $building, $config, $researches, $completedCycles,
            $prodAmountPerCycle, $cashCostPerCycle, $wearRatePerCycle,
            $energyProductId, $energyRequired, $productSlotLimit,
            $rawInputs, $productionRate, $productProductionSec
        );
    }

    /**
     * Modüler Bütünleşik Üretim ve Finans Transaction Yöneticisi
     */
    private function executeIntegratedBatchTransaction(
        $db, $userId, $building, $config, $researches, $completedCycles,
        $prodAmountPerCycle, $cashCostPerCycle, $wearRatePerCycle,
        $energyProductId, $energyRequired, $productSlotLimit,
        $rawInputs, $productionRate, $productProductionSec
        ) {
        $bId = $building['id'] ?? $building['building_id'];
        $producesId = $building['produces_product_id'];
        $productName = $building['product_name'] ?? 'Tesis';

        if (empty($producesId) || $producesId <= 0) return;

        try {
            $db->beginTransaction();

            // 1. Sağlık Kontrolü
            $currentHealth = $this->checkBuildingHealth($db, $bId, $productName);
            $targetCycles = max(1, (int)$completedCycles);

            // 2. Hammadde Kontrolü ve Bottleneck Hesabı
            [$targetCycles, $deductibleInputs, $inputMaterialCostTotal] = $this->processInputs(
                $db, $userId, $rawInputs, $productionRate, $targetCycles
            );

            // 3. Depo Kapasite Kontrolü
            $targetCycles = $this->checkWarehouseCapacity(
                $db, $userId, $producesId, $productSlotLimit, $prodAmountPerCycle, $targetCycles, $productName
            );

            // 4. Enerji Kontrolü
            $targetCycles = $this->checkEnergyStock(
                $db, $userId, $energyProductId, $energyRequired, $targetCycles, $productName
            );

            // 5. Nakit Bakiye Kontrolü
            $targetCycles = $this->checkPlayerMoney(
                $db, $userId, $cashCostPerCycle, $targetCycles, $productName
            );

            if ($targetCycles <= 0) {
                $db->commit();
                return;
            }

            // 6. Finansal, Envanter, Sağlık ve Zaman Güncellemelerini Uygula
            $this->applyProductionResults(
                $db, $userId, $building, $bId, $producesId, $targetCycles,
                $prodAmountPerCycle, $cashCostPerCycle, $wearRatePerCycle,
                $energyProductId, $energyRequired, $deductibleInputs,
                $inputMaterialCostTotal, $currentHealth, $productProductionSec
            );

            $db->commit();
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // ALT KONTROL VE İŞLEM MODÜLLERİ (BATCH HELPERS)
    // =========================================================================

    private function checkBuildingHealth($db, $bId, $productName)
    {
        $stmt = $db->prepare("SELECT condition_health FROM user_buildings WHERE id = ? FOR UPDATE");
        $stmt->execute([$bId]);
        $health = (float)$stmt->fetchColumn();

        if ($health < 50.0) {
            throw new \Exception("Üretim Durdu ({$productName}): Tesis kondisyonu %50'nin altında. Bakım yapmalısınız.");
        }
        return $health;
    }

    private function processInputs($db, $userId, $rawInputs, $productionRate, $targetCycles)
    {
        $deductibleInputs = [];
        $totalMaterialCost = 0;

        if (empty($rawInputs)) return [$targetCycles, $deductibleInputs, $totalMaterialCost];

        foreach ($rawInputs as $inp) {
            $scaledReq = round($inp['required_amount'] * $productionRate, 2);
            if ($scaledReq <= 0) continue;

            $chk = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
            $chk->execute([$userId, $inp['product_id']]);
            $invRow = $chk->fetch(\PDO::FETCH_ASSOC);
            $stock = (float)($invRow['quantity'] ?? 0);

            if ($stock < $scaledReq) {
                $pNameStmt = $db->prepare("SELECT name FROM products WHERE id = ?");
                $pNameStmt->execute([$inp['product_id']]);
                $missingName = $pNameStmt->fetchColumn() ?? "Hammadde";
                throw new \Exception("Hammadde Bekleniyor: {$missingName} (Gereken: {$scaledReq}, Mevcut: {$stock})");
            }

            $maxCycles = floor($stock / $scaledReq);
            $targetCycles = min($targetCycles, max(1, (int)$maxCycles));

            $mCost = (float)($invRow['unit_cost'] ?? 0);
            if ($mCost <= 0) {
                $pCostStmt = $db->prepare("SELECT unit_cost FROM products WHERE id = ?");
                $pCostStmt->execute([$inp['product_id']]);
                $mCost = (float)$pCostStmt->fetchColumn();
            }

            $totalQty = $scaledReq * $targetCycles;
            $totalMaterialCost += round($mCost * $totalQty, 2);

            $deductibleInputs[] = [
                'product_id'    => $inp['product_id'],
                'req_per_cycle' => $scaledReq,
                'total_qty'     => $totalQty
            ];
        }

        return [$targetCycles, $deductibleInputs, $totalMaterialCost];
    }

    private function checkWarehouseCapacity($db, $userId, $producesId, $slotLimit, $prodPerCycle, $targetCycles, $productName)
    {
        $chk = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
        $chk->execute([$userId, $producesId]);
        $currentStock = (float)$chk->fetchColumn();

        $availableSpace = max(0, $slotLimit - $currentStock);
        $maxCycles = $prodPerCycle > 0 ? floor($availableSpace / $prodPerCycle) : 0;

        if ($maxCycles <= 0) {
            throw new \Exception("Üretim Durdu ({$productName}): Depo kapasitesi dolu! (Mevcut: {$currentStock}, Limit: {$slotLimit})");
        }
        return min($targetCycles, (int)$maxCycles);
    }

    private function checkEnergyStock($db, $userId, $energyId, $energyReq, $targetCycles, $productName)
    {
        if ($energyId <= 0 || $energyReq <= 0) return $targetCycles;

        $chk = $db->prepare("SELECT quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
        $chk->execute([$userId, $energyId]);
        $enStock = (float)$chk->fetchColumn();

        $maxCycles = floor($enStock / $energyReq);
        if ($maxCycles <= 0) {
            throw new \Exception("Üretim Durdu ({$productName}): Yetersiz enerji! Gerekli: {$energyReq}, Depodaki: {$enStock}");
        }
        return min($targetCycles, (int)$maxCycles);
    }

    private function checkPlayerMoney($db, $userId, $cashCostPerCycle, $targetCycles, $productName)
    {
        if ($cashCostPerCycle <= 0) return $targetCycles;

        $money = (float)$db->query("SELECT money FROM users WHERE id = {$userId} FOR UPDATE")->fetchColumn();
        $maxCycles = floor($money / $cashCostPerCycle);

        if ($maxCycles <= 0) {
            throw new \Exception("Üretim Durdu ({$productName}): Yetersiz nakit bakiye!");
        }
        return min($targetCycles, (int)$maxCycles);
    }

    private function applyProductionResults(
        $db, $userId, $building, $bId, $producesId, $targetCycles,
        $prodAmountPerCycle, $cashCostPerCycle, $wearRatePerCycle,
        $energyId, $energyReq, $deductibleInputs, $materialCostTotal,
        $currentHealth, $productionSec
    ) {
        $productName = $building['product_name'] ?? 'Tesis';
        $unitType = $building['unit_type'] ?? 'Adet';

        $totalProd = round($targetCycles * $prodAmountPerCycle, 2);
        $totalCash = round($targetCycles * $cashCostPerCycle, 2);
        $totalEnergy = round($targetCycles * $energyReq, 2);
        $totalWear = round($targetCycles * $wearRatePerCycle, 2);

        // 1. Hammaddeleri Depodan Düş
        if (!empty($deductibleInputs)) {
            foreach ($deductibleInputs as $inp) {
                // Eğer deductibleInputs yapınız processInputs'tan gelen formattaysa:
                // $inp['product_id'], $inp['total_qty'] vb. içerir.
                $pId = $inp['product_id'];
                $qty = $inp['total_qty'] ?? ($inp['req_per_cycle'] * $targetCycles);

                // Envanterden düş
                $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
                   ->execute([$qty, $userId, $pId]);

                // Ürün adını log için çek
                $pNameStmt = $db->prepare("SELECT name FROM products WHERE id = ?");
                $pNameStmt->execute([$pId]);
                $inputProdName = $pNameStmt->fetchColumn() ?? 'Hammadde';

                // Hammadde Tüketim Logu ('consumption')
                $inputLogDesc = "Üretim için {$qty} adet {$inputProdName} harcandı.";
                $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
                   ->execute([$bId, $pId, $qty, $inputLogDesc]);
            }
        }

        // 2. Enerji Düş ve Bina Loguna İşle
        if ($energyId > 0 && $totalEnergy > 0) {
            $db->prepare("UPDATE user_inventories SET quantity = quantity - ? WHERE user_id = ? AND product_id = ?")
               ->execute([$totalEnergy, $userId, $energyId]);

            $energyLogDesc = "{$targetCycles} parti {$productName} için {$totalEnergy} kW/h enerji harcandı.";
            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'consumption', ?, ?, ?, NOW())")
               ->execute([$bId, $energyId, $totalEnergy, $energyLogDesc]);
        }

        // 3. Nakit Maliyeti Düş, Bina Loguna ve MERKEZİ ŞİRKET KASA DEFTERİNE Kaydet
        if ($totalCash > 0) {
            $costLog = "{$targetCycles} parti {$productName} ({$totalProd} {$unitType}) için {$totalCash}₺ üretim maliyeti düşüldü.";
            
            $this->recordCashTransaction($db, $userId, $totalCash, 'out', 'production_expense', $costLog, null);

            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'cost', ?, ?, ?, NOW())")
               ->execute([$bId, $producesId, $totalCash, $costLog]);
        }

        // 4. Birim Maliyet Hesapla ve Ürünü Depoya Ekle
        $settingsStmt = $db->query("SELECT setting_value FROM game_settings WHERE setting_key = 'amortisman_rate'");
        $amortismanRate = $settingsStmt ? (float)($settingsStmt->fetchColumn() ?? 2.0) : 2.0;

        $calculatedWearCost = round($wearRatePerCycle * $amortismanRate, 2) * $targetCycles;
        $grandTotalCost = round($materialCostTotal + $totalCash + $calculatedWearCost, 2);
        $calculatedUnitCost = ($totalProd > 0) ? round($grandTotalCost / $totalProd, 2) : 0.00;

        $invChk = $db->prepare("SELECT quantity, unit_cost FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
        $invChk->execute([$userId, $producesId]);
        $inv = $invChk->fetch(\PDO::FETCH_ASSOC);

        if ($inv) {
            $oldQty = (float)$inv['quantity'];
            $oldCost = (float)$inv['unit_cost'];
            $newQty = round($oldQty + $totalProd, 2);
            $blendedUnitCost = ($newQty > 0) ? (($oldQty * $oldCost) + ($totalProd * $calculatedUnitCost)) / $newQty : $calculatedUnitCost;

            $db->prepare("UPDATE user_inventories SET quantity = ?, unit_cost = ? WHERE user_id = ? AND product_id = ?")
               ->execute([$newQty, round($blendedUnitCost, 2), $userId, $producesId]);
        } else {
            $db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
               ->execute([$userId, $producesId, $totalProd, $calculatedUnitCost]);
        }

        // 5. Başarı Logu, Sağlık Güncellemesi ve Zaman Damgasını İlerletme
        $logDesc = "Üretim tamamlandı: {$targetCycles} parti {$productName} ({$totalProd} {$unitType}) depoya eklendi.";
        $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'production', ?, ?, ?, NOW())")
           ->execute([$bId, $producesId, $totalProd, $logDesc]);

        $newHealth = max(0.0, round($currentHealth - $totalWear, 2));
        $advSec = $targetCycles * $productionSec;

        $db->prepare("UPDATE user_buildings SET condition_health = ?, phase_started_at = DATE_ADD(phase_started_at, INTERVAL ? SECOND), buffer_inventory = NULL WHERE id = ?")
           ->execute([$newHealth, $advSec, $bId]);
    }

    // =========================================================================
    // MERKEZİ KASA, YANIT VE YARDIMCI METOTLAR
    // =========================================================================

    /**
     * Merkezi Kasa ve Şirket Defteri Yönetim Fonksiyonu[cite: 16]
     */
    protected function recordCashTransaction($db, $userId, $amount, $type, $category, $description, $storeId = null)
    {
        $amount = floatval($amount);
        if ($amount <= 0) return;

        if ($type === 'out') {
            $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$amount, $userId]);
        } elseif ($type === 'in') {
            $db->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$amount, $userId]);
        }

        $logStmt = $db->prepare("
            INSERT INTO company_cash_logs (user_id, store_id, transaction_type, category, amount, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$userId, $storeId, $type, $category, $amount, $description]);
    }

    /**
     * Standart JSON Yanıt Gönderici
     */
    protected function sendJsonResponse($status, $message, $extra = [])
    {
        ini_set('display_errors', 0);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'status'  => $status,
            'message' => $message
        ], $extra));
        exit;
    }

    protected function redirectBack()
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        } else {
            header('Location: /app/playerbuilding');
        }
        exit;
    }

    private function getProductionConfig($db)
    {
        $settings = $this->loadGameSettings($db);
        return [
            'production_seconds'    => (int)($settings['production_seconds'] ?? 600),
            'default_warehouse_lvl' => (int)($settings['default_warehouse_lvl'] ?? 1),
            'slot_multiplier'       => (float)($settings['slot_multiplier'] ?? 500.00),
            'min_health_threshold'  => 50.0,
            'default_energy_req'    => 0,
            'default_labor_req'     => 10,
            'default_labor_cost'    => 50.0,
            'default_transport'     => 20.0,
            'default_tax'           => 10.0,
            'default_wear_rate'     => 2.0,
        ];
    }

    private function loadGameSettings($db)
    {
        $settings = [];
        $stmt = $db->query("SELECT setting_key, setting_value FROM game_settings");
        if ($stmt) {
            foreach ($stmt as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return $settings;
    }

    private function getCurrentDatabaseTime($db)
    {
        $stmt = $db->query("SELECT UNIX_TIMESTAMP(NOW()) as db_now");
        $now = (int)$stmt->fetchColumn();
        return $now ?: time();
    }

    private function getPlayerProfile($db, $userId, $defaultWarehouseLvl)
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!isset($player['warehouse_level'])) {
            $player['warehouse_level'] = $defaultWarehouseLvl;
        }
        return $player;
    }

    private function getPlayerRowData($db, $userId)
    {
        $stmt = $db->prepare("SELECT warehouse_level, money FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $player ?: ['warehouse_level' => 1, 'money' => 0];
    }

    private function getWarehouseUpgradeCost($db, $nextLevel, $warehouseLevel, $defaultCost)
    {
        $stmt = $db->prepare("SELECT * FROM warehouse_level_matrix WHERE level = ?");
        $stmt->execute([$nextLevel]);
        $meta = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $meta ? (float)$meta['upgrade_cost'] : ($warehouseLevel * $defaultCost);
    }

    private function getPlayerActiveCardMultipliers($db, $userId)
    {
        $db->prepare("UPDATE user_cards SET is_active = 0 WHERE user_id = ? AND expire_date IS NOT NULL AND expire_date < NOW()")
           ->execute([$userId]);

        $stmt = $db->prepare("SELECT * FROM user_cards WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $activeCards = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $multipliers = [
            'fOutputAmount'      => 1.0,
            'fInputConsumption'  => 1.0,
            'fEnergyConsumption' => 1.0,
            'fLaborExpense'      => 1.0,
            'fTransportCost'     => 1.0,
            'fTaxRate'           => 1.0
        ];

        foreach ($activeCards as $card) {
            $lvl = max(1, (int)$card['level']);
            if ((float)$card['effect_output'] > 0) {
                $multipliers['fOutputAmount'] *= (1.0 + (($card['effect_output'] - 1.0) * $lvl));
            }
            if ((float)$card['effect_input_cons'] > 0) {
                $multipliers['fInputConsumption'] *= (1.0 - ((1.0 - $card['effect_input_cons']) * $lvl));
            }
            if ((float)$card['effect_energy'] > 0) {
                $multipliers['fEnergyConsumption'] *= (1.0 - ((1.0 - $card['effect_energy']) * $lvl));
            }
            if ((float)$card['effect_labor'] > 0) {
                $multipliers['fLaborExpense'] *= (1.0 - ((1.0 - $card['effect_labor']) * $lvl));
            }
            if ((float)$card['effect_transport'] > 0) {
                $multipliers['fTransportCost'] *= (1.0 - ((1.0 - $card['effect_transport']) * $lvl));
            }
            if ((float)$card['effect_tax'] > 0) {
                $multipliers['fTaxRate'] *= (1.0 - ((1.0 - $card['effect_tax']) * $lvl));
            }
        }
        return $multipliers;
    }

    private function calculateProductionAmountWithDetails($db, $b, $researches, $health)
    {
        $buildingTypeKey = $b['building_type_id'];
        $userId = $b['user_id'] ?? ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 1);
        $outputs = $this->getBuildingOutputs($db, $buildingTypeKey);
        $baseOutputAmount = !empty($outputs) ? (float)$outputs[0]['amount'] : 1.0;
        $productionRate = (float)($b['production_rate'] ?? 1.0);

        $category = strtolower(trim($b['category'] ?? ''));
        $soilMultiplier = 1.0;
        if (str_contains($category, 'agri') || str_contains($category, 'tarım') || str_contains($category, 'bahçe') || str_contains($category, 'farm')) {
            $soilMultiplier = (float)($b['agri_multiplier'] ?? 1.0);
        } elseif (str_contains($category, 'ind') || str_contains($category, 'sanayi') || str_contains($category, 'factory')) {
            $soilMultiplier = (float)($b['ind_multiplier'] ?? 1.0);
        } elseif (str_contains($category, 'mine') || str_contains($category, 'maden')) {
            $soilMultiplier = (float)($b['mine_multiplier'] ?? 1.0);
        }
        if ($soilMultiplier <= 0) $soilMultiplier = 1.0;

        $healthFactor = max(0, $health / 100.0);
        $cardMultipliers = $this->getPlayerActiveCardMultipliers($db, $userId);
        $fOutputAmount = $cardMultipliers['fOutputAmount'];

        $productionAmount = round($baseOutputAmount * $productionRate * $soilMultiplier * $healthFactor * $fOutputAmount, 2);

        return [$soilMultiplier, $healthFactor, $productionAmount, $baseOutputAmount, $productionRate];
    }

    private function calculateProductionAmount($db, $building, $researches, $baseOutputAmount, $productionRate, $health)
    {
        [, , $productionAmount] = $this->calculateProductionAmountWithDetails($db, $building, $researches, $health);
        return $productionAmount;
    }

    private function processBuildingInputs($db, $rawInputs, $productionRate, $inventoryCostMap, $researches)
    {
        $inputs = [];
        $inputMaterialCost = 0;
        $fInputConsumption = 1.0;
        $fInputOptimization = 1.0;

        foreach ($rawInputs as $inp) {
            $scaledInputAmount = round(($inp['required_amount'] ?? 1.0) * $productionRate * $fInputConsumption * $fInputOptimization, 2);
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
                'product_id'    => $pId,
                'product_name'  => $inp['product_name'] ?? 'Hammadde',
                'product_image' => $inp['product_image'] ?? '',
                'unit_type'     => $inp['unit_type'] ?? 'Adet',
                'amount'        => $scaledInputAmount,
                'unit_cost'     => (float)$mCost,
                'total_cost'    => (float)$rowCost
            ];
        }
        return [$inputs, $inputMaterialCost];
    }

    private function calculateOperationalExpenses($b, $researches, $amortismanBedeli, $inputMaterialCost, $productionAmount)
    {
        $fEnergyConsumption = 1.0;
        $fLaborExpense = 1.0;
        $fTransportCost = 1.0;
        $fTaxRate = 1.0;
        $fWearRate = 1.0;

        $laborReq = (float)($b['labor_required'] ?? 10);
        $laborMult = (float)($b['labor_cost_multiplier'] ?? 1.0);
        $energyRequired = (float)($b['energy_consumption'] ?? 50) * $fEnergyConsumption;
        $baseLaborCost = (float)($b['base_labor_cost'] ?? 50.0);
        $baseTransport = (float)($b['base_transport_cost'] ?? 20.0);
        $baseTax = (float)($b['base_tax_rate'] ?? 10.0);

        $rLog = isset($researches['log']) ? (int)$researches['log'] : 1;
        $rFin = isset($researches['fin']) ? (int)$researches['fin'] : 1;

        $logDiscount = max(0.5, 1.0 - (($rLog - 1) * 0.05));
        $finDiscount = max(0.6, 1.0 - (($rFin - 1) * 0.04));

        $totalLaborExpense = round($laborReq * $baseLaborCost * $laborMult * $fLaborExpense, 2);
        $nakliyeCost = round($baseTransport * $logDiscount * $fTransportCost, 2);
        $vergiCost = round($baseTax * $finDiscount * $fTaxRate, 2);

        $wearRateVal = (float)($b['wear_rate'] ?? 2.0);
        $calculatedWearCost = round($wearRateVal * $fWearRate * $amortismanBedeli, 2);

        $totalProductionCost = round($inputMaterialCost + $totalLaborExpense + $nakliyeCost + $vergiCost + $calculatedWearCost, 2);
        $cashDeductionCost = round($totalLaborExpense + $nakliyeCost + $vergiCost, 2);
        $calculatedUnitCost = ($productionAmount > 0) ? round($totalProductionCost / $productionAmount, 2) : 0.00;

        return [
            'energy_consumption'   => $energyRequired,
            'labor_required'       => (int)$laborReq,
            'wear_rate'            => $wearRateVal,
            'amortisman_bedeli'    => $amortismanBedeli,
            'calculated_wear_cost' => $calculatedWearCost,
            'total_labor_expense'  => $totalLaborExpense,
            'base_transport_cost'  => $nakliyeCost,
            'base_tax_rate'        => $vergiCost,
            'input_material_cost'  => $inputMaterialCost,
            'production_cost'      => $totalProductionCost,
            'cash_deduction_cost'  => $cashDeductionCost,
            'unit_cost'            => $calculatedUnitCost
        ];
    }

    private function calculateCashCost($building, $researches, $config)
    {
        $laborReq = (float)($building['labor_required'] ?? $config['default_labor_req']);
        $baseLaborCost = (float)($building['base_labor_cost'] ?? $config['default_labor_cost']);
        $laborMult = (float)($building['labor_cost_multiplier'] ?? 1.0);
        $baseTransport = (float)($building['base_transport_cost'] ?? $config['default_transport']);
        $baseTax = (float)($building['base_tax_rate'] ?? $config['default_tax']);

        $rLog = isset($researches['log']) ? (int)$researches['log'] : 1;
        $rFin = isset($researches['fin']) ? (int)$researches['fin'] : 1;

        $logDiscount = max(0.5, 1.0 - (($rLog - 1) * 0.05));
        $finDiscount = max(0.6, 1.0 - (($rFin - 1) * 0.04));

        return round(($laborReq * $baseLaborCost * $laborMult) + ($baseTransport * $logDiscount) + ($baseTax * $finDiscount), 2);
    }

    private function evaluateBuildingStatus($db, $b, $inventoryMap, $currentEnergyStock, $inputs, $productionSec, $now)
    {
        $health = (float)($b['condition_health'] ?? 100.0);
        $phaseStartedTs = (int)($b['phase_started_ts'] ?? 0);
        if ($phaseStartedTs <= 0) $phaseStartedTs = $now;
        $pausedTs = (int)($b['paused_ts'] ?? 0);

        $dbPaused = (int)($b['is_paused'] ?? 0) === 1;
        if ($health < 50.0 && !$dbPaused) {
            $isPaused = true;
            $bId = $b['building_id'] ?? $b['id'] ?? 0;
            if ($bId > 0) {
                $db->prepare("UPDATE user_buildings SET is_paused = 1 WHERE id = ?")->execute([$bId]);
            }
        } else {
            $isPaused = $dbPaused || ($health < 50.0);
        }

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
        } elseif ($isPaused && $health >= 50.0) {
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
            'is_paused'              => $isPaused,
            'is_currently_producing' => $isCurrentlyProducing,
            'has_enough_input'       => $hasEnoughInput,
            'warning_message'        => $warningMessage,
            'rem_sec'                => $remSec,
            'progress'               => $progress,
            'bar_text'               => $barText,
            'bar_color'              => $barColor
        ];
    }

    private function calculateRepairCost($b, $level, $health)
    {
        $baseBuildingCost = (float)($b['base_cost'] ?? 1000);
        $repairCost = round(((float)$baseBuildingCost * $level) * ((100.0 - $health) / 100.0), 2);
        return ($repairCost < 100 && $health < 100) ? 100 : $repairCost;
    }

    private function attachBuildingLogs($db, $b)
    {
        $logStmt = $db->prepare("SELECT bl.*, p.name as product_name FROM building_logs bl LEFT JOIN products p ON bl.product_id = p.id WHERE bl.building_id = ? ORDER BY bl.id DESC LIMIT 50");
        $logStmt->execute([$b['building_id']]);
        $allLogs = $logStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $b['input_logs']  = array_filter($allLogs, fn($l) => $l['action_type'] === 'consumption');
        $b['cost_logs']   = array_filter($allLogs, fn($l) => in_array($l['action_type'], ['cost', 'repair']));
        $b['output_logs'] = array_filter($allLogs, fn($l) => $l['action_type'] === 'production');
        $b['wear_logs']   = array_filter($allLogs, fn($l) => $l['action_type'] === 'wear');

        $b['totals'] = [
            'consumed' => array_sum(array_column($b['input_logs'], 'amount')),
            'cost'     => array_sum(array_column($b['cost_logs'], 'amount')),
            'produced' => array_sum(array_column($b['output_logs'], 'amount')),
            'wear'     => count($b['wear_logs'])
        ];
        return $b;
    }

    private function resetBuildingPhase($db, $bId)
    {
        $db->prepare("UPDATE user_buildings SET phase_started_at = NOW(), buffer_inventory = NULL WHERE id = ?")->execute([$bId]);
    }

    private function logBuildingWarning($db, $buildingId, $message)
    {
        $checkStmt = $db->prepare("SELECT description FROM building_logs WHERE building_id = ? ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$buildingId]);
        if ($checkStmt->fetchColumn() !== $message) {
            $db->prepare("INSERT INTO building_logs (building_id, action_type, product_id, amount, description, created_at) VALUES (?, 'warning', NULL, 0, ?, NOW())")
               ->execute([$buildingId, $message]);
        }
    }

    private function getEnergyProductId($db)
    {
        $stmt = $db->prepare("SELECT id FROM products WHERE name LIKE '%Enerji%' OR name LIKE '%Elektrik%' LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 0;
    }

    private function getBuildingTypes($db)
    {
        return $db->query("SELECT bt.*, p.name as product_name, p.image as product_image, p.unit_cost as product_base_cost FROM building_types bt LEFT JOIN products p ON bt.produces_product_id = p.id ORDER BY bt.name ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getProvinces($db)
    {
        return $db->query("SELECT * FROM provinces ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getRawBuildings($db, $userId, $activeCategory)
    {
        $query = "SELECT pb.*, pb.id as building_id,
            UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
            UNIX_TIMESTAMP(pb.paused_at) as paused_ts,
            UNIX_TIMESTAMP(pb.created_at) as created_ts,
            bt.id as building_type_id, bt.name as building_name,
            bt.category, bt.complexity_type, bt.base_cost,
            bt.unit_type, bt.image as building_image,
            bt.produces_product_id, p.name as product_name,
            p.unit_type as product_unit_type, p.image as product_image,
            p.unit_cost as product_base_cost, p.production_seconds as product_production_seconds,
            prov.name as province_name, prov.population, prov.labor_cost_multiplier,
            prov.agri_multiplier, prov.ind_multiplier, prov.mine_multiplier,
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

    private function getUserBuildingsWithDetails($db, $userId)
    {
        $stmt = $db->prepare("SELECT pb.*, pb.id as building_id,
            UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
            bt.produces_product_id, bt.category, bt.id as building_type_id,
            bt.energy_consumption as bt_energy_consumption,
            bt.water_consumption as bt_water_consumption,
            p.name as product_name, p.unit_type as unit_type, p.unit_cost as product_base_cost,
            p.production_seconds as product_production_seconds,
            prov.mine_multiplier, prov.labor_cost_multiplier, prov.agri_multiplier, prov.ind_multiplier,
            mlb.production_rate, mlb.wear_rate, mlb.labor_required, mlb.base_labor_cost,
            mlb.base_transport_cost, mlb.base_tax_rate,
            CASE WHEN bt.category = 'infrastructure' THEN COALESCE(bt.energy_consumption, 0) ELSE COALESCE(mlb.energy_consumption, 0) END as energy_consumption,
            CASE WHEN bt.category = 'infrastructure' THEN COALESCE(bt.water_consumption, 0) ELSE COALESCE(mlb.water_consumption, 0) END as water_consumption
            FROM user_buildings pb
            JOIN building_types bt ON pb.building_type_id = bt.id
            LEFT JOIN products p ON bt.produces_product_id = p.id
            JOIN provinces prov ON pb.province_id = prov.id
            LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
            WHERE pb.user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getSingleBuildingWithDetails($db, $userId, $buildingId)
    {
        $stmt = $db->prepare("SELECT pb.*, pb.id as building_id,
            UNIX_TIMESTAMP(pb.phase_started_at) as phase_started_ts,
            bt.produces_product_id, bt.category, bt.id as building_type_id,
            bt.energy_consumption as bt_energy_consumption,
            p.name as product_name, p.unit_type as unit_type, p.unit_cost as product_base_cost,
            p.production_seconds as product_production_seconds,
            prov.mine_multiplier, prov.labor_cost_multiplier, prov.agri_multiplier, prov.ind_multiplier,
            mlb.production_rate, mlb.energy_consumption, mlb.wear_rate, mlb.labor_required,
            mlb.base_labor_cost, mlb.base_transport_cost, mlb.base_tax_rate
            FROM user_buildings pb
            JOIN building_types bt ON pb.building_type_id = bt.id
            LEFT JOIN products p ON bt.produces_product_id = p.id
            JOIN provinces prov ON pb.province_id = prov.id
            LEFT JOIN matrix_level_building mlb ON mlb.building_type_id = pb.building_type_id AND mlb.level = pb.level
            WHERE pb.id = ? AND pb.user_id = ?");
        $stmt->execute([$buildingId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function getInventoryMaps($db, $userId)
    {
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

    private function getEnergyStock($db, $inventoryMap)
    {
        $energyProductId = $this->getEnergyProductId($db);
        return $inventoryMap[$energyProductId] ?? 0.0;
    }

    private function getFullInventory($db, $userId)
    {
        $invStmt = $db->prepare("SELECT inv.*, p.name as product_name, p.type, p.unit_type, p.image FROM user_inventories inv JOIN products p ON inv.product_id = p.id WHERE inv.user_id = ?");
        $invStmt->execute([$userId]);
        return $invStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getBuildingInputs($db, $buildingTypeId)
    {
        $stmt = $db->prepare("SELECT bi.*, p.name as product_name, p.unit_type, p.image as product_image FROM building_inputs bi JOIN products p ON bi.product_id = p.id WHERE bi.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        $inputs = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $inputs[] = [
                'product_id'      => $row['product_id'],
                'product_name'    => $row['product_name'] ?? 'Ürün',
                'unit_type'       => $row['unit_type'] ?? 'Birim',
                'required_amount' => (float)$row['amount'],
                'product_image'   => $row['product_image'] ?? ''
            ];
        }
        return $inputs;
    }

    private function getBuildingOutputs($db, $buildingTypeId)
    {
        $stmt = $db->prepare("SELECT bo.*, p.name as product_name, p.unit_type, p.image as product_image FROM building_outputs bo JOIN products p ON bo.product_id = p.id WHERE bo.building_type_id = ?");
        $stmt->execute([$buildingTypeId]);
        $outputs = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $outputs[] = [
                'product_id'    => $row['product_id'],
                'product_name'  => $row['product_name'] ?? 'Ürün',
                'unit_type'     => $row['unit_type'] ?? 'Birim',
                'amount'        => (float)$row['amount'],
                'product_image' => $row['product_image'] ?? ''
            ];
        }
        return $outputs;
    }

    private function getPlayerResearches($db, $userId)
    {
        $stmt = $db->prepare("SELECT * FROM user_researches WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return [
            'soil'       => (int)($row['soil_level'] ?? 1),
            'health'     => (int)($row['health_level'] ?? 1),
            'tech'       => (int)($row['tech_level'] ?? 1),
            'bio'        => (int)($row['bio_level'] ?? 1),
            'output'     => (int)($row['output_level'] ?? 1),
            'input_cons' => (int)($row['input_cons_level'] ?? 1),
            'input_opt'  => (int)($row['input_opt_level'] ?? 1),
            'energy'     => (int)($row['energy_level'] ?? 1),
            'labor'      => (int)($row['labor_level'] ?? 1),
            'log'        => (int)($row['log_level'] ?? 1),
            'fin'        => (int)($row['fin_level'] ?? 1),
            'wear'       => (int)($row['wear_level'] ?? 1),
        ];
    }
}
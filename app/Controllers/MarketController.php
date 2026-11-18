<?php

namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
class MarketController extends Controller
{
    const EXPENSE_SALE_THRESHOLD = 120;
    const DEFAULT_STORE_RENT = 3000;
    const DEFAULT_STORE_SALARY = 8000;

    protected function getCurrentDatabaseTime($db)
    {
        try {
            $stmt = $db->query("SELECT UNIX_TIMESTAMP()");
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return time();
        }
    }

    protected function getAuthenticatedUserId()
    {
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
        if (!$userId) {
            // Güvenli varsayılan veya yönlendirme
            return 1; 
        }
        return (int)$userId;
    }

    public function index()
    {
        $db = Database::getInstance();
        $userId = $this->getAuthenticatedUserId();

        if (class_exists('\\App\\Services\\RetailSalesEngine')) {
            \App\Services\RetailSalesEngine::processSales();
        }

        $now = $this->getCurrentDatabaseTime($db);

        // Oyuncu Bilgisi
        $playerStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $playerStmt->execute([$userId]);
        $player = $playerStmt->fetch(\PDO::FETCH_ASSOC);

        // Aktif Kartlar
        $cardsStmt = $db->prepare("SELECT * FROM user_cards WHERE user_id = ? AND is_active = 1");
        $cardsStmt->execute([$userId]);
        $activeCards = $cardsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Merkez Depo Envanteri (Filtreli)
        $invStmt = $db->prepare("
            SELECT ui.*, p.name as product_name, p.image, p.unit_type, p.type
            FROM user_inventories ui
            JOIN products p ON ui.product_id = p.id
            WHERE ui.user_id = ? AND p.type NOT IN ('utility', 'energy', 'water')
        ");
        $invStmt->execute([$userId]);
        $inventory = $invStmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalStockQty = array_sum(array_column($inventory, 'quantity'));
        $warehouseSummary = [
            'level' => $player['warehouse_level'] ?? 1,
            'slot_limit' => $player['warehouse_capacity'] ?? 500.00,
            'total_items' => count($inventory),
            'total_quantity' => $totalStockQty
        ];

        // Mağaza Tipleri ve İller (View için şart)
        $shopTypes = $db->query("SELECT * FROM shop_types ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $provinces = $db->query("SELECT * FROM provinces ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $products = $db->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);

        // Mağazalar
        $storesStmt = $db->prepare("
            SELECT rs.*, prov.name as province_name
            FROM retail_stores rs
            JOIN provinces prov ON rs.province_id = prov.id
            WHERE rs.user_id = ?
        ");
        $storesStmt->execute([$userId]);
        $stores = $storesStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($stores as &$store) {
            $storeId = $store['id'];
            $storeLevel = (int)($store['level'] ?? 1);
            $shopTypeId = $store['shop_type_id'] ?? 'market';

            $shopTypeInfo = null;
            foreach ($shopTypes as $st) {
                if ($st['id'] === $shopTypeId) {
                    $shopTypeInfo = $st;
                    break;
                }
            }

            $isBakkal = ($shopTypeId === 'bakkal' || strpos(strtolower($shopTypeInfo['name'] ?? ''), 'bakkal') !== false);
            if (!$shopTypeInfo) {
                $shopTypeInfo = [
                    'max_slots' => ($isBakkal ? 1 : 4),
                    'base_rent' => self::DEFAULT_STORE_RENT,
                    'base_salary' => self::DEFAULT_STORE_SALARY,
                    'storage_capacity' => ($isBakkal ? 500 : 2000)
                ];
            }

            $store['shop_info'] = $shopTypeInfo;

            $baseMaxSlots = (int)($shopTypeInfo['max_slots'] ?? ($isBakkal ? 1 : 4));
            $store['max_slots'] = $baseMaxSlots + (($storeLevel - 1) * 1);

            $baseCapacity = (float)($shopTypeInfo['storage_capacity'] ?? ($isBakkal ? 500 : 2000));
            $store['storage_capacity'] = max((float)($store['storage_capacity'] ?? 0), $baseCapacity);

            // Slotlar (Volume sütunu yoksa hata vermemesi için güvenli sorgu)
            try {
                $slotsStmt = $db->prepare("
                    SELECT rss.*, p.name as product_name, p.unit_type, p.sales_interval_seconds, p.volume,
                    UNIX_TIMESTAMP(rss.updated_at) as last_updated_ts
                    FROM retail_store_slots rss
                    LEFT JOIN products p ON rss.product_id = p.id
                    WHERE rss.store_id = ?
                    ORDER BY rss.slot_index ASC
                ");
                $slotsStmt->execute([$storeId]);
                $store['slots'] = $slotsStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Eğer products.volume kolonu henüz eklenmediyse alternatif sorgu
                $slotsStmt = $db->prepare("
                    SELECT rss.*, p.name as product_name, p.unit_type, p.sales_interval_seconds, 
                    1.00 as volume,
                    UNIX_TIMESTAMP(rss.updated_at) as last_updated_ts
                    FROM retail_store_slots rss
                    LEFT JOIN products p ON rss.product_id = p.id
                    WHERE rss.store_id = ?
                    ORDER BY rss.slot_index ASC
                ");
                $slotsStmt->execute([$storeId]);
                $store['slots'] = $slotsStmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            // Stok hacim hesaplama
            $volSumStmt = $db->prepare("
                SELECT COALESCE(SUM(rss.amount), 0) 
                FROM retail_store_slots rss
                WHERE rss.store_id = ?
            ");
            $volSumStmt->execute([$storeId]);
            $actualStockSum = (float)$volSumStmt->fetchColumn();
            $store['current_stock_volume'] = $actualStockSum;

            // Bilanço Özetleri
            $finStmt = $db->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN sale_type = 'retail_sale' THEN total_revenue ELSE 0 END), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN sale_type = 'retail_sale' THEN net_profit ELSE 0 END), 0) as total_net_profit,
                    COALESCE(SUM(CASE WHEN sale_type = 'retail_sale' THEN transport_cost ELSE 0 END), 0) as total_transport_cost,
                    COALESCE(SUM(CASE WHEN sale_type = 'retail_sale' THEN tax_amount ELSE 0 END), 0) as total_tax_amount,
                    COALESCE(SUM(CASE WHEN sale_type = 'retail_sale' THEN (unit_base_cost * amount) ELSE 0 END), 0) as total_product_cost,
                    COALESCE(SUM(CASE WHEN sale_type = 'store_expense' THEN ABS(total_revenue) ELSE 0 END), 0) as total_expenses_paid
                FROM retail_sales_logs
                WHERE store_id = ?
            ");
            $finStmt->execute([$storeId]);
            $store['financials'] = $finStmt->fetch(\PDO::FETCH_ASSOC);

            // Ürün Bazlı Ciro
            $prodRevStmt = $db->prepare("
                SELECT p.name as product_name, p.unit_type,
                SUM(log.amount) as sold_qty,
                SUM(log.total_revenue) as revenue,
                SUM(log.net_profit) as net_profit
                FROM retail_sales_logs log
                JOIN products p ON log.product_id = p.id
                WHERE log.store_id = ? AND log.sale_type = 'retail_sale'
                GROUP BY log.product_id
            ");
            $prodRevStmt->execute([$storeId]);
            $store['product_revenues'] = $prodRevStmt->fetchAll(\PDO::FETCH_ASSOC);

            $logStmt = $db->prepare("
                SELECT log.*, p.name as product_name
                FROM retail_sales_logs log
                LEFT JOIN products p ON log.product_id = p.id
                WHERE log.store_id = ?
                ORDER BY log.created_at DESC LIMIT 50
            ");
            $logStmt->execute([$storeId]);
            $store['logs'] = $logStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($store);

        // Kasa Hareketleri Logları
        $cashLogsStmt = $db->prepare("
            SELECT ccl.*, rs.store_name
            FROM company_cash_logs ccl
            LEFT JOIN retail_stores rs ON ccl.store_id = rs.id
            WHERE ccl.user_id = ?
            ORDER BY ccl.created_at DESC LIMIT 100
        ");
        $cashLogsStmt->execute([$userId]);
        $companyCashLogs = $cashLogsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $cashSummaryStmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN transaction_type = 'out' THEN amount ELSE 0 END), 0) as total_out
            FROM company_cash_logs
            WHERE user_id = ?
        ");
        $cashSummaryStmt->execute([$userId]);
        $companyCashSummary = $cashSummaryStmt->fetch(\PDO::FETCH_ASSOC);

        // View'a tüm değişkenleri eksiksiz aktarıyoruz (Beyaz ekranı önler)
        return $this->view('app/market/index', [
            'player' => $player,
            'stores' => $stores,
            'inventory' => $inventory,
            'warehouse_summary' => $warehouseSummary,
            'shopTypes' => $shopTypes,
            'provinces' => $provinces,
            'products' => $products,
            'activeCards' => $activeCards,
            'now' => $now,
            'company_cash_logs' => $companyCashLogs,
            'company_cash_summary' => $companyCashSummary
        ]);
    }

    public function assignProduct()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /manager/app/market/index");
            exit;
        }

        $db = Database::getInstance();
        $userId = $this->getAuthenticatedUserId();

        $storeId = (int)($_POST['store_id'] ?? 0);
        $slotIndex = (int)($_POST['slot_index'] ?? 1);
        $productId = (int)($_POST['product_id'] ?? 0);
        $transferAmount = (float)($_POST['transfer_amount'] ?? 0);
        $customSellPrice = isset($_POST['custom_sell_price']) && $_POST['custom_sell_price'] !== '' ? (float)$_POST['custom_sell_price'] : null;

        if ($transferAmount <= 0 || $productId <= 0) {
            $_SESSION['error'] = "Geçersiz miktar veya ürün seçimi.";
            header("Location: /manager/app/market/index");
            exit;
        }

        try {
            $db->beginTransaction();

            $prodCheck = $db->prepare("SELECT * FROM products WHERE id = ?");
            $prodCheck->execute([$productId]);
            $product = $prodCheck->fetch(\PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \Exception("Ürün bulunamadı.");
            }

            $storeStmt = $db->prepare("SELECT * FROM retail_stores WHERE id = ? AND user_id = ?");
            $storeStmt->execute([$storeId, $userId]);
            $store = $storeStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$store) {
                throw new \Exception("İşletme bulunamadı veya yetkiniz yok.");
            }

            // Merkez depodan düşüş
            $warehouseStmt = $db->prepare("SELECT * FROM user_inventories WHERE user_id = ? AND product_id = ?");
            $warehouseStmt->execute([$userId, $productId]);
            $whItem = $warehouseStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$whItem || (float)$whItem['quantity'] < $transferAmount) {
                throw new \Exception("Merkez depoda yeterli ürün bulunmuyor.");
            }

            $unitCost = (float)$whItem['unit_cost'];
            $newWarehouseQty = (float)$whItem['quantity'] - $transferAmount;

            if ($newWarehouseQty <= 0) {
                $db->prepare("DELETE FROM user_inventories WHERE id = ?")->execute([$whItem['id']]);
            } else {
                $db->prepare("UPDATE user_inventories SET quantity = ? WHERE id = ?")->execute([$newWarehouseQty, $whItem['id']]);
            }

            $defaultSellPrice = $unitCost * 1.30;
            $finalSellPrice = $customSellPrice !== null ? $customSellPrice : $defaultSellPrice;

            // Slot güncelleme veya ekleme
            $slotStmt = $db->prepare("SELECT * FROM retail_store_slots WHERE store_id = ? AND slot_index = ?");
            $slotStmt->execute([$storeId, $slotIndex]);
            $slot = $slotStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$slot) {
                $db->prepare("INSERT INTO retail_store_slots (store_id, slot_index, product_id, amount, unit_cost, current_sell_price, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                   ->execute([$storeId, $slotIndex, $productId, $transferAmount, $unitCost, $finalSellPrice]);
            } else {
                $oldQty = (float)$slot['amount'];
                $oldCost = (float)$slot['unit_cost'];
                $totalQty = $oldQty + $transferAmount;
                $avgCost = $totalQty > 0 ? (($oldQty * $oldCost) + ($transferAmount * $unitCost)) / $totalQty : $unitCost;
                
                $db->prepare("UPDATE retail_store_slots SET product_id = ?, amount = ?, unit_cost = ?, current_sell_price = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$productId, $totalQty, $avgCost, $finalSellPrice, $slot['id']]);
            }

            // İşlem logu
            $db->prepare("
                INSERT INTO retail_sales_logs (
                    store_id, product_id, amount, unit_price, unit_base_cost, total_revenue, net_profit, sale_type, created_at
                ) VALUES (?, ?, ?, ?, ?, 0, 0, 'stock_transfer', NOW())
            ")->execute([$storeId, $productId, $transferAmount, $unitCost, $unitCost]);

            $db->commit();
            $_SESSION['success'] = "Mal alımı ve rafa aktarım başarıyla tamamlandı.";
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['error'] = "Aktarım hatası: " . $e->getMessage();
        }

        header("Location: /manager/app/market/index");
        exit;
    }

    public function sellStore()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /manager/app/market/index");
            exit;
        }

        $db = Database::getInstance();
        $userId = $this->getAuthenticatedUserId();
        $storeId = (int)($_POST['store_id'] ?? 0);

        $stmt = $db->prepare("SELECT rs.* FROM retail_stores rs WHERE rs.id = ? AND rs.user_id = ?");
        $stmt->execute([$storeId, $userId]);
        $store = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($store) {
            $refundAmount = 25000.00; // Tasfiye bedeli
            try {
                $db->beginTransaction();
                $db->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$refundAmount, $userId]);
                $db->prepare("DELETE FROM retail_store_slots WHERE store_id = ?")->execute([$storeId]);
                $db->prepare("DELETE FROM retail_stores WHERE id = ?")->execute([$storeId]);
                $db->commit();
                $_SESSION['success'] = "İşletmeniz tasfiye edildi ve " . number_format($refundAmount, 2) . " ₺ iade edildi.";
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['error'] = "Tasfiye hatası oluştu: " . $e->getMessage();
            }
        }
        header("Location: /manager/app/market/index");
        exit;
    }

    public function upgradeStore()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /manager/app/market/index");
            exit;
        }

        $db = Database::getInstance();
        $userId = $this->getAuthenticatedUserId();
        $storeId = (int)($_POST['store_id'] ?? 0);

        $stmt = $db->prepare("SELECT * FROM retail_stores WHERE id = ? AND user_id = ?");
        $stmt->execute([$storeId, $userId]);
        $store = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($store) {
            $currentLevel = (int)($store['level'] ?? 1);
            $nextLevel = $currentLevel + 1;
            $upgradeCost = $nextLevel * 25000;

            $playerStmt = $db->prepare("SELECT money FROM users WHERE id = ?");
            $playerStmt->execute([$userId]);
            $playerMoney = (float)$playerStmt->fetchColumn();

            if ($playerMoney >= $upgradeCost) {
                try {
                    $db->beginTransaction();
                    $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$upgradeCost, $userId]);
                    // Düzeltilmiş doğru parametre yapısı:
                    $db->prepare("UPDATE retail_stores SET level = ?, storage_capacity = storage_capacity + 1500 WHERE id = ? AND user_id = ?")->execute([$nextLevel, $storeId, $userId]);
                    $db->commit();
                    $_SESSION['success'] = "İşletme Seviye {$nextLevel}'e yükseltildi!";
                } catch (\Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $_SESSION['error'] = "Seviye atlatma sırasında hata oluştu: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Yeterli şirket kasası bakiyesi yok! Gerekli: " . number_format($upgradeCost, 2) . " ₺";
            }
        }
        header("Location: /manager/app/market/index");
        exit;
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $this->getAuthenticatedUserId();
            $shopTypeId = $_POST['store_type_id'] ?? 'market';
            $provinceId = (int)($_POST['province_id'] ?? 0);
            $storeName = trim($_POST['store_name'] ?? 'Yeni İşletme');

            try {
                $db->beginTransaction();
                $stmt = $db->prepare("
                    INSERT INTO retail_stores (
                        user_id, province_id, shop_type_id, store_name,
                        level, storage_capacity, current_stock_volume,
                        min_margin_percent, max_margin_percent,
                        market_demand_multiplier, advertising_multiplier,
                        status, cash, sales_counter, created_at
                    ) VALUES (?, ?, ?, ?, 1, 2000.00, 0.00, 15.00, 35.00, 1.00, 1.00, 1, 0.00, 0, NOW())
                ");
                $stmt->execute([$userId, $provinceId, $shopTypeId, $storeName]);
                $db->commit();
                $_SESSION['success'] = "Yeni işletme başarıyla kuruldu.";
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $_SESSION['error'] = "Mağaza kurma hatası: " . $e->getMessage();
            }
        }
        header("Location: /manager/app/market/index");
        exit;
    }

  public function ajaxSync()
    {
        $db = Database::getInstance();
        $userId = $this->getAuthenticatedUserId();

        if (class_exists('\\App\\Services\\RetailSalesEngine')) {
            \App\Services\RetailSalesEngine::processSales();
        }

        $now = $this->getCurrentDatabaseTime($db);
        $storesStmt = $db->prepare("SELECT id FROM retail_stores WHERE user_id = ?");
        $storesStmt->execute([$userId]);
        $stores = $storesStmt->fetchAll(\PDO::FETCH_ASSOC);

        $storesData = [];
        foreach ($stores as $store) {
            $storeId = $store['id'];
            $slotsStmt = $db->prepare("
                SELECT rss.*, p.name as product_name, p.unit_type, p.sales_interval_seconds,
                UNIX_TIMESTAMP(rss.updated_at) as last_updated_ts
                FROM retail_store_slots rss
                LEFT JOIN products p ON rss.product_id = p.id
                WHERE rss.store_id = ?
                ORDER BY rss.slot_index ASC
            ");
            $slotsStmt->execute([$storeId]);
            $slots = $slotsStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($slots as &$slot) {
                if (!empty($slot['product_id']) && (float)$slot['amount'] > 0) {
                    $interval = (int)($slot['sales_interval_seconds'] > 0 ? $slot['sales_interval_seconds'] : 30);
                    $lastUp = (int)($slot['last_updated_ts'] > 0 ? $slot['last_updated_ts'] : $now);
                    
                    // Geçen süre ve kalan süre hesaplamasındaki bozuk satırlar düzeltildi:
                    $elapsed = $now - $lastUp;
                    if ($elapsed < 0) $elapsed = 0;

                    $rem = $interval - ($elapsed % $interval);
                    if ($rem < 0) $rem = 0;

                    $slot['remaining_seconds'] = $rem;
                    $slot['total_duration'] = $interval;
                } else {
                    $slot['remaining_seconds'] = 0;
                    $slot['total_duration'] = 30;
                }
            }
            unset($slot);

            $storesData[$storeId] = [
                'slots' => $slots
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'stores' => $storesData,
            'server_time' => $now
        ]);
        exit;
    }
}
<?php
namespace App\Services;

use System\Core\Database;

class RetailSalesEngine
{
    const DEFAULT_TRANSPORT_COST = 2.50;
    const DEFAULT_OVERHEAD_COST = 1.50;
    const DEFAULT_ELECTRICITY_KWH = 2.00;
    const ARGE_DEFAULT_MULTIPLIER = 1.00;

    protected static function getCurrentDatabaseTime($db)
    {
        try {
            $stmt = $db->query("SELECT UNIX_TIMESTAMP()");
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return time();
        }
    }

    public static function processSales($storeId = null)
    {
        $db = Database::getInstance();
        try {
            $now = self::getCurrentDatabaseTime($db);
            $query = "
                SELECT rs.*, 
                       rs.user_id,
                       rs.last_expense_time,
                       COALESCE(st.base_tax_rate, 15.00) as base_tax_rate,
                       COALESCE(st.base_rent, 3000) as base_rent,
                       COALESCE(st.base_salary, 8000) as base_salary,
                       COALESCE(st.electricity_kwh_per_cycle, 2.00) as electricity_kwh_per_cycle,
                       COALESCE(st.max_slots, 1) as max_slots
                FROM retail_stores rs
                LEFT JOIN shop_types st ON rs.shop_type_id COLLATE utf8mb4_unicode_ci = st.id COLLATE utf8mb4_unicode_ci
                WHERE rs.status = 1
            ";
            $params = [];
            if ($storeId) {
                $query .= " AND rs.id = ?";
                $params[] = $storeId;
            }

            $storesStmt = $db->prepare($query);
            $storesStmt->execute($params);
            $stores = $storesStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($stores as $store) {
                self::processStoreSales($db, $store, $now);
            }
        } catch (\Exception $e) {
            error_log("RetailSalesEngine General Error: " . $e->getMessage());
        }
    }

    private static function processStoreSales($db, $store, $now)
    {
        $storeId = $store['id'];
        $userId = $store['user_id'];

        $slotsStmt = $db->prepare("
            SELECT rss.*, p.name as product_name, p.sales_interval_seconds,
                   UNIX_TIMESTAMP(rss.updated_at) as last_updated_ts
            FROM retail_store_slots rss
            LEFT JOIN products p ON rss.product_id = p.id
            WHERE rss.store_id = ? AND rss.product_id IS NOT NULL AND rss.amount > 0
        ");
        $slotsStmt->execute([$storeId]);
        $slots = $slotsStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($slots as $slotItem) {
            $interval = (int)($slotItem['sales_interval_seconds'] > 0 ? $slotItem['sales_interval_seconds'] : 30);
            $lastUpdated = (int)($slotItem['last_updated_ts'] > 0 ? $slotItem['last_updated_ts'] : $now);
            
            // $elapsed = $now - $lastUpdated;
            // if ($elapsed < 0) $elapsed = 0;

            // if ($elapsed < $interval) {
            //     continue; // Henüz satış zamanı gelmedi
            // }

            // $cycles = floor($elapsed / $interval);
            // if ($cycles <= 0) continue;

            // $amountPerCycle = 1;
            // $soldAmount = $cycles * $amountPerCycle;
            // $currentAmount = (float)$slotItem['amount'];

            // if ($soldAmount > $currentAmount) {
            //     $soldAmount = $currentAmount;
            // }

            // if ($soldAmount <= 0) continue;
            $elapsed = $now - $lastUpdated;
            if ($elapsed < 0) $elapsed = 0;

            if ($elapsed < $interval) {
                continue; // Henüz satış zamanı gelmedi
            }

            $cycles = floor($elapsed / $interval);
            if ($cycles <= 0) continue;

            // 1. DENGELİ SATIŞ SINIRLAMASI: 
            // Tek bir tetiklenmede (sayfa yenilendiğinde veya motor çalıştığında) 
            // en fazla kaç adet satılabileceğini sınırlandıralım (Örn: Maksimum 5 veya 10 adet).
            $maxUnitsPerBatch = 5; 
            $effectiveCycles = min((int)$cycles, $maxUnitsPerBatch);

            $amountPerCycle = 1;
            $soldAmount = $effectiveCycles * $amountPerCycle;
            $currentAmount = (float)$slotItem['amount'];

            if ($soldAmount > $currentAmount) {
                $soldAmount = $currentAmount;
            }

            if ($soldAmount <= 0) continue;

            // Zaman damgasını sadece satılan miktar kadar ilerletelim 
            // (Böylece kalan birikmiş zaman bir sonraki döngüye aktarılır, stok aniden erimez)
            $newTimestamp = $lastUpdated + ($soldAmount * $interval);

            try {
                $db->beginTransaction();

                // Kilitleme
                $lockStmt = $db->prepare("SELECT amount, current_sell_price, unit_cost FROM retail_store_slots WHERE id = ? FOR UPDATE");
                $lockStmt->execute([$slotItem['id']]);
                $lockedSlot = $lockStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$lockedSlot || (float)$lockedSlot['amount'] <= 0) {
                    $db->commit();
                    continue;
                }

                $soldAmount = min($soldAmount, (float)$lockedSlot['amount']);
                $newAmount = (float)$lockedSlot['amount'] - $soldAmount;
                $sellPrice = (float)$lockedSlot['current_sell_price'];
                $unitBaseCost = (float)$lockedSlot['unit_cost'];

                $totalRevenue = $soldAmount * $sellPrice;
                $totalProductCost = $soldAmount * $unitBaseCost;
                
                $unitTransportCost = self::DEFAULT_TRANSPORT_COST;
                $unitOverheadCost = self::DEFAULT_OVERHEAD_COST;
                $baseTaxRate = (float)($store['base_tax_rate'] ?? 15.00);
                
                $grossProfit = $totalRevenue - $totalProductCost;
                $totalTransport = $soldAmount * $unitTransportCost;
                $unitTaxCost = ($sellPrice > $unitBaseCost) ? (($sellPrice - $unitBaseCost) * ($baseTaxRate / 100)) : 0;
                $totalTax = $soldAmount * $unitTaxCost;
                
                $netProfit = $grossProfit - $totalTransport - $totalTax - ($soldAmount * $unitOverheadCost);

                $newTimestamp = date('Y-m-d H:i:s', $lastUpdated + ($cycles * $interval));

                if ($newAmount <= 0) {
                    $db->prepare("UPDATE retail_store_slots SET product_id = NULL, amount = 0, unit_cost = 0, current_sell_price = 0, updated_at = ? WHERE id = ?")
                       ->execute([$newTimestamp, $slotItem['id']]);
                } else {
                    $db->prepare("UPDATE retail_store_slots SET amount = ?, updated_at = ? WHERE id = ?")
                       ->execute([$newAmount, $newTimestamp, $slotItem['id']]);
                }

                // Mağaza ve kullanıcı kasasını güncelle
                $db->prepare("UPDATE retail_stores SET current_stock_volume = GREATEST(0, current_stock_volume - ?), cash = cash + ? WHERE id = ?")
                   ->execute([$soldAmount, $totalRevenue, $storeId]);

                $db->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                   ->execute([$totalRevenue, $userId]);

                // Kasa Logu (Girdi)
                $db->prepare("
                    INSERT INTO company_cash_logs (user_id, store_id, transaction_type, category, amount, description, created_at)
                    VALUES (?, ?, 'in', 'retail_sale', ?, ?, NOW())
                ")->execute([$userId, $storeId, $totalRevenue, "Perakende Satış Geliri [Ürün: {$slotItem['product_name']}, Miktar: {$soldAmount}, Birim Fiyat: {$sellPrice}₺]"]);
                // Enerji tüketimi
                $baseKwh = (float)($store['electricity_kwh_per_cycle'] ?? self::DEFAULT_ELECTRICITY_KWH);
                $kwhNeeded = $baseKwh * $cycles;
                self::consumeUserEnergy($db, $userId, $kwhNeeded);

                // Satış Logu
                $db->prepare("
                    INSERT INTO retail_sales_logs (
                        store_id, product_id, amount, unit_price, unit_base_cost, 
                        total_revenue, transport_cost, tax_amount, net_profit, sale_type, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'retail_sale', NOW())
                ")->execute([
                    $storeId, $slotItem['product_id'], $soldAmount, $sellPrice,
                    $unitBaseCost, $totalRevenue, $totalTransport, $totalTax, $netProfit
                ]);

                // 24 Saatte Bir (86400 Saniye) Sabit Gider Kontrolü
                $lastExpenseTs = !empty($store['last_expense_time']) ? strtotime($store['last_expense_time']) : $now;
                // Eğer ilk defa çalışıyorsa veya aradan 24 saat (86400 saniye) geçtiyse
                if (($now - $lastExpenseTs) >= 86400) {
                    $rentAmount = max(0, (float)($store['base_rent'] ?? 3000));
                    $salaryAmount = max(0, (float)($store['base_salary'] ?? 8000));
                    $totalExpense = $rentAmount + $salaryAmount;

                    $db->prepare("UPDATE retail_stores SET cash = GREATEST(0, cash - ?), last_expense_time = NOW() WHERE id = ?")
                       ->execute([$totalExpense, $storeId]);
                    
                    $db->prepare("UPDATE users SET money = GREATEST(0, money - ?) WHERE id = ?")
                       ->execute([$totalExpense, $userId]);

                    $db->prepare("
                        INSERT INTO company_cash_logs (user_id, store_id, transaction_type, category, amount, description, created_at)
                        VALUES (?, ?, 'out', 'store_expense', ?, ?, NOW())
                    ")->execute([$userId, $storeId, $totalExpense, "Mağaza Sabit Gideri [Kira: {$rentAmount}₺, Maaş: {$salaryAmount}₺]"]);

                    $db->prepare("
                        INSERT INTO retail_sales_logs (
                            store_id, product_id, amount, unit_price, total_revenue, net_profit, tax_amount, transport_cost, sale_type, created_at
                        ) VALUES (?, NULL, 0, 0, ?, ?, ?, ?, 'store_expense', NOW())
                    ")->execute([$storeId, -$totalExpense, -$totalExpense, $rentAmount, $salaryAmount]);
                }

                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("RetailSalesEngine Slot Error: " . $e->getMessage());
            }
        }
    }

    private static function consumeUserEnergy($db, $userId, $amountNeeded)
    {
        try {
            $stmt = $db->prepare("
                SELECT ui.* FROM user_inventories ui
                JOIN products p ON ui.product_id = p.id
                WHERE ui.user_id = ? AND (p.type = 'energy' OR p.type = 'utility' OR p.name LIKE '%Elektrik%' OR p.name LIKE '%Enerji%')
                LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$userId]);
            $energyItem = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($energyItem && (float)$energyItem['quantity'] >= $amountNeeded) {
                $newQty = (float)$energyItem['quantity'] - $amountNeeded;
                if ($newQty <= 0) {
                    $db->prepare("DELETE FROM user_inventories WHERE id = ?")->execute([$energyItem['id']]);
                } else {
                    $db->prepare("UPDATE user_inventories SET quantity = ? WHERE id = ?")->execute([$newQty, $energyItem['id']]);
                }
            }
        } catch (\Exception $e) {
            error_log("Energy consumption error: " . $e->getMessage());
        }
    }
}
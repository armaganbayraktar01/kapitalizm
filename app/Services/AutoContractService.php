<?php

namespace App\Services;

use System\Core\Database;
use Exception;
use PDO;

class AutoContractService 
{
    private PDO $db;

    public function __construct(?PDO $db = null) 
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Zamanı gelen tüm aktif otomatik sözleşmeleri işler ve tetikler.
     */
    public function processActiveContracts(): array 
    {
        $stats = [
            'processed_count' => 0,
            'total_items_transferred' => 0,
            'total_revenue' => 0.00,
            'errors' => []
        ];

        try {
            $now = time();

            // Süresi gelen aktif sözleşmeleri çek
            $stmt = $this->db->prepare("
                SELECT rc.*, rsi.amount as stock_amount, rsi.current_sell_price, rsi.average_cost
                FROM retail_contracts rc
                JOIN retail_store_inventories rsi ON rc.store_id = rsi.store_id AND rc.product_id = rsi.product_id
                WHERE rc.status = 'active' AND (rc.last_run_time + rc.interval_seconds) <= ?
            ");
            $stmt->execute([$now]);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($contracts as $contract) {
                try {
                    $this->executeSingleContract($contract, $now);
                    $stats['processed_count']++;
                    $stats['total_items_transferred'] += (float)$contract['amount_per_cycle'];
                    $stats['total_revenue'] += ((float)$contract['amount_per_cycle'] * (float)$contract['current_sell_price']);
                } catch (Exception $e) {
                    $stats['errors'][] = "Sözleşme ID {$contract['id']} hata: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'message' => 'Otomatik sözleşme döngüsü tamamlandı.',
                'stats' => $stats
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sözleşme motoru hatası: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Tekil bir sözleşmeyi atomik olarak (Transaction ile) gerçekleştirir.
     */
    private function executeSingleContract(array $contract, int $now): void 
    {
        $contractId = (int)$contract['id'];
        $storeId = (int)$contract['store_id'];
        $buyerUserId = (int)$contract['buyer_user_id'];
        $productId = (int)$contract['product_id'];
        $requestedAmount = (float)$contract['amount_per_cycle'];
        $availableStock = (float)$contract['stock_amount'];
        $unitPrice = (float)$contract['current_sell_price'];
        $averageCost = (float)$contract['average_cost'];

        // Çekilecek miktar market stokundan fazla olamaz
        $transferAmount = min($requestedAmount, $availableStock);
        if ($transferAmount <= 0) {
            return; // Stok yetersizse bu döngüyü atla
        }

        $totalCost = round($transferAmount * $unitPrice, 2);
        $totalProfit = round($transferAmount * ($unitPrice - $averageCost), 2);

        $this->db->beginTransaction();

        try {
            // 1. Alıcının yeterli parası var mı kontrol et ve kasadan düş
            $buyerMoneyStmt = $this->db->prepare("SELECT money FROM users WHERE id = ? FOR UPDATE");
            $buyerMoneyStmt->execute([$buyerUserId]);
            $buyerMoney = (float)$buyerMoneyStmt->fetchColumn();

            if ($buyerMoney < $totalCost) {
                // Para yetersizse sözleşmeyi geçici olarak durdur veya pas geç
                $this->db->commit();
                return;
            }

            // Alıcının parasını düş
            $this->db->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                     ->execute([$totalCost, $buyerUserId]);

            // 2. Market sahibine (veya sisteme) parayı aktar (Marketin bağlı olduğu user_id'yi bul)
            $storeOwnerStmt = $this->db->prepare("SELECT user_id FROM retail_stores WHERE id = ?");
            $storeOwnerStmt->execute([$storeId]);
            $storeOwnerId = (int)$storeOwnerStmt->fetchColumn();

            if ($storeOwnerId > 0) {
                $this->db->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                         ->execute([$totalCost, $storeOwnerId]);
            }

            // 3. Market stokunu düş
            $this->db->prepare("
                UPDATE retail_store_inventories 
                SET amount = amount - ? 
                WHERE store_id = ? AND product_id = ?
            ")->execute([$transferAmount, $storeId, $productId]);

            // Stok sıfırlandıysa temizle
            $this->db->prepare("DELETE FROM retail_store_inventories WHERE store_id = ? AND product_id = ? AND amount <= 0")
                     ->execute([$storeId, $productId]);

            // Market genel doluluk hacmini güncelle
            $this->db->prepare("UPDATE retail_stores SET current_stock_volume = GREATEST(0, current_stock_volume - ?) WHERE id = ?")
                     ->execute([$transferAmount, $storeId]);

            // 4. Alıcının Envanterine (`user_inventories`) ürünleri ekle
            $invCheck = $this->db->prepare("SELECT id, quantity FROM user_inventories WHERE user_id = ? AND product_id = ? FOR UPDATE");
            $invCheck->execute([$buyerUserId, $productId]);
            $existingInv = $invCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingInv) {
                $this->db->prepare("UPDATE user_inventories SET quantity = quantity + ? WHERE id = ?")
                         ->execute([$transferAmount, $existingInv['id']]);
            } else {
                $this->db->prepare("INSERT INTO user_inventories (user_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")
                         ->execute([$buyerUserId, $productId, $transferAmount, $unitPrice]);
            }

            // 5. Sözleşmenin son çalışma zamanını güncelle (`last_run_time`)
            $this->db->prepare("UPDATE retail_contracts SET last_run_time = ? WHERE id = ?")
                     ->execute([$now, $contractId]);

            // 6. Satış Logunu Kaydet (`retail_sales_logs`)
            $this->db->prepare("
                INSERT INTO retail_sales_logs 
                (store_id, product_id, buyer_user_id, amount, unit_price, total_revenue, profit_amount, sale_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'auto_contract')
            ")->execute([
                $storeId,
                $productId,
                $buyerUserId,
                $transferAmount,
                $unitPrice,
                $totalCost,
                $totalProfit
            ]);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Sözleşme işleme hatası: " . $e->getMessage());
        }
    }

    /**
     * Oyuncu yeni bir otomatik sözleşme oluşturur.
     */
    public function createContract(int $storeId, int $buyerUserId, int $productId, float $amountPerCycle, int $intervalSeconds = 3600): array 
    {
        try {
            if ($amountPerCycle <= 0 || $intervalSeconds < 300) {
                throw new Exception("Geçersiz miktar veya çok kısa süre aralığı (minimum 5 dakika).");
            }

            $stmt = $this->db->prepare("
                INSERT INTO retail_contracts (store_id, buyer_user_id, product_id, amount_per_cycle, interval_seconds, last_run_time, status)
                VALUES (?, ?, ?, ?, ?, 0, 'active')
            ");
            $stmt->execute([$storeId, $buyerUserId, $productId, $amountPerCycle, $intervalSeconds]);

            return [
                'success' => true,
                'message' => 'Otomatik tedarik sözleşmesi başarıyla kuruldu.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sözleşme oluşturulamadı: ' . $e->getMessage()
            ];
        }
    }
}
<?php

namespace App\Services;

use System\Core\Database;
use Exception;
use PDO;

class NpcSourcingService 
{
    private PDO $db;

    public function __construct(?PDO $db = null) 
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Bir marketin eksik stokları için 4 aşamalı hiyerarşik tedarik algoritmasını çalıştırır.
     * 
     * @param int $storeId Tedarik yapılacak market/işletme ID'si
     * @param int $productId Tedarik edilmek istenen ürün ID'si
     * @param float $requestedAmount Talep edilen stok miktarı
     * @return array İşlem sonucu ve detaylı durum bilgisi
     */
    public function executeAutoSourcing(int $storeId, int $productId, float $requestedAmount): array 
    {
        try {
            // 1. İşletme ve Depo Durum Kontrolü
            $store = $this->getStoreDetails($storeId);
            if (!$store) {
                throw new Exception("İşletme bulunamadı. (ID: {$storeId})");
            }

            if (!(bool)$store['auto_sourcing_active']) {
                return ['success' => false, 'message' => 'Bu işletmede otomatik tedarik devre dışı.'];
            }

            // Kapasite Aşım Kontrolü
            $availableCapacity = (float)$store['storage_capacity'] - (float)$store['current_stock_volume'];
            if ($availableCapacity <= 0) {
                throw new Exception("İşletmenin depo kapasitesi tamamen dolu.");
            }

            // Çekilecek nihai miktar (Kapasiteyi aşamaz)
            $amountToProcure = min($requestedAmount, $availableCapacity);

            // 2. Hiyerarşik Tedarik Arama Algoritması
            $sourceResult = $this->findBestSource(
                (int)$store['user_id'], 
                (int)$store['province_id'], 
                $productId, 
                $amountToProcure
            );

            if (!$sourceResult['found']) {
                return [
                    'success' => false, 
                    'message' => 'Kendi ilinde, zincir mağazalarında veya en yakın 5 ilde uygun stok bulunamadı.'
                ];
            }

            // 3. Siparişi Veritabanına Yansıtma (Transaction İşlemi)
            return $this->processTransitOrder($store, $sourceResult, $productId);

        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'SOURCING_ERROR',
                'message' => 'Tedarik süreci başarısız: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 4 Aşamalı Hiyerarşik Kaynak Arama Algoritması
     */
    private function findBestSource(int $userId, int $provinceId, int $productId, float $neededAmount): array 
    {
        // --- 1. ÖNCELİK: Kendi İli - Kendi Üreticisi (Oyuncunun kendi tesis envanteri) ---
        $source = $this->checkPlayerOwnProvinceStock($userId, $provinceId, $productId, $neededAmount);
        if ($source) {
            return array_merge($source, ['priority_level' => 1, 'found' => true]);
        }

        // --- 2. ÖNCELİK: Kendi İli - Diğer Satıcılar/Oyuncular ---
        $source = $this->checkProvinceMarketStock($provinceId, $productId, $neededAmount, $userId);
        if ($source) {
            return array_merge($source, ['priority_level' => 2, 'found' => true]);
        }

        // --- 3. ÖNCELİK: Kendi Zincir İşletmeleri (Farklı illerdeki kendi ana depoları) ---
        $source = $this->checkPlayerChainStoresStock($userId, $provinceId, $productId, $neededAmount);
        if ($source) {
            return array_merge($source, ['priority_level' => 3, 'found' => true]);
        }

        // --- 4. ÖNCELİK: En Yakın 5 İl Mesafesi (province_distances matrisi ile) ---
        $source = $this->checkNearestFiveProvincesStock($provinceId, $productId, $neededAmount);
        if ($source) {
            return array_merge($source, ['priority_level' => 4, 'found' => true]);
        }

        return ['found' => false];
    }

    /**
     * 1. Aşama: Aynı ildeki kendi üretici tesis stoku
     */
    private function checkPlayerOwnProvinceStock(int $userId, int $provinceId, int $productId, float $amount): ?array 
    {
        $stmt = $this->db->prepare("
            SELECT ubi.amount, p.base_price as unit_cost, ub.province_id
            FROM user_building_inventories ubi
            JOIN user_buildings ub ON ubi.user_building_id = ub.id
            JOIN products p ON ubi.product_id = p.id
            WHERE ub.user_id = ? AND ub.province_id = ? AND ubi.product_id = ? AND ubi.amount >= ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $provinceId, $productId, $amount]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            return [
                'source_type' => 'own_building',
                'source_province_id' => $res['province_id'],
                'unit_cost' => (float)$res['unit_cost'],
                'amount' => $amount,
                'distance_km' => 0.0
            ];
        }
        return null;
    }

    /**
     * 2. Aşama: Kendi ilindeki diğer oyuncu/pazar stokları
     */
    private function checkProvinceMarketStock(int $provinceId, int $productId, float $amount, int $excludeUserId): ?array 
    {
        $stmt = $this->db->prepare("
            SELECT rsi.amount, rsi.current_sell_price as unit_cost, rs.province_id
            FROM retail_store_inventories rsi
            JOIN retail_stores rs ON rsi.store_id = rs.id
            WHERE rs.province_id = ? AND rsi.product_id = ? AND rsi.amount >= ? AND rs.user_id != ?
            LIMIT 1
        ");
        $stmt->execute([$provinceId, $productId, $amount, $excludeUserId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            return [
                'source_type' => 'local_market',
                'source_province_id' => $res['province_id'],
                'unit_cost' => (float)$res['unit_cost'],
                'amount' => $amount,
                'distance_km' => 0.0
            ];
        }
        return null;
    }

    /**
     * 3. Aşama: Oyuncunun farklı illerdeki kendi depoları
     */
    private function checkPlayerChainStoresStock(int $userId, int $currentProvinceId, int $productId, float $amount): ?array 
    {
        $stmt = $this->db->prepare("
            SELECT rsi.amount, rsi.average_cost as unit_cost, rs.province_id,
                   COALESCE(pd.distance_km, 100.00) as distance_km
            FROM retail_store_inventories rsi
            JOIN retail_stores rs ON rsi.store_id = rs.id
            LEFT JOIN province_distances pd ON 
                 (pd.source_province_id = rs.province_id AND pd.target_province_id = ?) OR
                 (pd.source_province_id = ? AND pd.target_province_id = rs.province_id)
            WHERE rs.user_id = ? AND rs.province_id != ? AND rsi.product_id = ? AND rsi.amount >= ?
            ORDER BY distance_km ASC
            LIMIT 1
        ");
        $stmt->execute([$currentProvinceId, $currentProvinceId, $userId, $currentProvinceId, $productId, $amount]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            return [
                'source_type' => 'chain_store',
                'source_province_id' => $res['province_id'],
                'unit_cost' => (float)$res['unit_cost'],
                'amount' => $amount,
                'distance_km' => (float)$res['distance_km']
            ];
        }
        return null;
    }

    /**
     * 4. Aşama: province_distances tablosundan en yakın 5 ilin taranması
     */
    private function checkNearestFiveProvincesStock(int $currentProvinceId, int $productId, float $amount): ?array 
    {
        // En yakın 5 ili mesafeye göre sıralı çek
        $distStmt = $this->db->prepare("
            SELECT 
                CASE WHEN source_province_id = ? THEN target_province_id ELSE source_province_id END AS near_province_id,
                distance_km
            FROM province_distances
            WHERE source_province_id = ? OR target_province_id = ?
            ORDER BY distance_km ASC
            LIMIT 5
        ");
        $distStmt->execute([$currentProvinceId, $currentProvinceId, $currentProvinceId]);
        $nearestProvinces = $distStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($nearestProvinces as $prov) {
            $targetProvId = (int)$prov['near_province_id'];
            $distKm = (float)$prov['distance_km'];

            // O ildeki ürün stoklarını denetle
            $stockStmt = $this->db->prepare("
                SELECT rsi.amount, rsi.current_sell_price as unit_cost
                FROM retail_store_inventories rsi
                JOIN retail_stores rs ON rsi.store_id = rs.id
                WHERE rs.province_id = ? AND rsi.product_id = ? AND rsi.amount >= ?
                LIMIT 1
            ");
            $stockStmt->execute([$targetProvId, $productId, $amount]);
            $res = $stockStmt->fetch(PDO::FETCH_ASSOC);

            if ($res) {
                return [
                    'source_type' => 'regional_nearest_five',
                    'source_province_id' => $targetProvId,
                    'unit_cost' => (float)$res['unit_cost'],
                    'amount' => $amount,
                    'distance_km' => $distKm
                ];
            }
        }

        return null;
    }

    /**
     * Transit sevkiyat oluşturma (Nakliye Maliyeti & Süre Hesabı ile)
     */
    private function processTransitOrder(array $store, array $source, int $productId): array 
    {
        $this->db->beginTransaction();

        try {
            $distanceKm = $source['distance_km'];
            $amount = $source['amount'];
            $userId = (int)$store['user_id'];

            // Sertifika Çarpanlarını Hesabla (research_cards üzerinden)
            $logisticsMultiplier = $this->getUserLogisticsSpeedMultiplier($userId);
            
            // Nakliye Hesabı (Mesafe * Miktar * Birim Fiyat)
            $baseUnitFreight = 0.50; // KM ve Birim başına baz nakliye bedeli
            $totalTransportFee = $distanceKm * $amount * $baseUnitFreight;

            // Nakliye Süresi Hesabı (Her 10 km 60 saniye varsayılan, hız çarpanına bölünür)
            $baseTravelTimeSec = max(60, (int)round(($distanceKm * 6) / $logisticsMultiplier));
            
            $now = time();
            $arrivalTime = $now + $baseTravelTimeSec;

            // Ara Depo / Transit Tablosuna Kayıt
            $insertStmt = $this->db->prepare("
                INSERT INTO transit_inventories 
                (store_id, product_id, amount, unit_cost, transport_fee, source_province_id, target_province_id, departure_time, arrival_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_transit')
            ");
            $insertStmt->execute([
                $store['id'],
                $productId,
                $amount,
                $source['unit_cost'],
                $totalTransportFee,
                $source['source_province_id'],
                $store['province_id'],
                $now,
                $arrivalTime
            ]);

            // İşletme geçici stok doluluğunu güncelle
            $updateCapStmt = $this->db->prepare("
                UPDATE retail_stores 
                SET current_stock_volume = current_stock_volume + ? 
                WHERE id = ?
            ");
            $updateCapStmt->execute([$amount, $store['id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Tedarik siparişi başarıyla oluşturuldu ve yola çıktı.',
                'details' => [
                    'store_id' => $store['id'],
                    'product_id' => $productId,
                    'amount' => $amount,
                    'priority_level' => $source['priority_level'],
                    'distance_km' => $distanceKm,
                    'transport_fee' => $totalTransportFee,
                    'arrival_in_seconds' => $baseTravelTimeSec
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Transit kaydı oluşturulurken transaction hatası: " . $e->getMessage());
        }
    }

    private function getStoreDetails(int $storeId): ?array 
    {
        $stmt = $this->db->prepare("SELECT * FROM retail_stores WHERE id = ?");
        $stmt->execute([$storeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getUserLogisticsSpeedMultiplier(int $userId): float 
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(rc.effect_transport_speed), 1.00) as speed_mult
            FROM user_cards uc
            JOIN research_cards rc ON uc.card_id = rc.id
            WHERE uc.user_id = ? AND uc.is_active = 1
        ");
        $stmt->execute([$userId]);
        $val = (float)$stmt->fetchColumn();
        return $val > 0 ? $val : 1.00;
    }
}
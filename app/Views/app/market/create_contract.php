<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0"><i class="fas fa-file-contract me-2"></i>Yeni Otomatik Alım Kontratı Kur</h5>
    </div>
    <div class="card-body">
        <form action="/app/market/createAutoContract" method="POST">
            
            <!-- Hedef Perakende Mağazası Seçimi -->
            <div class="mb-3">
                <label for="store_id" class="form-label fw-bold">Hedef Perakende Mağazası</label>
                <select name="store_id" id="store_id" class="form-select" required>
                    <option value="" disabled selected>-- Mağaza Seçiniz --</option>
                    <?php if (!empty($stores)): ?>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?= $store['id'] ?>">
                                <?= htmlspecialchars($store['store_name']) ?> (<?= htmlspecialchars($store['province_name'] ?? 'İl Bilgisi Yok') ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Aktif mağazanız bulunmuyor.</option>
                    <?php endif; ?>
                </select>
                <div class="form-text">Ürünün tedarik edileceği ve raflara dizileceği mağazayı seçin.</div>
            </div>

            <!-- Tedarik Edilecek Ürün Seçimi -->
            <div class="mb-3">
                <label for="product_id" class="form-label fw-bold">Tedarik Edilecek Ürün</label>
                <select name="product_id" id="product_id" class="form-select" required>
                    <option value="" disabled selected>-- Ürün Seçiniz --</option>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>">
                                <?= htmlspecialchars($product['name']) ?> (Birim Maliyet: <?= number_format($product['base_price'] ?? 0, 2) ?> ₺)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Eğer controller'dan $products dizisi gelmediyse manuel ID girişi için yedek input veya dinamik liste -->
                        <option value="2">Buğday (Örnek)</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Her Döngüde Çekilecek Miktar -->
            <div class="mb-3">
                <label for="amount_per_cycle" class="form-label fw-bold">Her Döngüde Çekilecek Miktar</label>
                <input type="number" step="0.01" name="amount_per_cycle" id="amount_per_cycle" class="form-control" placeholder="Örn: 100.00" required>
                <div class="form-text">Her periyodik tetiklemede depodan/zincirden çekilip rafa eklenecek miktar.</div>
            </div>

            <!-- Yenileme Aralığı (Süre) -->
            <div class="mb-3">
                <label for="interval_seconds" class="form-label fw-bold">Yenileme Aralığı (Döngü Süresi)</label>
                <select name="interval_seconds" id="interval_seconds" class="form-select" required>
                    <option value="900">15 Dakikada Bir (900 sn)</option>
                    <option value="1800" selected>30 Dakikada Bir (1800 sn)</option>
                    <option value="3600">1 Saatte Bir (3600 sn)</option>
                    <option value="7200">2 Saatte Bir (7200 sn)</option>
                </select>
                <div class="form-text">Süre dolduğunda arka plandaki motor otomatik olarak alım işlemini gerçekleştirecektir.</div>
            </div>

            <!-- Form Gönderme Butonu -->
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-check-circle me-1"></i> Kontratı Başlat ve Kaydet
                </button>
            </div>

        </form>
    </div>
</div>
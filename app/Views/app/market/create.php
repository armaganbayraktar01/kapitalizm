<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="card-title mb-0 fw-bold">
            <i class="fas fa-store-alt text-warning me-2"></i>Yeni Perakende İşletmesi Kurulum Merkezi
        </h5>
    </div>
    <div class="card-body bg-light p-4">
        <form action="/manager/app/market/store" method="POST">
            
            <!-- İşletme Türü (Şablon) Seçimi -->
            <div class="mb-3">
                <label for="store_type_id" class="form-label fw-bold text-secondary">İşletme Türü (Şablon Seçimi)</label>
                <select name="store_type_id" id="store_type_id" class="form-select form-select-lg" required>
                    <option value="" disabled selected>-- İşletme Türünü Seçiniz --</option>
                    <option value="bakkal">Dükkan / Bakkal (Tek Çeşit Ürün - 200 Birim Depo)</option>
                    <option value="market">Market (Orta Ölçekli - 1.000 Birim Depo)</option>
                    <option value="magaza">Mağaza (Geniş Çeşit - 5.000 Birim Depo)</option>
                    <option value="avm">AVM / Hipermarket (Devasa Kapasite - 20.000 Birim Depo)</option>
                </select>
                <div class="form-text">Seçtiğiniz işletme türü raf slot sayısını ve maksimum stok hacmini belirler.</div>
            </div>

            <!-- Kurulacak İl Seçimi -->
            <div class="mb-3">
                <label for="province_id" class="form-label fw-bold text-secondary">Kurulacak İl (Bölge)</label>
                <select name="province_id" id="province_id" class="form-select form-select-lg" required>
                    <option value="" disabled selected>-- İl Seçiniz --</option>
                    <?php if (!empty($provinces)): ?>
                        <?php foreach ($provinces as $prov): ?>
                            <option value="<?= $prov['id']; ?>">
                                <?= htmlspecialchars($prov['name']); ?> (Nüfus: <?= number_format($prov['population'] ?? 1000000); ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Kayıtlı il bulunamadı.</option>
                    <?php endif; ?>
                </select>
                <div class="form-text">İlin nüfusu ve demografik yapısı satış hızınızı ve cironuzu doğrudan etkiler.</div>
            </div>

            <!-- İşletme Adı -->
            <div class="mb-3">
                <label for="store_name" class="form-label fw-bold text-secondary">İşletme Adı</label>
                <input type="text" name="store_name" id="store_name" class="form-control form-control-lg" placeholder="Örn: İzmir Merkez Süpermarket" required>
            </div>

            <!-- Gönderme Butonu -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="/manager/app/market/index" class="btn btn-outline-secondary px-4">İptal</a>
                <button type="submit" class="btn btn-success fw-bold px-5">
                    <i class="fas fa-check-circle me-2"></i>Yatırımı Onayla ve Kur
                </button>
            </div>

        </form>
    </div>
</div>
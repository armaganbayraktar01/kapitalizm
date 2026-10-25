<div class="container-fluid">
    <h2 class="mb-4">Yeni Bina Tipi Ekle</h2>

    <form action="/manager/admin/buildingtype/store" method="POST" enctype="multipart/form-data">
        
        <div class="mb-3">
            <label class="form-label">Bina Adı</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <select name="category" class="form-control">
                <option value="farm">Tarım / Tarla (farm)</option>
                <option value="factory">Fabrika / İşleme Tesisi (factory)</option>
                <option value="mine">Maden (mine)</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Komplekslik Tipi (complexity_type)</label>
            <select name="complexity_type" class="form-control">
                <option value="raw">Hammaddesiz (raw)</option>
                <option value="semi">İşlenmiş / Girdili (semi)</option>
                <option value="finished">Nihai Ürün (finished)</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Ürettiği Ürün (Produces Product)</label>
            <select name="produces_product_id" class="form-control">
                <option value="">-- Ürün Seçin --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= $product['id']; ?>"><?= htmlspecialchars($product['name']); ?> (<?= htmlspecialchars($product['type'] ?? ''); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Hammadde Girdi Ayarları -->
        <div class="card p-3 mb-3 bg-light">
            <h5 class="text-primary">Hammadde Girdi Ayarları (Değirmen ve Fabrikalar İçin)</h5>
            
            <div class="mb-3">
                <label class="form-label">Tükettiği Hammadde (Consumes Product)</label>
                <select name="consumes_product_id" class="form-control">
                    <option value="">-- Hammadde Gerekmiyor (Tarla/Maden) --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id']; ?>"><?= htmlspecialchars($product['name']); ?> (<?= htmlspecialchars($product['type'] ?? ''); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Değirmen gibi tesisler için buğday vb. hammadde seçilmelidir.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Her Döngüde Tüketim Miktarı (Consumption Amount)</label>
                <input type="number" step="0.01" name="consumption_amount" class="form-control" value="0.00">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Döngü Başına Üretim Miktarı (Production Rate)</label>
            <input type="number" name="production_rate" class="form-control" value="4" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Birim Tipi (Unit Type)</label>
            <input type="text" name="unit_type" class="form-control" value="Adet">
        </div>

        <div class="mb-3">
            <label class="form-label">Kurulum Maliyeti (Base Cost)</label>
            <input type="number" step="0.01" name="base_cost" class="form-control" value="50000.00" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Gerekli Skor / Seviye (Required Score)</label>
            <input type="number" name="required_score" class="form-control" value="100">
        </div>

        <div class="mb-3">
            <label class="form-label">Depo Tipi (Storage Type)</label>
            <input type="text" name="storage_type" class="form-control" value="raw">
        </div>

        <div class="mb-3">
            <label class="form-label">Enerji Tüketimi</label>
            <input type="number" name="energy_consumption" class="form-control" value="50">
        </div>

        <div class="mb-3">
            <label class="form-label">Su Tüketimi</label>
            <input type="number" name="water_consumption" class="form-control" value="10">
        </div>

        <div class="mb-3">
            <label class="form-label">Gerekli İşgücü</label>
            <input type="number" name="labor_required" class="form-control" value="10">
        </div>

        <div class="mb-3">
            <label class="form-label">Bina Görseli (Image)</label>
            <input type="file" name="image" class="form-control">
        </div>

        <button type="submit" class="btn btn-success">Bina Tipini Kaydet</button>
        <a href="/manager/admin/buildingtype" class="btn btn-secondary">İptal</a>
    </form>
</div>
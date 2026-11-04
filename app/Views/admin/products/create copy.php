<div class="container-fluid px-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-plus-circle me-2 text-primary"></i> Yeni Ürün ve Reçete Ekle</h1>
        <a href="<?= base_url('admin/product'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/product/store'); ?>" method="POST" enctype="multipart/form-data">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light fw-bold"><i class="fas info-circle me-1"></i> Temel Ürün Bilgileri</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Ürün Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: Ekmek, Un, Buğday" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Ürün Türü</label>
                        <select name="type" id="productType" class="form-select" required onchange="toggleRecipeSection()">
                            <option value="raw">Hammadde (Raw)</option>
                            <option value="semi">Yarı Mamül (Semi)</option>
                            <option value="finished">Mamül Ürün (Finished)</option>
                            <option value="utility">Şebeke / Kaynak (Utility)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Taban Fiyat (TL)</label>
                        <input type="number" step="0.01" name="base_price" class="form-control" value="10.00" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Ürün Görseli</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
        </div>

        <!-- Reçete Bölümü (Hammadde harici durumlarda aktif olur) -->
        <div class="card border-0 shadow-sm mb-4" id="recipeSection" style="display: none;">
            <div class="card-header bg-warning bg-opacity-25 fw-bold text-dark"><i class="fas fa-cogs me-1"></i> Üretim Reçetesi Ayarları</div>
            <div class="card-body">
                <div class="mb-3 col-md-3">
                    <label class="form-label fw-bold">Tek Seferde Üretilen Çıktı Miktarı</label>
                    <input type="number" name="output_quantity" class="form-control" value="1" min="1">
                </div>
                <label class="form-label fw-bold">Gerekli Girdiler ve Miktarları:</label>
                <div class="row g-3">
                    <?php foreach ($allProducts as $ap): ?>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text" style="width: 140px;"><?= htmlspecialchars($ap['name']); ?></span>
                                <input type="number" name="recipe_items[<?= $ap['id']; ?>]" class="form-control" placeholder="Miktar" value="0" min="0">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="text-end mb-5">
            <button type="submit" class="btn btn-success px-5 py-2 fw-bold fs-5">
                <i class="fas fa-save me-2"></i> Ürünü ve Reçeteyi Kaydet
            </button>
        </div>
    </form>
</div>

<script>
function toggleRecipeSection() {
    const type = document.getElementById('productType').value;
    const recipeSection = document.getElementById('recipeSection');
    if (type === 'raw' || type === 'utility') {
        recipeSection.style.display = 'none';
    } else {
        recipeSection.style.display = 'block';
    }
}
// Sayfa yüklendiğinde çalıştır
toggleRecipeSection();
</script>
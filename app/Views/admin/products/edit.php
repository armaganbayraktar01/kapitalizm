<div class="container-fluid px-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-edit me-2 text-warning"></i> Ürün & Reçete Düzenle: <?= htmlspecialchars($product['name']); ?></h1>
        <a href="<?= base_url('admin/product'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/product/update/' . $product['id']); ?>" method="POST" enctype="multipart/form-data">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light fw-bold"><i class="fas fa-info-circle me-1"></i> Temel Ürün Bilgileri</div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Ürün Adı</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Ürün Türü</label>
                        <select name="type" id="productType" class="form-select" required onchange="toggleRecipeSection()">
                            <option value="raw" <?= $product['type'] === 'raw' ? 'selected' : ''; ?>>Hammadde (Raw)</option>
                            <option value="semi" <?= $product['type'] === 'semi' ? 'selected' : ''; ?>>Yarı Mamül (Semi)</option>
                            <option value="finished" <?= $product['type'] === 'finished' ? 'selected' : ''; ?>>Mamül Ürün (Finished)</option>
                            <option value="utility" <?= $product['type'] === 'utility' ? 'selected' : ''; ?>>Şebeke / Kaynak (Utility)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Taban Fiyat (TL)</label>
                        <input type="number" step="0.01" name="base_price" class="form-control" value="<?= $product['base_price']; ?>" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-center gap-2">
                        <img src="<?= base_url('public/uploads/products/' . ($product['image'] ?: 'default.png')); ?>" alt="" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;">
                        <div class="flex-grow-1">
                            <label class="form-label fw-bold small">Yeni Görsel</label>
                            <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reçete Bölümü -->
        <div class="card border-0 shadow-sm mb-4" id="recipeSection" style="display: <?= ($product['type'] === 'raw' || $product['type'] === 'utility') ? 'none' : 'block'; ?>;">
            <div class="card-header bg-warning bg-opacity-25 fw-bold text-dark"><i class="fas fa-cogs me-1"></i> Üretim Reçetesi Ayarları</div>
            <div class="card-body">
                <div class="mb-3 col-md-3">
                    <label class="form-label fw-bold">Tek Seferde Üretilen Çıktı Miktarı</label>
                    <input type="number" name="output_quantity" class="form-control" value="<?= $recipe['output_quantity'] ?? 1; ?>" min="1">
                </div>
                <label class="form-label fw-bold">Gerekli Girdiler ve Miktarları:</label>
                <div class="row g-3">
                    <?php foreach ($allProducts as $ap): ?>
                        <?php 
                            // Kendi kendini girdi olarak seçmesini engellemek için isteğe bağlı atlayabiliriz ya da değer koyabiliriz
                            $currentQty = $recipeItemsMap[$ap['id']] ?? 0;
                        ?>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text" style="width: 140px;"><?= htmlspecialchars($ap['name']); ?></span>
                                <input type="number" name="recipe_items[<?= $ap['id']; ?>]" class="form-control" placeholder="Miktar" value="<?= $currentQty; ?>" min="0">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="text-end mb-5">
            <button type="submit" class="btn btn-warning px-5 py-2 fw-bold fs-5 text-dark">
                <i class="fas fa-sync-alt me-2"></i> Ürünü ve Reçeteyi Güncelle
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
</script>
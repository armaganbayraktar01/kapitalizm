<?php
// admin/buildings/create.php
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 fw-bold text-dark mb-0">
            <i class="fas fa-plus-circle text-success me-2"></i> Yeni Tesis Şablonu Ekle
        </h2>
        <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/buildingtype/store'); ?>" method="POST" enctype="multipart/form-data">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2"></i> Tesis Genel Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Bina Adı</label>
                    <input type="text" name="name" class="form-control" placeholder="Örn: Un Değirmeni" required>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kategori</label>
                        <select name="category" class="form-control">
                            <option value="farm">Tarım / Tarla</option>
                            <option value="factory" selected>Fabrika</option>
                            <option value="mine">Maden</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Komplekslik Tipi</label>
                        <select name="complexity_type" class="form-control">
                            <option value="raw">Hammaddesiz (raw)</option>
                            <option value="semi" selected>İşlenmiş / Girdili (semi)</option>
                            <option value="finished">Nihai Ürün (finished)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Birim Tipi</label>
                        <input type="text" name="unit_type" class="form-control" value="Adet">
                    </div>
                </div>

                <!-- ÇOKLU GİRDİLER -->
                <div class="card p-3 mb-3 bg-light border">
                    <h6 class="text-danger fw-bold"><i class="fas fa-sign-in-alt me-1"></i> Tükettiği Hammaddeler / Girdiler</h6>
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <select name="inputs[0][product_id]" class="form-control">
                                <option value="">-- Hammadde Seçin (Buğday vb.) --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>"><?= htmlspecialchars($product['name']); ?> (<?= $product['type']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="inputs[0][amount]" class="form-control" placeholder="Miktar (Örn: 2)">
                        </div>
                    </div>
                </div>

                <!-- ÇOKLU ÇIKTILAR -->
                <div class="card p-3 mb-3 bg-light border">
                    <h6 class="text-success fw-bold"><i class="fas fa-sign-out-alt me-1"></i> Ürettiği Ürünler / Çıktılar</h6>
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <select name="outputs[0][product_id]" class="form-control">
                                <option value="">-- Ürün Seçin --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>"><?= htmlspecialchars($product['name']); ?> (<?= $product['type']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="outputs[0][amount]" class="form-control" placeholder="Miktar" value="1">
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Kurulum Maliyeti (Base Cost)</label>
                        <input type="number" step="0.01" name="base_cost" class="form-control" value="50000.00" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tesis Görseli Yükle</label>
                    <input type="file" name="image" class="form-control">
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 pb-5">
            <button type="submit" class="btn btn-success px-4 py-2 fw-bold shadow-sm">Tesis Şablonunu Kaydet</button>
            <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-secondary px-4 py-2">İptal</a>
        </div>
    </form>
</div>
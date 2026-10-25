<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-industry me-2 text-primary"></i>Tesis Şablonları & Seviye Matrisleri</h1>
        <a href="<?= base_url('admin/buildingtype/create'); ?>" class="btn btn-primary fw-bold">
            <i class="fas fa-plus me-1"></i> Yeni Tesis Türü Ekle
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">Görsel</th>
                            <th>ID</th>
                            <th>Tesis Adı</th>
                            <th>Kategori / Kompleks</th>
                            <th>Ürettiği Ürün</th>
                            <th>Taban Maliyet</th>
                            <th>Tüketim (Enerji / Su / İşgücü)</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($buildings)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Henüz kayıtlı bir tesis şablonu bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($buildings as $b): ?>
                                <tr>
                                    <td>
                                        <img src="<?= base_url('public/uploads/buildings/' . ($b['image'] ?: 'default.png')); ?>" alt="" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;">
                                    </td>
                                    <td><?= $b['id']; ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($b['name']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($b['category']); ?></span>
                                        <span class="badge bg-dark text-uppercase"><?= htmlspecialchars($b['complexity_type']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($b['product_name'])): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= base_url('public/uploads/products/' . ($b['product_image'] ?: 'default.png')); ?>" alt="" class="rounded border" style="width: 32px; height: 32px; object-fit: cover;">
                                                <div>
                                                    <span class="text-success fw-bold"><?= htmlspecialchars($b['product_name']); ?></span>
                                                    <br><small class="text-muted text-uppercase" style="font-size: 10px;"><?= $b['product_type']; ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Ürün Tanımlı Değil</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($b['base_cost'], 2); ?> TL</td>
                                    <td>
                                        <div class="small text-muted">
                                            <span class="text-warning"><i class="fas fa-bolt"></i> <?= $b['energy_consumption']; ?></span> | 
                                            <span class="text-info"><i class="fas fa-tint"></i> <?= $b['water_consumption']; ?></span> | 
                                            <span class="text-secondary"><i class="fas fa-users"></i> <?= $b['labor_required']; ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= base_url('admin/buildingtype/edit/' . $b['id']); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= base_url('admin/buildingtype/delete/' . $b['id']); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu tesis şablonunu silmek istediğinize emin misiniz?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
// admin/buildings/index.php
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-industry me-2 text-primary"></i>Tesis Şablonları & Reçete Matrisleri</h1>
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
                            <th>Tükettiği Girdiler (Hammaddeler)</th>
                            <th>Ürettiği Çıktılar (Ürünler)</th>
                            <th>Taban Maliyet</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($buildings)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Henüz kayıtlı bir tesis şablonu bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($buildings as$b): ?>
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
                                        <?php if (!empty($b['inputs'])): ?>
                                            <div class="small text-danger">
                                                <?php foreach ($b['inputs'] as$in): ?>
                                                    <div><i class="fas fa-arrow-right me-1"></i> <?= htmlspecialchars($in['product_name']); ?>: <strong><?=$in['amount']; ?></strong></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic small">Girdi Yok (Tarla/Maden)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($b['outputs'])): ?>
                                            <div class="small text-success">
                                                <?php foreach ($b['outputs'] as$out): ?>
                                                    <div><i class="fas fa-arrow-left me-1"></i> <?= htmlspecialchars($out['product_name']); ?>: <strong><?=$out['amount']; ?></strong></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic small">Ürün Tanımlı Değil</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($b['base_cost'], 2); ?> TL</td>
                                    <td class="text-end">
                                        <a href="<?= base_url('admin/buildingtype/edit/' . $b['id']); ?>" class="btn btn-outline-primary btn-sm" title="Düzenle & Reçete Ayarla">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= base_url('admin/buildingtype/delete/' . $b['id']); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu tesis şablonunu silmek istediğinize emin misiniz?');" title="Sil">
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
<?php
// admin/buildings/index.php
?>
<div class="container-fluid px-4 py-4">
    <!-- Sayfa Başlığı ve Aksiyon Bütünü -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-industry text-primary me-2"></i>Tesis Şablonları & Reçeteler
            </h1>
            <p class="text-muted small mb-0">Sistemdeki üretim tesislerini, hammadde girdilerini ve ürün çıktılarını yönetin.</p>
        </div>
        <a href="<?= base_url('admin/buildingtype/create'); ?>" class="btn btn-primary fw-bold shadow-sm px-3 py-2">
            <i class="fas fa-plus me-2"></i>Yeni Tesis Şablonu Ekle
        </a>
    </div>

    <!-- Tesis Listesi Kartı -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-secondary">
                <i class="fas fa-list me-2"></i>Kayıtlı Tesisler Listesi
            </h5>
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                Toplam: <?= count($buildings); ?> Tesis
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 border-top">
                    <thead class="table-light text-uppercase fs-7 text-secondary">
                        <tr>
                            <th style="width: 70px;" class="text-center">Görsel</th>
                            <th style="width: 50px;">ID</th>
                            <th>Tesis / Şablon Adı</th>
                            <th>Kategori & Komplekslik</th>
                            <th>Girdiler (Hammaddeler)</th>
                            <th>Çıktılar (Ürünler)</th>
                            <th class="text-end">Taban Maliyet</th>
                            <th class="text-center" style="width: 120px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($buildings)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-building-circle-exclamation fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    Henüz kayıtlı bir tesis şablonu bulunmuyor.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($buildings as $b): ?>
                                <tr>
                                    <!-- Görsel -->
                                    <td class="text-center">
                                        <img src="<?= base_url('public/uploads/buildings/' . ($b['image'] ?: 'default.png')); ?>" 
                                             alt="<?= htmlspecialchars($b['name']); ?>" 
                                             class="rounded-3 border shadow-sm object-fit-cover" 
                                             style="width: 48px; height: 48px;">
                                    </td>

                                    <!-- ID -->
                                    <td class="fw-bold text-secondary">#<?= $b['id']; ?></td>

                                    <!-- Tesis Adı & Depolama -->
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($b['name']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-boxes-stacked me-1"></i>
                                            Depo: <span class="badge bg-light text-dark border fw-normal"><?= htmlspecialchars($b['storage_type'] ?? 'raw'); ?></span>
                                        </small>
                                    </td>

                                    <!-- Kategori & Komplekslik -->
                                    <td>
                                        <div class="mb-1">
                                            <?php 
                                                $catName = $categories[$b['category']] ?? $b['category'];
                                            ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                                <i class="fas fa-folder me-1"></i><?= htmlspecialchars($catName); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <?php 
                                                $compKey = $b['complexity_type'];
                                                $compLabel = $compKey;
                                                foreach ($complexityTypes as $ct) {
                                                    if ($ct[0] === $compKey) {
                                                        $compLabel = $ct[0];
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <span class="badge bg-dark-subtle text-dark border border-dark-subtle rounded-pill fs-8">
                                                <?= htmlspecialchars($compLabel); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Girdiler -->
                                    <td>
                                        <?php if (!empty($b['inputs'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($b['inputs'] as $in): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-start py-1 px-2 fw-normal">
                                                        <i class="fas fa-arrow-down me-1"></i>
                                                        <strong><?= htmlspecialchars($in['product_name']); ?></strong> 
                                                        <span class="ms-1 badge bg-danger text-white"><?= number_format($in['amount'], 2); ?></span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border border-dashed fw-normal">
                                                <i class="fas fa-ban me-1"></i>Girdi Yok (SonsuzKaynak)
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Çıktılar -->
                                    <td>
                                        <?php if (!empty($b['outputs'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($b['outputs'] as $out): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle text-start py-1 px-2 fw-normal">
                                                        <i class="fas fa-arrow-up me-1"></i>
                                                        <strong><?= htmlspecialchars($out['product_name']); ?></strong> 
                                                        <span class="ms-1 badge bg-success text-white"><?= number_format($out['amount'], 2); ?></span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic fs-7">Çıktı Tanımlı Değil</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Taban Maliyet -->
                                    <td class="text-end">
                                        <div class="fw-bold text-dark fs-6">
                                            ₺<?= number_format($b['base_cost'], 2, ',', '.'); ?>
                                        </div>
                                        <small class="text-muted">Kurulum Bedeli</small>
                                    </td>

                                    <!-- Aksiyon Butonları -->
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm shadow-sm" role="group">
                                            <a href="<?= base_url('admin/buildingtype/edit/' . $b['id']); ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Düzenle & Reçete Ayarla">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <a href="<?= base_url('admin/buildingtype/delete/' . $b['id']); ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('Bu tesis şablonunu ve bağlı tüm reçetelerini silmek istediğinize emin misiniz?');" 
                                               title="Sil">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
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
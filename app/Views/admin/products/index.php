<div class="container-fluid px-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <div>
            <h1 class="fw-bold m-0"><i class="fas fa-boxes me-2 text-primary"></i> Ürün ve Reçete Yönetimi</h1>
            <p class="text-muted small mb-0">Sistemdeki tüm ham, yarı mamül ve nihai ürünleri, üretim tesislerini ve girdi/çıktı reçetelerini buradan yönetebilirsiniz.</p>
        </div>
        <a href="<?= base_url('admin/product/create'); ?>" class="btn btn-primary fw-bold">
            <i class="fas fa-plus-circle me-1"></i> Yeni Ürün Ekle
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 70px;">Görsel</th>
                            <th>Ürün Adı</th>
                            <th>Ürün Türü</th>
                            <th>Baz Fiyat / Maliyet</th>
                            <th>Üretim Süresi</th>
                            <th>Üreten Tesis</th>
                            <th>Çıktı Miktarı<br><small class="text-info">Çıktı x Üretim Çarpanı</small></th>
                            <th>Üretim Reçetesi</th>
                            <th style="width: 120px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td>
                                        <img src="<?= base_url('public/uploads/products/' . ($prod['image'] ?: 'default.png')); ?>" 
                                             alt="" class="rounded border shadow-sm" style="width: 45px; height: 45px; object-fit: cover;">
                                    </td>
                                    <td class="fw-bold text-start">
                                        <?= htmlspecialchars($prod['name']); ?>
                                        <br><small class="text-muted font-monospace">ID: #<?= $prod['id']; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-secondary';
                                            $typeText = strtoupper($prod['type']);
                                            if ($prod['type'] === 'raw') { $badgeClass = 'bg-success'; $typeText = 'Hammadde'; }
                                            elseif ($prod['type'] === 'semi') { $badgeClass = 'bg-info text-dark'; $typeText = 'Yarı Mamül'; }
                                            elseif ($prod['type'] === 'finished') { $badgeClass = 'bg-primary'; $typeText = 'Mamül Ürün'; }
                                            elseif ($prod['type'] === 'utility') { $badgeClass = 'bg-warning text-dark'; $typeText = 'Kaynak / Şebeke'; }
                                        ?>
                                        <span class="badge <?= $badgeClass; ?>"><?= $typeText; ?></span>
                                    </td>
                                    <td class="text-start">
                                        <div class="fw-bold text-success"><?= number_format((float)$prod['base_price'], 2); ?> TL</div>
                                        <small class="text-muted">Maliyet: <?= number_format((float)($prod['unit_cost'] ?? 0), 2); ?> TL</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="far fa-clock me-1 text-muted"></i><?= (int)$prod['production_seconds']; ?> sn 
                                            <small class="text-muted">(~<?= number_format($prod['production_seconds'] / 60, 1); ?> dk)</small>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($prod['building_type_name'])): ?>
                                            <span class="badge bg-dark">
                                                <i class="fas fa-industry me-1"></i><?= htmlspecialchars($prod['building_type_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small"><em>Bağlantı Yok</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($prod['building_type_id'])): ?>
                                            <span class="fw-bold text-primary"><?= (float)($prod['output_amount'] ?? 1.0); ?></span> 
                                            <span class="text-muted">×</span> 
                                            <span class="fw-bold text-info"><?= (float)($prod['production_rate'] ?? 1.0); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-start">
                                        <?php if (!empty($prod['inputs'])): ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($prod['inputs'] as $input): ?>
                                                    <span class="badge bg-light text-dark border d-flex align-items-center gap-1 py-1">
                                                        <span><?= htmlspecialchars($input['input_product_name']); ?>:</span>
                                                        <strong class="text-danger"><?= (float)$input['amount']; ?></strong>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small"><em>Girdisiz (Doğal Üretim / Tarla vb.)</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('admin/product/edit/' . $prod['id']); ?>" class="btn btn-outline-warning" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= base_url('admin/product/delete/' . $prod['id']); ?>" class="btn btn-outline-danger" title="Sil" onclick="return confirm('Bu ürünü ve bağlı reçete matrislerini silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-box-open fa-3x mb-3 text-secondary"></i>
                                    <p class="mb-0">Henüz kayıtlı bir ürün bulunmuyor. Yukarıdaki "Yeni Ürün Ekle" butonunu kullanarak ilk ürünü tanımlayın.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
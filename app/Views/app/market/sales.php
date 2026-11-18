<?php
// Notification / Flash mesajları kontrolü
$successMsg = $_SESSION['success_message'] ?? null;
$errorMsg = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="content-wrapper p-4">
    <!-- Başlık ve Bildirimler -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-store me-2"></i>Perakende Satış ve Kontrat Yönetimi</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContractModal">
            <i class="fas fa-file-contract me-1"></i> Yeni Otomatik Kontrat Kur
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- 1. MARKETLERİM VE RAFLARIM -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-shopping-basket me-2"></i>Marketlerim & Raf Stokları</h5>
                    <span class="badge bg-info"><?= count($stores) ?> Mağaza</span>
                </div>
                <div class="card-body">
                    <?php if (empty($stores)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-store-slash fa-3x mb-3"></i>
                            <p>Henüz aktif bir perakende mağazanız veya marketiniz bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stores as $store): ?>
                            <div class="card border mb-3">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($store['store_name']) ?></strong>
                                        <span class="badge bg-secondary ms-2"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($store['province_name']) ?></span>
                                    </div>
                                    <small class="text-muted">Kasa: <?= number_format($store['cash'] ?? 0, 2) ?> ₺</small>
                                </div>
                                <div class="card-body p-2">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Ürün</th>
                                                    <th>Raf Stoğu</th>
                                                    <th>Birim Maliyet</th>
                                                    <th>Son Satış Döngüsü</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($store['inventory'])): ?>
                                                    <tr><td colspan="4" class="text-center text-muted">Rafta ürün bulunmuyor.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($store['inventory'] as $inv): ?>
                                                        <tr>
                                                            <td>
                                                                <img src="/assets/images/products/<?= htmlspecialchars($inv['product_image'] ?? 'default.png') ?>" width="24" height="24" class="me-1">
                                                                <?= htmlspecialchars($inv['product_name']) ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-success"><?= number_format($inv['amount'], 2) ?> <?= htmlspecialchars($inv['unit_type'] ?? 'Adet') ?></span>
                                                            </td>
                                                            <td><?= number_format($inv['unit_cost'] ?? 0, 2) ?> ₺</td>
                                                            <td>
                                                                <?php 
                                                                    $diff = $now - ($inv['last_sales_time'] ?? 0);
                                                                    echo ($inv['last_sales_time'] > 0) ? $diff . " sn önce" : "Bekliyor";
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. AKTİF OTOMATİK SÖZLEŞMELER -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-sync-alt me-2"></i>Aktif Otomatik Kontratlar</h5>
                    <span class="badge bg-warning text-dark"><?= count($contracts) ?> Kontrat</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($contracts)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-invoice fa-3x mb-3"></i>
                            <p>Taraf olduğunuz aktif bir alım/satım sözleşmesi yok.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($contracts as $contract): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h6 class="mb-1 fw-bold">
                                            <i class="fas fa-box me-1"></i><?= htmlspecialchars($contract['product_name']) ?>
                                        </h6>
                                        <small class="text-primary fw-bold">Her <?= round(($contract['interval_seconds'] ?? 3600) / 60) ?> Dakikada Bir</small>
                                    </div>
                                    <p class="mb-1 text-muted small">
                                        Mağaza: <strong><?= htmlspecialchars($contract['store_name']) ?></strong><br>
                                        Çekilen Miktar: <strong><?= number_format($contract['amount_per_cycle'], 2) ?> Adet/Periyot</strong>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="badge bg-info text-dark">
                                            Son İşlem: <?= ($contract['last_run_time'] > 0) ? ($now - $contract['last_run_time']) . " sn önce" : "Yeni" ?>
                                        </span>
                                        <small class="text-success"><i class="fas fa-check-circle"></i> Otomatik Aktif</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- OTOMATİK KONTRAT OLUŞTURMA MODAL -->
<div class="modal fade" id="createContractModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/manager/app/playerbuilding/createAutoContract" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-handshake me-1"></i> Yeni Otomatik Alım Kontratı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-content-body p-3">
                    <div class="mb-3">
                        <label class="form-label">Hedef Perakende Mağazası</label>
                        <select name="store_id" class="form-select" required>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?= $store['id'] ?>"><?= htmlspecialchars($store['store_name']) ?> (<?= htmlspecialchars($store['province_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tedarik Edilecek Ürün ID</label>
                        <input type="number" name="product_id" class="form-control" placeholder="Örn: 5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Her Döngüde Çekilecek Miktar</label>
                        <input type="number" step="0.01" name="amount_per_cycle" class="form-control" placeholder="100.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yenileme Aralığı (Saniye)</label>
                        <select name="interval_seconds" class="form-select">
                            <option value="900">15 Dakikada Bir (900 sn)</option>
                            <option value="1800" selected>30 Dakikada Bir (1800 sn)</option>
                            <option value="3600">1 Saat (3600 sn)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kontratı Başlat</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="container-fluid px-4 py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-cogs me-2 text-warning"></i> Tesis Yönetimi: <?= htmlspecialchars($playerBuilding['building_name']); ?></h1>
        <a href="<?= base_url('player/buildings'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Tesislerime Dön
        </a>
    </div>

    <form action="<?= base_url('player/buildings/update/' . $playerBuilding['id']); ?>" method="POST">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light fw-bold"><i class="fas fa-info-circle me-1"></i> Tesis Bilgileri ve Durumu</div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <img src="<?= base_url('public/uploads/buildings/' . ($playerBuilding['building_image'] ?: 'default.png')); ?>" alt="" class="rounded border" style="width: 70px; height: 70px; object-fit: cover;">
                            <div>
                                <h4 class="fw-bold mb-1"><?= htmlspecialchars($playerBuilding['building_name']); ?></h4>
                                <span class="badge bg-dark">Seviye <?= $playerBuilding['level']; ?></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Çalışma Durumu</label>
                            <select name="status" class="form-select" required>
                                <option value="active" <?= $playerBuilding['status'] === 'active' ? 'selected' : ''; ?>>Aktif (Üretimde)</option>
                                <option value="paused" <?= $playerBuilding['status'] === 'paused' ? 'selected' : ''; ?>>Durduruldu (Pasif)</option>
                            </select>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="fas fa-save me-1"></i> Durumu Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-warning bg-opacity-25 fw-bold text-dark"><i class="fas fa-level-up-alt me-1"></i> Tesis Seviye Yükseltme</div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <?php if (!empty($nextLevelMatrix)): ?>
                            <div>
                                <p class="text-muted">Bu tesisi bir üst seviyeye taşıyarak üretim hızını artırabilirsiniz.</p>
                                <ul class="list-unstyled small text-muted mb-3">
                                    <li><i class="fas fa-bolt text-warning me-1"></i> Yeni Üretim Hızı: <strong><?= $nextLevelMatrix['production_rate']; ?> Adet</strong></li>
                                    <li><i class="fas fa-coins text-success me-1"></i> Yükseltme Maliyeti: <strong><?= number_format($nextLevelMatrix['upgrade_cost'], 2); ?> TL</strong></li>
                                </ul>
                            </div>
                            <button type="submit" name="action" value="upgrade" class="btn btn-warning fw-bold text-dark w-100 py-2">
                                <i class="fas fa-arrow-up me-1"></i> Seviye Yükselt (Seviye <?= $nextLevelMatrix['level']; ?>)
                            </button>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="mb-0">Bu tesis maksimum seviyededir.</p>
                            </div>
                            <button type="submit" class="btn btn-secondary fw-bold w-100 py-2" disabled>
                                Maksimum Seviye
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
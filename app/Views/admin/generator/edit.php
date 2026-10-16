<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h4 class="m-0 fw-bold text-dark"><i class="fas fa-edit text-warning me-2"></i> <?= $title ?></h4>
                </div>
                <div class="card-body p-4">
                    <form action="<?= base_url('admin/modules/update/' . $module['id']) ?>" method="POST" id="editModuleForm">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Modül Başlığı</label>
                                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($module['title']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">URL / Rota (Sabit)</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($module['url']) ?>" disabled>
                                <small class="text-muted">Dosya yapısı bütünlüğü için URL değiştirilemez.</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6" id="menuIconWrapper">
                                <label class="form-label fw-semibold">İkon Seçimi (FontAwesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white" id="iconPreviewBox">
                                        <i class="<?= htmlspecialchars($module['icon'] ?? 'fas fa-folder') ?>" id="iconPreviewElement"></i>
                                    </span>
                                    <input type="text" name="icon" id="menuIconInput" class="form-control" value="<?= htmlspecialchars($module['icon'] ?? 'fas fa-folder') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Üst Menü (Parent Menu)</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">-- Ana Menü Olsun (Yok) --</option>
                                    <?php if (!empty($parent_modules)): foreach ($parent_modules as $pm): ?>
                                        <option value="<?= $pm['id'] ?>" <?= ($module['parent_id'] == $pm['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pm['title']) ?> (<?= $pm['url'] ?>)
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Durum (Status) Toggle Alanı -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card bg-light border-0 p-3">
                                    <div class="form-check form-switch d-flex align-items-center justify-content-between p-0">
                                        <label class="form-check-label fw-semibold text-dark m-0" for="statusSwitch">
                                            <i class="fas fa-toggle-on text-success me-2"></i> Modül Durumu (Aktif / Yayınla)
                                        </label>
                                        <input class="form-check-input fs-5 m-0" type="checkbox" role="switch" id="statusSwitch" name="status" value="1" <?= (!isset($module['status']) || $module['status'] == 1) ? 'checked' : '' ?> style="cursor: pointer;">
                                    </div>
                                    <small class="text-muted mt-1">Pasif yapıldığında modül menüde gizlenir.</small>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?= base_url('admin/modules') ?>" class="btn btn-secondary">Geri Dön</a>
                            <button type="submit" class="btn btn-warning px-4 fw-bold text-dark">
                                <i class="fas fa-save me-1"></i> Güncellemeleri Kaydet
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // İkon Önizleme Canlı Güncelleme
    document.getElementById('menuIconInput').addEventListener('input', function() {
        let iconClass = this.value.trim();
        let previewElement = document.getElementById('iconPreviewElement');
        previewElement.className = iconClass === '' ? 'fas fa-circle' : iconClass;
    });
</script>
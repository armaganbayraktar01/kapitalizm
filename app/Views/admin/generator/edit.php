<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h4 class="m-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i> Modülü Düzenle</h4>
                </div>
                <div class="card-body p-4">
                    <form action="<?= base_url('admin/modules/update/' . $module['id']) ?>" method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Panelde Görünecek Başlık (Title)</label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($module['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL / Rota (Slug)</label>
                            <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($module['url']) ?>" required>
                        </div> 

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Üst Menü (Parent Menu - Opsiyonel)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- Ana Menü Olsun (Yok) --</option>
                                <?php if (!empty($parentMenus)): foreach ($parentMenus as $pMenu): ?>
                                    <?php if ($pMenu['id'] != $module['id']): ?>
                                        <option value="<?= $pMenu['id'] ?>" <?= ($module['parent_id'] == $pMenu['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pMenu['title']) ?> (<?= $pMenu['url'] ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="menuIconWrapper">
                            <label class="form-label small fw-semibold text-muted">İkon Seçimi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white" id="iconPreviewBox">
                                    <i class="<?= htmlspecialchars($module['icon'] ?? 'fas fa-file-alt') ?>" id="iconPreviewElement"></i>
                                </span>
                                <input type="text" name="icon" id="menuIconInput" class="form-control form-control-sm" value="<?= htmlspecialchars($module['icon'] ?? 'fas fa-file-alt') ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Açılış Hedefi (Target)</label>
                                <select name="target" class="form-select">
                                    <option value="_self" <?= ($module['target'] === '_self') ? 'selected' : '' ?>>Aynı Sekme (_self)</option>
                                    <option value="_blank" <?= ($module['target'] === '_blank') ? 'selected' : '' ?>>Yeni Sekme (_blank)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="status" value="1" id="statusSwitch" <?= ($module['status'] == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="statusSwitch">Modül Aktif / Görünır</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?= base_url('admin/modules') ?>" class="btn btn-secondary">Geri Dön</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-1"></i> Değişiklikleri Kaydet</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('menuIconInput').addEventListener('input', function() {
        let iconClass = this.value.trim();
        let previewElement = document.getElementById('iconPreviewElement');
        previewElement.className = iconClass === '' ? 'fas fa-circle' : iconClass;
    });
</script>
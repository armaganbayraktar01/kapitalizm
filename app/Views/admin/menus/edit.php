<div class="row mb-4">
    <div class="col-12">
        <h2>Menüyü Düzenle</h2>
        <p class="text-muted">Mevcut sidebar bağlantı ayarlarını güncelleyin.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Menü Başlığı</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($menu['title'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL / Rota</label>
                        <input type="text" name="url" class="form-control" value="<?= htmlspecialchars($menu['url'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3" id="menuIconWrapper">
                        <label class="form-label small fw-semibold text-muted">Sidebar İkonu (FontAwesome)</label>
                        <div class="input-group">
                            <!-- Canlı İkon Önizleme Kutusu -->
                            <span class="input-group-text bg-white" id="iconPreviewBox">
                                <i class="<?= !empty($menu['icon']) ? htmlspecialchars($menu['icon']) : 'fas fa-file-alt' ?>" id="iconPreviewElement"></i>
                            </span>
                            <!-- İkon Sınıfı Giriş Alanı -->
                            <input type="text" name="icon" id="menuIconInput" class="form-control form-control-sm" value="<?= htmlspecialchars($menu['icon'] ?? 'fas fa-file-alt') ?>" placeholder="Örn: fas fa-file-alt">
                        </div>
                        <div class="form-text">FontAwesome sınıf adını yazın (örn: <code>fas fa-home</code>, <code>fas fa-wallet</code>, <code>fas fa-users</code>).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Üst Menü (Parent)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-- Ana Menü (Bağlı Yok) --</option>
                            <?php 
                                $menuModel = new \App\Models\Menu();
                                foreach ($menuModel->getParentMenus() as $pMenu): 
                                    // Kendi kendini üst menü seçmesini engellemek için
                                    if (isset($menu['id']) && $pMenu['id'] == $menu['id']) continue;
                            ?>
                                <option value="<?= $pMenu['id'] ?>" <?= (isset($menu['parent_id']) && $menu['parent_id'] == $pMenu['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pMenu['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sıra Numarası</label>
                        <input type="number" name="menu_order" class="form-control" value="<?= htmlspecialchars($menu['menu_order'] ?? 0) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Açılış Hedefi</label>
                        <select name="target" class="form-select">
                            <option value="_self" <?= (isset($menu['target']) && $menu['target'] === '_self') ? 'selected' : '' ?>>Aynı Sekme (_self)</option>
                            <option value="_blank" <?= (isset($menu['target']) && $menu['target'] === '_blank') ? 'selected' : '' ?>>Yeni Sekme (_blank)</option>
                        </select>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="status" class="form-check-input" id="statusCheck" value="1" <?= (isset($menu['status']) && $menu['status'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="statusCheck">Aktif (Sidebar'da Göster)</label>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i> Değişiklikleri Kaydet
                    </button>
                    <a href="<?= base_url('admin/menus') ?>" class="btn btn-secondary px-3">İptal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('menuIconInput').addEventListener('input', function() {
        let iconClass = this.value.trim();
        let previewElement = document.getElementById('iconPreviewElement');
        
        if (iconClass === '') {
            previewElement.className = 'fas fa-circle';
        } else {
            previewElement.className = iconClass;
        }
    });
</script>
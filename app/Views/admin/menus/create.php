<div class="row mb-4">
    <div class="col-12">
        <h2>Yeni Menü Ekle</h2>
        <p class="text-muted">Sidebar'a yeni bir bağlantı ekleyin.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="<?= base_url('admin/menus/create') ?>" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Menü Başlığı</label>
                        <input type="text" name="title" class="form-control" placeholder="Örn: Kullanıcılar" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL / Rota</label>
                        <input type="text" name="url" class="form-control" placeholder="Örn: admin/users" required>
                    </div>
                    <div class="mb-3" id="menuIconWrapper">
                        <label class="form-label small fw-semibold text-muted">Sidebar İkonu (FontAwesome)</label>
                        <div class="input-group">
                            <!-- Canlı İkon Önizleme Kutusu -->
                            <span class="input-group-text bg-white" id="iconPreviewBox">
                                <i class="fas fa-file-alt" id="iconPreviewElement"></i>
                            </span>
                            <!-- İkon Sınıfı Giriş Alanı -->
                            <input type="text" name="menu_icon" id="menuIconInput" class="form-control form-control-sm" value="fas fa-file-alt" placeholder="Örn: fas fa-file-alt">
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
                            ?>
                                <option value="<?= $pMenu['id'] ?>"><?= htmlspecialchars($pMenu['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Eğer bu menüyü başka bir menünün altında açılır (dropdown) olarak göstermek istiyorsanız seçin.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sıra Numarası</label>
                        <input type="number" name="menu_order" class="form-control" value="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Açılış Hedefi</label>
                        <select name="target" class="form-select">
                            <option value="_self">Aynı Sekme (_self)</option>
                            <option value="_blank">Yeni Sekme (_blank)</option>
                        </select>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="status" class="form-check-input" id="statusCheck" value="1" checked>
                        <label class="form-check-label fw-semibold" for="statusCheck">Aktif (Sidebar'da Göster)</label>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i> Menüyü Kaydet
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
        
        // Eğer boş bırakılırsa varsayılan ikon gösterelim
        if (iconClass === '') {
            previewElement.className = 'fas fa-circle';
        } else {
            previewElement.className = iconClass;
        }
    });
</script>
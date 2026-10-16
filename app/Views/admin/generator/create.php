<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h4 class="m-0 fw-bold text-primary"><i class="fas fa-magic me-2"></i> Akıllı Modül & Dosya Üreticisi (RAD Generator)</h4>
                </div>
                <div class="card-body p-4">
                    <form action="<?= base_url('admin/modules/store') ?>" method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Modül Adı (İngilizce / Sınıf Adı Formatında)</label>
                            <input type="text" name="module_name" class="form-control" placeholder="Örn: Budget, CreditCard, Expense" required>
                            <div class="form-text">Bu isimle otomatik Controller, Model ve View klasörü üretilecektir.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Panelde Görünecek Başlık (Title)</label>
                            <input type="text" name="title" class="form-control" placeholder="Örn: Bütçe Yönetimi" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL / Rota (Slug)</label>
                            <input type="text" name="slug" class="form-control" placeholder="Örn: admin/budget">
                            <div class="form-text">Boş bırakılırsa modül adına göre otomatik oluşturulur.</div>
                        </div> 

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Üst Menü (Parent Menu - Opsiyonel)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- Ana Menü Olsun (Yok) --</option>
                                <?php if (!empty($parentMenus)): foreach ($parentMenus as $menu): ?>
                                    <option value="<?= $menu['id'] ?>"><?= htmlspecialchars($menu['title']) ?> (<?= $menu['url'] ?>)</option>
                                <?php endforeach; endif; ?>
                            </select>
                            <div class="form-text">Eğer başka bir modülün altında alt menü olacaksa buradan üst menüyü seçebilirsiniz.</div>
                        </div>

                        <div class="mb-3" id="menuIconWrapper">
                            <label class="form-label small fw-semibold text-muted">İkon Seçimi</label>
                            <div class="input-group">
                                <!-- Canlı İkon Önizleme Kutusu -->
                                <span class="input-group-text bg-white" id="iconPreviewBox">
                                    <i class="fas fa-file-alt" id="iconPreviewElement"></i>
                                </span>
                                <!-- İkon Sınıfı Giriş Alanı -->
                                <input type="text" name="icon" id="menuIconInput" class="form-control form-control-sm" value="fas fa-file-alt" placeholder="Örn: fas fa-file-alt">
                            </div>
                            <div class="form-text">FontAwesome sınıf adını yazın (örn: <code>fas fa-home</code>, <code>fas fa-wallet</code>, <code>fas fa-users</code>).</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Açılış Hedefi (Target)</label>
                                <select name="target" class="form-select">
                                    <option value="_self" selected>Aynı Sekme (_self)</option>
                                    <option value="_blank">Yeni Sekme (_blank)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="status" value="1" id="statusSwitch" checked>
                                    <label class="form-check-label fw-semibold" for="statusSwitch">Modül Aktif / Görünür</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?= base_url('admin/modules') ?>" class="btn btn-secondary">Geri Dön</a>
                            <button type="submit" class="btn btn-success px-4 fw-bold"><i class="fas fa-cogs me-1"></i> Modülü ve Dosyaları Otomatik Üret</button>
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
        
        if (iconClass === '') {
            previewElement.className = 'fas fa-circle';
        } else {
            previewElement.className = iconClass;
        }
    });
</script>
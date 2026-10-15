<div class="row mb-4">
    <div class="col-12">
        <h2>Sayfayı Düzenle</h2>
        <p class="text-muted">Mevcut sayfa özelliklerini ve içerik veya modül eşlemelerini güncelleyin.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sayfa Başlığı</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($page['title'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL Slug</label>
                        <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($page['slug'] ?? '') ?>" required>
                        <div class="form-text">Tarayıcı adres çubuğunda görünecek benzersiz yol.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hangi Menüye Bağlı?</label>
                        <select name="menu_id" class="form-select">
                            <option value="">-- Bağlantısız / Bağımsız Sayfa --</option>
                            <?php foreach ($menus as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($page['menu_id'] == $m['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sayfa Tipi</label>
                        <select name="page_type" id="pageTypeSelect" class="form-select" required>
                            <option value="static" <?= ($page['page_type'] === 'static') ? 'selected' : '' ?>>Statik / Metin Sayfası (Düz İçerik)</option>
                            <option value="controller" <?= ($page['page_type'] === 'controller') ? 'selected' : '' ?>>Modüler / Özel Controller Sayfası (Uygulama)</option>
                        </select>
                    </div>

                    <!-- Statik İçerik Alanı -->
                    <div class="mb-3 page-type-section <?= ($page['page_type'] === 'controller') ? 'd-none' : '' ?>" id="staticSection">
                        <label class="form-label fw-semibold">Sayfa İçeriği (HTML / Metin)</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Sayfa içeriğini buraya yazabilirsiniz..."><?= htmlspecialchars($page['content'] ?? '') ?></textarea>
                    </div>

                    <!-- Controller / Modül Alanı -->
                    <div class="mb-3 page-type-section <?= ($page['page_type'] === 'static') ? 'd-none' : '' ?>" id="controllerSection">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Controller Adı</label>
                                <input type="text" name="controller_name" class="form-control" value="<?= htmlspecialchars($page['controller_name'] ?? '') ?>" placeholder="Örn: ExpenseController">
                                <div class="form-text">App\Controllers\ altında yer almalıdır.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Metod (Action) Adı</label>
                                <input type="text" name="action_name" class="form-control" value="<?= htmlspecialchars($page['action_name'] ?? 'index') ?>" placeholder="Örn: index">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="status" class="form-check-input" id="statusCheck" value="1" <?= ($page['status'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="statusCheck">Aktif</label>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i> Güncellemeleri Kaydet
                    </button>
                    <a href="<?= base_url('admin/pages') ?>" class="btn btn-secondary px-3">İptal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tip Değişimine Göre Alanları Göster/Gizle -->
<script>
document.getElementById('pageTypeSelect').addEventListener('change', function() {
    if (this.value === 'static') {
        document.getElementById('staticSection').classList.remove('d-none');
        document.getElementById('controllerSection').classList.add('d-none');
    } else {
        document.getElementById('staticSection').classList.add('d-none');
        document.getElementById('controllerSection').classList.remove('d-none');
    }
});
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
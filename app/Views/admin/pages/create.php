<div class="row mb-4">
    <div class="col-12">
        <h2>Yeni Sayfa Oluştur</h2>
        <p class="text-muted">Statik bir içerik sayfası ya da özel bir Controller modülü tanımlayın ve otomatik olarak menüye ekleyin.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sayfa Başlığı</label>
                        <input type="text" name="title" class="form-control" placeholder="Örn: Faturalar" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL Slug</label>
                        <input type="text" name="slug" class="form-control" placeholder="Örn: ev-islemleri/faturalar" required>
                        <div class="form-text">Tarayıcı adres çubuğunda görünecek benzersiz yol.</div>
                    </div>

                    <!-- Otomatik Menü Seçeneği -->
                    <div class="card bg-light border-0 p-3 mb-3">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="add_to_menu" class="form-check-input" id="addToMenuCheck" value="1" checked>
                            <label class="form-check-label fw-semibold text-dark" for="addToMenuCheck">
                                Bu sayfayı sol sidebar menüsüne otomatik ekle
                            </label>
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
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sayfa Tipi</label>
                        <select name="page_type" id="pageTypeSelect" class="form-select" required>
                            <option value="static">Statik / Metin Sayfası (Düz İçerik)</option>
                            <option value="controller">Modüler / Özel Controller Sayfası (Uygulama)</option>
                        </select>
                    </div>

                    <!-- Statik İçerik Alanı -->
                    <div class="mb-3 page-type-section" id="staticSection">
                        <label class="form-label fw-semibold">Sayfa İçeriği (HTML / Metin)</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Sayfa içeriğini buraya yazabilirsiniz..."></textarea>
                    </div>

                    <!-- Controller / Modül Alanı -->
                    <div class="mb-3 page-type-section d-none" id="controllerSection">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Controller Adı</label>
                                <input type="text" name="controller_name" class="form-control" placeholder="Örn: ExpenseController">
                                <div class="form-text">App\Controllers\ altında yer almalıdır.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Metod (Action) Adı</label>
                                <input type="text" name="action_name" class="form-control" value="index" placeholder="Örn: index">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="status" class="form-check-input" id="statusCheck" value="1" checked>
                        <label class="form-check-label fw-semibold" for="statusCheck">Aktif</label>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i> Sayfayı ve Menüyü Kaydet
                    </button>
                    <a href="<?= base_url('admin/pages') ?>" class="btn btn-secondary px-3">İptal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Menüye ekle seçeneğine göre ikon alanını göster/gizle
    document.getElementById('addToMenuCheck').addEventListener('change', function() {
        document.getElementById('menuIconWrapper').style.display = this.checked ? 'block' : 'none';
    });

    // Sayfa tipine göre alanları değiştir
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
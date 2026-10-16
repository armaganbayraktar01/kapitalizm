<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h4 class="m-0 fw-bold text-primary"><i class="fas fa-magic me-2"></i> Yeni Modül Oluştur (Generator)</h4>
                </div>
                <div class="card-body p-4">
                    <form action="<?= base_url('admin/generator/store') ?>" method="POST" id="moduleForm">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Modül Adı (İngilizce / Tekil - Örn: Product, Expense)</label>
                                <input type="text" name="module_name" class="form-control" placeholder="Product" required>
                                <div class="form-text">Controller, Model ve Veritabanı tablosu bu isme göre otomatik türetilir.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Panelde Görünecek Başlık (Title)</label>
                                <input type="text" name="title" class="form-control" placeholder="Ürünler Yönetimi" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">URL / Rota (Slug)</label>
                                <input type="text" name="slug" class="form-control" placeholder="admin/products">
                                <div class="form-text">Boş bırakılırsa otomatik türetilir.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Üst Menü (Parent Menu - Opsiyonel)</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">-- Ana Menü Olsun (Yok) --</option>
                                    <?php if (!empty($parentMenus)): foreach ($parentMenus as$menu): ?>
                                        <option value="<?= $menu['id'] ?>"><?= htmlspecialchars($menu['title']) ?> (<?=$menu['url'] ?>)</option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6" id="menuIconWrapper">
                                <label class="form-label fw-semibold">İkon Seçimi (FontAwesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white" id="iconPreviewBox">
                                        <i class="fas fa-folder" id="iconPreviewElement"></i>
                                    </span>
                                    <input type="text" name="icon" id="menuIconInput" class="form-control" value="fas fa-">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Modül Şablon Tipi</label>
                                <select name="template_type" class="form-select">
                                    <option value="crud">Standart CRUD Modülü (Tablo + CRUD)</option>
                                    <option value="report">Rapor / İstatistik Modülü</option>
                                    <option value="blank">Boş / Özel Modül</option>
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
                                        <input class="form-check-input fs-5 m-0" type="checkbox" role="switch" id="statusSwitch" name="status" value="1" checked style="cursor: pointer;">
                                    </div>
                                    <small class="text-muted mt-1">Pasif yapıldığında modül menüde gizlenir veya erişimi kısıtlanır.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Dinamik Form Alanları (Dynamic Form Builder) -->
                        <div class="card mb-4 border bg-light">
                            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-columns me-2"></i> Ekstra Tablo Sütunları & Form Alanları</h6>
                                <button type="button" class="btn btn-sm btn-outline-success" id="addFieldBtn">
                                    <i class="fas fa-plus me-1"></i> Alan Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="dynamicFieldsContainer">
                                    <!-- Dinamik alan satırları buraya eklenecek -->
                                </div>
                                <div class="form-text text-muted">Standart olarak her modülde `id`, `title`, `description`, `status` ve `created_at` alanları otomatik oluşturulur. Buradan ek sütunlar ekleyebilirsiniz.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?= base_url('admin/modules') ?>" class="btn btn-secondary">Geri Dön</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold" id="submitBtn">
                                <i class="fas fa-magic me-1"></i> Modülü ve Dosyaları Üret
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

    // Dinamik Alan Ekleme Fonksiyonu
    document.getElementById('addFieldBtn').addEventListener('click', function() {
        let container = document.getElementById('dynamicFieldsContainer');
        let index = container.children.length;
        
        let html = `
            <div class="row g-2 mb-3 align-items-center dynamic-field-row">
                <div class="col-md-4">
                    <input type="text" name="fields[${index}][name]" class="form-control form-control-sm" placeholder="Sütun Adı (Örn: price, stock)" required>
                </div>
                <div class="col-md-4">
                    <select name="fields[${index}][type]" class="form-select form-select-sm">
                        <option value="VARCHAR(255)">Metin (VARCHAR)</option>
                        <option value="TEXT">Uzun Metin (TEXT)</option>
                        <option value="INT">Sayı (INT)</option>
                        <option value="DECIMAL(10,2)">Ondalık / Fiyat (DECIMAL)</option>
                        <option value="DATE">Tarih (DATE)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="fields[${index}][label]" class="form-control form-control-sm" placeholder="Etiket (Örn: Fiyat)" required>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-field-btn"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    });

    // Dinamik Alan Silme ve Çift Gönderim Koruması
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-field-btn')) {
            e.target.closest('.dynamic-field-row').remove();
        }
    });

    document.getElementById('moduleForm').addEventListener('submit', function() {
        let btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Üretiliyor...';
    });
</script>
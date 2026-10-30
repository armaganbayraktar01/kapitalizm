<?php
// admin/buildings/create.php
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-plus-circle text-success me-2"></i>Yeni Tesis Şablonu Ekle
            </h1>
            <p class="text-muted small mb-0">Sistem için yeni bir üretim tesisi ve reçete kurallarını tanımlayın.</p>
        </div>
        <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-outline-secondary fw-bold shadow-sm px-3 py-2">
            <i class="fas fa-arrow-left me-2"></i>Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/buildingtype/store'); ?>" method="POST" enctype="multipart/form-data" id="buildingCreateForm">
        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-info-circle text-primary me-2"></i>Tesis Genel Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Bina / Tesis Adı <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="Örn: Un Değirmeni" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Kategori <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <?php foreach ($categories as $catKey => $catName): ?>
                                        <option value="<?= $catKey; ?>" <?= $catKey === 'factories' ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($catName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Depolama Tipi</label>
                                <select name="storage_type" class="form-select">
                                    <option value="raw" selected>Hammadde Deposu (raw)</option>
                                    <option value="liquid">Sıvı / Tank Deposu (liquid)</option>
                                    <option value="cold">Soğuk Hava Deposu (cold)</option>
                                    <option value="finished">Nihai Ürün Deposu (finished)</option>
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-bold">Komplekslik Tipi & Reçete Kuralı <span class="text-danger">*</span></label>
                                <select name="complexity_type" id="complexityTypeSelect" class="form-select fw-semibold text-primary" required>
                                    <?php foreach ($complexityTypes as $cKey => $cVal): ?>
                                        <option value="<?= $cVal[0]; ?>" 
                                                data-min-in="<?= $cVal[2]; ?>" 
                                                data-max-in="<?= $cVal[3]; ?>" 
                                                data-min-out="<?= $cVal[4]; ?>" 
                                                data-max-out="<?= $cVal[5]; ?>"
                                                <?= $cVal[0] === 'two_in_one_out' ? 'selected' : ''; ?>>
                                            <?= $cVal[0]; ?> - <?= $cVal[1]; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Birim Tipi</label>
                                <input type="text" name="unit_type" class="form-control" value="Adet">
                            </div>
                        </div>

                        <div class="alert alert-info border-0 mt-3 mb-0 d-flex align-items-center" id="complexityInfoBox">
                            <i class="fas fa-lightbulb fa-2x me-3 text-info"></i>
                            <div>
                                <strong id="complexityRuleTitle">2 Girdi ➔ 1 Çıktı</strong>
                                <div class="small" id="complexityRuleDesc">Bu tesis şablonu için reçete tanımlarken tam olarak 2 girdi ve 1 çıktı seçilmelidir.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-vials text-warning me-2"></i>Üretim Reçetesi (Girdiler & Çıktılar)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-danger fw-bold mb-0"><i class="fas fa-arrow-down me-1"></i> Tükettiği Hammaddeler</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="addInputBtn"><i class="fas fa-plus me-1"></i> Girdi Ekle</button>
                            </div>
                            <div id="inputsContainer" class="d-flex flex-column gap-2"></div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-success fw-bold mb-0"><i class="fas fa-arrow-up me-1"></i> Ürettiği Ürünler</h6>
                                <button type="button" class="btn btn-sm btn-outline-success" id="addOutputBtn"><i class="fas fa-plus me-1"></i> Çıktı Ekle</button>
                            </div>
                            <div id="outputsContainer" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-coins text-warning me-2"></i>Maliyet & Kilit</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Kurulum Maliyeti (₺)</label>
                            <input type="number" step="0.01" name="base_cost" class="form-control fw-bold" value="50000.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Gerekli Oyun Skoru</label>
                            <input type="number" name="required_score" class="form-control" value="0">
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-image text-info me-2"></i>Görsel Yükle</h5>
                    </div>
                    <div class="card-body">
                        <input type="file" name="image" id="imageInput" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm py-3"><i class="fas fa-save me-2"></i>Tesis Şablonunu Kaydet</button>
                    <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-light border py-2">İptal</a>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const products = <?= json_encode($products ?? []); ?>;
    const complexitySelect = document.getElementById('complexityTypeSelect');
    const inputsContainer = document.getElementById('inputsContainer');
    const outputsContainer = document.getElementById('outputsContainer');

    function getProductOptionsHtml() {
        let options = '<option value="">-- Ürün / Hammadde Seçin --</option>';
        products.forEach(p => { options += `<option value="${p.id}">${p.name} (${p.unit_type ?? 'Adet'})</option>`; });
        return options;
    }

    function addInputRow(productId = '', amount = '') {
        const index = inputsContainer.children.length;
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center input-row';
        div.innerHTML = `
            <div class="col-8"><select name="inputs[${index}][product_id]" class="form-select form-select-sm">${getProductOptionsHtml()}</select></div>
            <div class="col-3"><input type="number" step="0.01" name="inputs[${index}][amount]" class="form-control form-control-sm" placeholder="Miktar" value="${amount}"></div>
            <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="fas fa-times"></i></button></div>
        `;
        if (productId) div.querySelector('select').value = productId;
        inputsContainer.appendChild(div);
    }

    function addOutputRow(productId = '', amount = '1') {
        const index = outputsContainer.children.length;
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center output-row';
        div.innerHTML = `
            <div class="col-8"><select name="outputs[${index}][product_id]" class="form-select form-select-sm">${getProductOptionsHtml()}</select></div>
            <div class="col-3"><input type="number" step="0.01" name="outputs[${index}][amount]" class="form-control form-control-sm" placeholder="Miktar" value="${amount}"></div>
            <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="fas fa-times"></i></button></div>
        `;
        if (productId) div.querySelector('select').value = productId;
        outputsContainer.appendChild(div);
    }

    function syncComplexityRules() {
        const selectedOpt = complexitySelect.options[complexitySelect.selectedIndex];
        const minIn = parseInt(selectedOpt.getAttribute('data-min-in'));
        const maxIn = parseInt(selectedOpt.getAttribute('data-max-in'));
        const minOut = parseInt(selectedOpt.getAttribute('data-min-out'));
        const maxOut = parseInt(selectedOpt.getAttribute('data-max-out'));

        document.getElementById('complexityRuleTitle').innerText = selectedOpt.text;
        document.getElementById('complexityRuleDesc').innerText = 
            `Bu kural gereği en az ${minIn}, en fazla ${maxIn === 99 ? 'Sınırsız' : maxIn} girdi; ` +
            `en az ${minOut}, en fazla ${maxOut === 99 ? 'Sınırsız' : maxOut} çıktı girilmelidir.`;

        inputsContainer.innerHTML = '';
        outputsContainer.innerHTML = '';

        for (let i = 0; i < minIn; i++) addInputRow();
        for (let i = 0; i < minOut; i++) addOutputRow();
    }

    complexitySelect.addEventListener('change', syncComplexityRules);
    document.getElementById('addInputBtn').addEventListener('click', () => addInputRow());
    document.getElementById('addOutputBtn').addEventListener('click', () => addOutputRow());

    document.addEventListener('click', function (e) {
        if (e.target.closest('.remove-row-btn')) {
            e.target.closest('.row').remove();
        }
    });

    syncComplexityRules();
});
</script>
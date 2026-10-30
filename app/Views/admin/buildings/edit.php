<?php
// admin/buildings/edit.php
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-edit text-primary me-2"></i>Tesis Şablonunu Düzenle: <?= htmlspecialchars($building['name'] ?? ''); ?>
            </h1>
            <p class="text-muted small mb-0">Tesis genel parametrelerini, reçetesini ve 1-10 seviye gelişim matrisini güncelleyin.</p>
        </div>
        <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-outline-secondary fw-bold shadow-sm px-3 py-2">
            <i class="fas fa-arrow-left me-2"></i>Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/buildingtype/update/' . $building['id']); ?>" method="POST" enctype="multipart/form-data" id="buildingEditForm">
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
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($building['name'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Kategori <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <?php foreach ($categories as $catKey => $catName): ?>
                                        <option value="<?= $catKey; ?>" <?= (($building['category'] ?? '') === $catKey) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($catName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Depolama Tipi</label>
                                <select name="storage_type" class="form-select">
                                    <option value="raw" <?= (($building['storage_type'] ?? 'raw') === 'raw') ? 'selected' : ''; ?>>Hammadde Deposu (raw)</option>
                                    <option value="liquid" <?= (($building['storage_type'] ?? '') === 'liquid') ? 'selected' : ''; ?>>Sıvı / Tank Deposu (liquid)</option>
                                    <option value="cold" <?= (($building['storage_type'] ?? '') === 'cold') ? 'selected' : ''; ?>>Soğuk Hava Deposu (cold)</option>
                                    <option value="finished" <?= (($building['storage_type'] ?? '') === 'finished') ? 'selected' : ''; ?>>Nihai Ürün Deposu (finished)</option>
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
                                                <?= (($building['complexity_type'] ?? '') === $cVal[0]) ? 'selected' : ''; ?>>
                                            <?= $cVal[0]; ?> - <?= $cVal[1]; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Birim Tipi</label>
                                <input type="text" name="unit_type" class="form-control" value="<?= htmlspecialchars($building['unit_type'] ?? 'Adet'); ?>">
                            </div>
                        </div>

                        <div class="alert alert-info border-0 mt-3 mb-0 d-flex align-items-center" id="complexityInfoBox">
                            <i class="fas fa-lightbulb fa-2x me-3 text-info"></i>
                            <div>
                                <strong id="complexityRuleTitle">Reçete Kuralı</strong>
                                <div class="small" id="complexityRuleDesc">Seçilen komplekslik tipine uygun girdi ve çıktı sayılarını yönetiniz.</div>
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
                                <h6 class="text-danger fw-bold mb-0"><i class="fas fa-arrow-down me-1"></i> Tükettiği Hammaddeler (Girdiler)</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="addInputBtn"><i class="fas fa-plus me-1"></i> Girdi Ekle</button>
                            </div>
                            <div id="inputsContainer" class="d-flex flex-column gap-2"></div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-success fw-bold mb-0"><i class="fas fa-arrow-up me-1"></i> Ürettiği Ürünler (Çıktılar)</h6>
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
                            <input type="number" step="0.01" name="base_cost" class="form-control fw-bold" value="<?= htmlspecialchars($building['base_cost'] ?? '50000.00'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Gerekli Oyun Skoru</label>
                            <input type="number" name="required_score" class="form-control" value="<?= htmlspecialchars($building['required_score'] ?? '0'); ?>">
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-image text-info me-2"></i>Tesis Görseli</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php $currentImg = !empty($building['image']) ? $building['image'] : 'default.png'; ?>
                            <img id="imagePreview" src="<?= base_url('public/uploads/buildings/' . htmlspecialchars($currentImg)); ?>" alt="Tesis Görseli" class="rounded-3 border shadow-sm" style="max-height: 120px; object-fit: cover;">
                        </div>
                        <input type="file" name="image" id="imageInput" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm py-3"><i class="fas fa-save me-2"></i>Değişiklikleri Kaydet</button>
                    <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-light border py-2">İptal</a>
                </div>
            </div>

        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-layer-group me-2"></i>Tesis Seviye Matrisi (Level 1 - 10)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-center small mb-0">
                        <thead class="table-light align-middle">
                            <tr>
                                <th style="width: 70px;">Seviye</th>
                                <th>Gerekli XP</th>
                                <th>Üretim Çarpanı</th>
                                <th>Tüketim Miktarı</th>
                                <th>Yükseltme Bedeli (₺)</th>
                                <th>Bakım Gideri (₺)</th>
                                <th>Enerji (kW)</th>
                                <th>Su (Lt)</th>
                                <th>Personel</th>
                                <th>Aşınma (%)</th>
                                <th>İşçilik Tabanı</th>
                                <th>Taşıma Tabanı</th>
                                <th>Vergi (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $levelMap = [];
                            if (!empty($levels) && is_array($levels)) {
                                foreach ($levels as $l) {
                                    $levelMap[(int)$l['level']] = $l;
                                }
                            }

                            for ($i = 1; $i <= 10; $i++):
                                $curr = $levelMap[$i] ?? [];
                                $lXp     = $curr['required_xp'] ?? ($i === 1 ? 0 : $i * 500);
                                $lProd   = $curr['production_rate'] ?? (8.00 * $i);
                                $lCons   = $curr['consumption_amount'] ?? (2.00 * $i);
                                $lCost   = $curr['upgrade_cost'] ?? (($building['base_cost'] ?? 50000) * $i * 1.5);
                                $lMaint  = $curr['maintenance_cost'] ?? (100.00 * $i);
                                $lEnergy = $curr['energy_consumption'] ?? (50.00 + ($i * 5));
                                $lWater  = $curr['water_consumption'] ?? (10.00 + ($i * 20));
                                $lLabor  = $curr['labor_required'] ?? (10 + ($i * 2));
                                $lWear   = $curr['wear_rate'] ?? 2.00;
                                $lBLabor = $curr['base_labor_cost'] ?? 150.00;
                                $lBTrans = $curr['base_transport_cost'] ?? 50.00;
                                $lBTax   = $curr['base_tax_rate'] ?? 18.00;
                            ?>
                            <tr>
                                <td class="fw-bold bg-light">Lv. <?= $i; ?></td>
                                <td><input type="number" name="levels[<?= $i; ?>][required_xp]" value="<?= htmlspecialchars($lXp); ?>" class="form-control form-control-sm text-center text-success fw-bold" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][production_rate]" value="<?= htmlspecialchars($lProd); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][consumption_amount]" value="<?= htmlspecialchars($lCons); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][upgrade_cost]" value="<?= htmlspecialchars($lCost); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][maintenance_cost]" value="<?= htmlspecialchars($lMaint); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][energy_consumption]" value="<?= htmlspecialchars($lEnergy); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][water_consumption]" value="<?= htmlspecialchars($lWater); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" name="levels[<?= $i; ?>][labor_required]" value="<?= htmlspecialchars($lLabor); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][wear_rate]" value="<?= htmlspecialchars($lWear); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][base_labor_cost]" value="<?= htmlspecialchars($lBLabor); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][base_transport_cost]" value="<?= htmlspecialchars($lBTrans); ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][base_tax_rate]" value="<?= htmlspecialchars($lBTax); ?>" class="form-control form-control-sm text-center" required></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 pb-5">
            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm"><i class="fas fa-save me-2"></i>Değişiklikleri ve Reçeteyi Kaydet</button>
            <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-secondary px-4 py-2">İptal</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const products = <?= json_encode($products ?? []); ?>;
    const existingInputs = <?= json_encode($inputs ?? []); ?>;
    const existingOutputs = <?= json_encode($outputs ?? []); ?>;
    
    const complexitySelect = document.getElementById('complexityTypeSelect');
    const inputsContainer = document.getElementById('inputsContainer');
    const outputsContainer = document.getElementById('outputsContainer');

    function getProductOptionsHtml(selectedProductId = '') {
        let options = '<option value="">-- Ürün / Hammadde Seçin --</option>';
        products.forEach(p => {
            const isSelected = (selectedProductId == p.id) ? 'selected' : '';
            options += `<option value="${p.id}" ${isSelected}>${p.name} (${p.unit_type ?? 'Adet'})</option>`;
        });
        return options;
    }

    function addInputRow(productId = '', amount = '') {
        const index = inputsContainer.children.length;
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center input-row';
        div.innerHTML = `
            <div class="col-8"><select name="inputs[${index}][product_id]" class="form-select form-select-sm">${getProductOptionsHtml(productId)}</select></div>
            <div class="col-3"><input type="number" step="0.01" name="inputs[${index}][amount]" class="form-control form-control-sm" placeholder="Miktar" value="${amount}"></div>
            <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="fas fa-times"></i></button></div>
        `;
        inputsContainer.appendChild(div);
    }

    function addOutputRow(productId = '', amount = '') {
        const index = outputsContainer.children.length;
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center output-row';
        div.innerHTML = `
            <div class="col-8"><select name="outputs[${index}][product_id]" class="form-select form-select-sm">${getProductOptionsHtml(productId)}</select></div>
            <div class="col-3"><input type="number" step="0.01" name="outputs[${index}][amount]" class="form-control form-control-sm" placeholder="Miktar" value="${amount}"></div>
            <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="fas fa-times"></i></button></div>
        `;
        outputsContainer.appendChild(div);
    }

    if (existingInputs.length > 0) {
        existingInputs.forEach(inItem => addInputRow(inItem.product_id, inItem.amount));
    } else {
        addInputRow();
    }

    if (existingOutputs.length > 0) {
        existingOutputs.forEach(outItem => addOutputRow(outItem.product_id, outItem.amount));
    } else {
        addOutputRow();
    }

    document.getElementById('addInputBtn').addEventListener('click', () => addInputRow());
    document.getElementById('addOutputBtn').addEventListener('click', () => addOutputRow());

    document.addEventListener('click', function (e) {
        if (e.target.closest('.remove-row-btn')) {
            e.target.closest('.row').remove();
        }
    });

    function updateComplexityInfo() {
        const selectedOpt = complexitySelect.options[complexitySelect.selectedIndex];
        document.getElementById('complexityRuleTitle').innerText = selectedOpt.text;
        document.getElementById('complexityRuleDesc').innerText = "Seçilen komplekslik tipine uygun girdi/çıktı reçete kombinasyonunu aşağıdan düzenleyebilirsiniz.";
    }

    complexitySelect.addEventListener('change', updateComplexityInfo);
    updateComplexityInfo();
});
</script>
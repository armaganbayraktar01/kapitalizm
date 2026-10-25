<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Tesis Kurulumu - Stratejik Yatırım</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-lg">
                    <div class="card-header bg-dark text-white p-4 d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold"><i class="fas fa-industry text-warning me-2"></i>Stratejik Tesis Kurulum Merkezi</h4>
                        <a href="<?= base_url('app/playerbuilding'); ?>" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Geri Dön
                        </a>
                    </div>
                    
                    <form action="<?= base_url('app/playerbuilding/store'); ?>" method="POST" id="buildingForm">
                        <div class="card-body p-4 bg-light">
                            <div class="row g-4">
                                
                                <!-- SOL: TESİS SEÇİMİ -->
                                <div class="col-lg-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-success text-white fw-semibold">
                                            1. Tesis ve Üretim Şablonu
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Tesis Türü</label>
                                                <select name="building_type_id" id="buildingSelect" class="form-select form-select-lg" required onchange="onBuildingChange(this)">
                                                    <option value="" disabled selected>-- Tesis Seçin --</option>
                                                    <?php foreach ($building_types as $bt): ?>
                                                        <option value="<?= $bt['id']; ?>"
                                                            data-cost="<?= number_format($bt['base_cost'], 2, '.', ''); ?>"
                                                            data-category="<?= htmlspecialchars($bt['category'] ?? 'Genel'); ?>"
                                                            data-rate="<?= (float)($bt['production_rate'] ?? 10); ?>"
                                                            data-laborreq="<?= (int)($bt['labor_required'] ?? 5); ?>"
                                                            data-prodname="<?= htmlspecialchars($bt['product_name'] ?? 'Ürün Yok'); ?>"
                                                            data-prodimg="<?= htmlspecialchars($bt['product_image'] ?? 'default.png'); ?>"
                                                            data-img="<?= htmlspecialchars($bt['image'] ?? 'factory-default.png'); ?>">
                                                            <?= htmlspecialchars($bt['name']); ?> (<?= number_format($bt['base_cost'], 2); ?> TL)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="p-3 bg-white border rounded text-center mt-3">
                                                <div class="row align-items-center">
                                                    <div class="col-6 border-end">
                                                        <small class="text-muted d-block mb-1">Görsel</small>
                                                        <img id="buildingImage" src="<?= base_url('assets/img/buildings/factory-default.png'); ?>" class="img-fluid rounded border p-1" style="max-height: 80px; object-fit: contain;">
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block mb-1">Çıktı</small>
                                                        <img id="productImage" src="<?= base_url('assets/img/products/default.png'); ?>" class="rounded border mb-1" style="width: 35px; height: 35px; object-fit: contain;">
                                                        <div id="productNameLabel" class="fw-bold small">-</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SAĞ: BÖLGE SEÇİMİ VE FİZİBİLİTE -->
                                <div class="col-lg-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-primary text-white fw-semibold">
                                            2. Bölge Seçimi ve Verimlilik
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Kurulacak İl</label>
                                                <select name="province_id" id="provinceSelect" class="form-select form-select-lg" required disabled onchange="calculateFeasibility()">
                                                    <option value="" disabled selected>-- Önce Tesis Seçin --</option>
                                                    <?php foreach ($provinces as $prov): ?>
                                                        <option value="<?= $prov['id']; ?>" 
                                                            data-pop="<?= number_format($prov['population']); ?>"
                                                            data-agri="<?= $prov['agri_multiplier']; ?>"
                                                            data-ind="<?= $prov['ind_multiplier']; ?>"
                                                            data-mine="<?= $prov['mine_multiplier']; ?>"
                                                            data-labor="<?= $prov['labor_cost_multiplier']; ?>">
                                                            <?= htmlspecialchars($prov['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small id="provinceHelpText" class="text-muted">İller verimliliklerine göre renklenecektir.</small>
                                            </div>

                                            <div class="p-3 bg-white border rounded mt-3">
                                                <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Bölgesel Çarpan:</span><span id="lblRegionalMult" class="fw-bold">1.00x</span></div>
                                                <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Maaş Çarpanı:</span><span id="lblLaborMult" class="fw-bold text-danger">1.00x</span></div>
                                                <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Nüfus:</span><span id="lblProvincePop" class="fw-bold">-</span></div>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-success">Nihai Üretim:</span>
                                                    <span id="lblFinalOutput" class="fs-5 fw-bold text-success">0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="card-footer bg-white p-4 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">Maliyet: </span>
                                <span id="lblTotalCost" class="fs-4 fw-bold text-danger">0.00 TL</span>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                                <i class="fas fa-check-circle me-2"></i>Kurulumu Tamamla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentCategory = '';
    let baseProductionRate = 0;

    function onBuildingChange(select) {
        const option = select.options[select.selectedIndex];
        const cost = parseFloat(option.getAttribute('data-cost') || 0);
        baseProductionRate = parseFloat(option.getAttribute('data-rate') || 0);
        currentCategory = (option.getAttribute('data-category') || '').toLowerCase();
        
        document.getElementById('buildingImage').src = '<?= base_url("assets/img/buildings/"); ?>' + (option.getAttribute('data-img') || 'factory-default.png');
        document.getElementById('productImage').src = '<?= base_url("assets/img/products/"); ?>' + (option.getAttribute('data-prodimg') || 'default.png');
        document.getElementById('productNameLabel').innerText = option.getAttribute('data-prodname') || 'Bilinmiyor';
        document.getElementById('lblTotalCost').innerText = cost.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL';

        const provSelect = document.getElementById('provinceSelect');
        provSelect.removeAttribute('disabled');
        document.getElementById('provinceHelpText').innerText = "Yeşil: Yüksek Verim, Sarı: Orta, Pembe: Düşük.";

        colorProvincesByCategory(currentCategory);
        calculateFeasibility();
    }

    function colorProvincesByCategory(category) {
        const provSelect = document.getElementById('provinceSelect');
        for (let i = 1; i < provSelect.options.length; i++) {
            const opt = provSelect.options[i];
            let multiplier = 1.0;
            if (category.includes('agri') || category.includes('tarım')) multiplier = parseFloat(opt.getAttribute('data-agri') || 1.0);
            else if (category.includes('ind') || category.includes('sanayi')) multiplier = parseFloat(opt.getAttribute('data-ind') || 1.0);
            else if (category.includes('mine') || category.includes('maden')) multiplier = parseFloat(opt.getAttribute('data-mine') || 1.0);

            if (multiplier >= 1.6) opt.style.backgroundColor = '#d1e7dd';
            else if (multiplier >= 1.2) opt.style.backgroundColor = '#fff3cd';
            else opt.style.backgroundColor = '#f8d7da';
        }
    }

    function calculateFeasibility() {
        const provSelect = document.getElementById('provinceSelect');
        if (provSelect.selectedIndex <= 0) return;
        const opt = provSelect.options[provSelect.selectedIndex];
        let multiplier = 1.0;

        if (currentCategory.includes('agri') || currentCategory.includes('tarım')) multiplier = parseFloat(opt.getAttribute('data-agri') || 1.0);
        else if (currentCategory.includes('ind') || currentCategory.includes('sanayi')) multiplier = parseFloat(opt.getAttribute('data-ind') || 1.0);
        else if (currentCategory.includes('mine') || currentCategory.includes('maden')) multiplier = parseFloat(opt.getAttribute('data-mine') || 1.0);

        const laborMult = parseFloat(opt.getAttribute('data-labor') || 1.0);
        const pop = opt.getAttribute('data-pop') || '-';
        const finalOutput = baseProductionRate * 1 * multiplier;

        document.getElementById('lblRegionalMult').innerText = multiplier.toFixed(2) + 'x';
        document.getElementById('lblLaborMult').innerText = laborMult.toFixed(2) + 'x';
        document.getElementById('lblProvincePop').innerText = pop + ' kişi';
        document.getElementById('lblFinalOutput').innerText = finalOutput.toFixed(2);
    }
    </script>
</body>
</html>
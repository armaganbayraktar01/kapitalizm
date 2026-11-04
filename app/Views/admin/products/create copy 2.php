<div class="container-fluid px-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-plus-circle me-2 text-primary"></i> Yeni Ürün & Tesis Ekle</h1>
        <a href="<?= base_url('admin/product'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/product/store'); ?>" method="POST" enctype="multipart/form-data">
        
        <!-- BİNA EŞLEŞTİRME VE ÇIKTI / GİRDİ YÖNETİMİ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light fw-bold"><i class="fas fa-industry me-1"></i> Tesis ve Girdi / Çıktı Tanımları (Building Inputs & Outputs)</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Bu Ürünü Üreten Bina Türü</label>
                        <select name="building_type_id" class="form-select" required>
                            <option value="">-- Bina Türü Seçin --</option>
                            <?php foreach ($buildingTypes as $bt): ?>
                                <option value="<?= $bt['id']; ?>"><?= htmlspecialchars($bt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Temel Çıktı Miktarı (Output Amount)</label>
                        <input type="number" step="0.01" name="output_amount" id="outputAmountInput" class="form-control" value="1.00" required min="0.01" oninput="calculateMatrixTable()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Taban Verimlilik Katsayısı (Rate)</label>
                        <input type="number" step="0.1" name="production_rate" id="baseProductionRateInput" class="form-control" value="1.0" required min="0.1" oninput="calculateMatrixTable()">
                    </div>
                </div>

                <hr class="my-4">

                <label class="form-label fw-bold text-secondary mb-3"><i class="fas fa-boxes me-1"></i> Bu Binanın Tükettiği Hammaddeler (Building Inputs)</label>
                <div class="row g-3">
                    <?php foreach ($allProducts as $ap): ?>
                        <div class="col-md-4">
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-dark fw-semibold" style="width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($ap['name']); ?>">
                                    <?= htmlspecialchars($ap['name']); ?>
                                </span>
                                <input type="number" step="0.01" name="building_inputs[<?= $ap['id']; ?>]" class="form-control" placeholder="Miktar" value="" min="0">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TEMEL ÜRÜN BİLGİLERİ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light fw-bold"><i class="fas fa-info-circle me-1"></i> Temel Ürün Bilgileri ve Üretim Parametreleri</div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Ürün Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: Ekmek" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Ürün Türü</label>
                        <select name="type" id="productType" class="form-select" required>
                            <option value="raw">Hammadde (Raw)</option>
                            <option value="semi">Yarı Mamül (Semi)</option>
                            <option value="finished" selected>Mamül Ürün (Finished)</option>
                            <option value="utility">Şebeke / Kaynak (Utility)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Taban Fiyat (TL)</label>
                        <input type="number" step="0.01" name="base_price" class="form-control" value="10.00" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Üretim Süresi (sn)</label>
                        <input type="number" name="production_seconds" id="productionSeconds" class="form-control" value="600" min="1" required oninput="calculateMatrixTable()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Ürün Görseli</label>
                        <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                    </div>
                </div>
            </div>
        </div>

        <!-- GELİŞMİŞ MATRİS VE DARBOĞAZ SİMÜLATÖRÜ (Create Önizlemesi) -->
        <div class="card border-info shadow-sm mb-4" id="matrixSimulatorCard">
            <div class="card-header bg-info text-dark fw-bold">
                <i class="fas fa-calculator me-1"></i> Tesis Seviye Matrisi (Level 1 - 10) & Kapasite / Darboğaz Analizi Önizlemesi
            </div>
            <div class="card-body bg-light">
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Toprak/Bölge Faktörü:</label>
                        <input type="number" id="simSoilMultiplier" class="form-control form-control-sm" value="1.0" step="0.1" min="0.1" oninput="calculateMatrixTable()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Başlangıç Sağlığı (%):</label>
                        <input type="number" id="simStartHealth" class="form-control form-control-sm" value="100.0" step="1" min="50" max="100" oninput="calculateMatrixTable()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Endüstri AR-GE (rInd):</label>
                        <input type="number" id="simRInd" class="form-control form-control-sm" value="1" step="1" min="1" max="10" oninput="calculateMatrixTable()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Teknoloji AR-GE (rTech):</label>
                        <input type="number" id="simRTech" class="form-control form-control-sm" value="1" step="1" min="1" max="10" oninput="calculateMatrixTable()">
                    </div>
                </div>

                <hr class="text-muted">

                <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-chart-line me-1"></i> Seviye Bazlı Net Üretim, Tam Kapasite (%100) ve Ortalama Üretim (%75) Tablosu</h6>
                
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered bg-white align-middle text-center mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th>Seviye (Level)</th>
                                <th>Üretim Süresi</th>
                                <th>Verimlilik ($Rate$)</th>
                                <th>AR-GE Bonusu</th>
                                <th>Net Üretim <br><small class="text-success">Tam Kapasite (%100 Sağlık)</small></th>
                                <th>Net Üretim <br><small class="text-primary">Ortalama Üretim (%75 Sağlık)</small></th>
                                <th>Dakikalık Kapasite ($K$) <br><small class="text-muted">(Ort. %75 Üzerinden)</small></th>
                                <th class="table-warning">Zincir Durumu / Darboğaz</th>
                            </tr>
                        </thead>
                        <tbody id="matrixTableBody">
                            <!-- JavaScript ile dinamik doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-end mb-5">
            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold fs-5">
                <i class="fas fa-save me-2"></i> Ürünü ve Tesis Verilerini Kaydet
            </button>
        </div>
    </form>
</div>

<script>
function calculateMatrixTable() {
    const secInput = document.getElementById('productionSeconds');
    const outputInput = document.getElementById('outputAmountInput');
    const baseRateInput = document.getElementById('baseProductionRateInput');
    
    const productionSec = parseFloat(secInput ? secInput.value : 600) || 600;
    const productionMin = productionSec / 60.0;
    const baseOutputQty = parseFloat(outputInput ? outputInput.value : 1) || 1;
    const baseRate = parseFloat(baseRateInput ? baseRateInput.value : 1.0) || 1.0;

    const soilMultiplier = parseFloat(document.getElementById('simSoilMultiplier').value) || 1.0;
    const startHealth = parseFloat(document.getElementById('simStartHealth').value) || 100.0;
    const rInd = parseInt(document.getElementById('simRInd').value) || 1;
    const rTech = parseInt(document.getElementById('simRTech').value) || 1;

    const rndProdBonus = 1.0 + ((rInd - 1) * 0.03) + ((rTech - 1) * 0.02);
    
    let fullHealthFactor = startHealth / 100.0;
    let avgHealthFactor = 0.75; 

    const tbody = document.getElementById('matrixTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    for (let level = 1; level <= 10; level++) {
        let productionRate = level * baseRate; 

        let netOutputFull = baseOutputQty * productionRate * soilMultiplier * fullHealthFactor * rndProdBonus;
        let netOutputAvg75 = baseOutputQty * productionRate * soilMultiplier * avgHealthFactor * rndProdBonus;

        let K = productionMin > 0 ? (netOutputAvg75 / productionMin) : 0;

        let statusBadge = '';
        if (startHealth < 50.0) {
            statusBadge = '<span class="badge bg-danger">Üretim Durdu (< %50)</span>';
        } else if (K < 0.05) {
            statusBadge = '<span class="badge bg-danger">Çok Yavaş (Darboğaz)</span>';
        } else if (K > 10.0) {
            statusBadge = '<span class="badge bg-warning text-dark">Yüksek Hız (Depo Dolabilir)</span>';
        } else {
            statusBadge = '<span class="badge bg-success">Dengeli Akış</span>';
        }

        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="fw-bold">Level ${level}</td>
            <td>${productionSec} sn <small class="text-muted">(${productionMin.toFixed(1)} dk)</small></td>
            <td class="fw-bold text-info">${productionRate.toFixed(1)}x</td>
            <td>${rndProdBonus.toFixed(2)}x</td>
            <td class="fw-bold text-success">${netOutputFull.toFixed(2)} Adet <br><small class="text-muted">(Sağlık: %${startHealth.toFixed(0)})</small></td>
            <td class="fw-bold text-primary">${netOutputAvg75.toFixed(2)} Adet <br><small class="text-muted">(Ort. Sağlık: %75)</small></td>
            <td class="fw-semibold text-dark">${K.toFixed(2)} Adet/dk</td>
            <td>${statusBadge}</td>
        `;
        tbody.appendChild(tr);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    calculateMatrixTable();

    const outputInput = document.getElementById('outputAmountInput');
    const secInput = document.getElementById('productionSeconds');
    const baseRateInput = document.getElementById('baseProductionRateInput');
    
    if (outputInput) outputInput.addEventListener('input', calculateMatrixTable);
    if (secInput) secInput.addEventListener('input', calculateMatrixTable);
    if (baseRateInput) baseRateInput.addEventListener('input', calculateMatrixTable);
});
</script>
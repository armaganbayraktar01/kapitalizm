<?php
// admin/buildings/edit.php
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 fw-bold text-dark mb-0">
            <i class="fas fa-edit text-primary me-2"></i> Tesis Şablonunu Düzenle: <?= htmlspecialchars($building['name'] ?? ''); ?>
        </h2>
        <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Listeye Dön
        </a>
    </div>

    <form action="<?= base_url('admin/buildingtype/update/' . $building['id']); ?>" method="POST" enctype="multipart/form-data">
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2"></i> Genel Tesis Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Bina Adı</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($building['name'] ?? ''); ?>" required>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kategori</label>
                        <select name="category" class="form-control">
                            <option value="farm" <?= (($building['category'] ?? '') === 'farm') ? 'selected' : ''; ?>>Tarım / Tarla</option>
                            <option value="factory" <?= (($building['category'] ?? '') === 'factory') ? 'selected' : ''; ?>>Fabrika</option>
                            <option value="mine" <?= (($building['category'] ?? '') === 'mine') ? 'selected' : ''; ?>>Maden</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Komplekslik Tipi</label>
                        <select name="complexity_type" class="form-control">
                            <option value="raw" <?= (($building['complexity_type'] ?? '') === 'raw') ? 'selected' : ''; ?>>Hammaddesiz (raw)</option>
                            <option value="semi" <?= (($building['complexity_type'] ?? '') === 'semi') ? 'selected' : ''; ?>>İşlenmiş / Girdili (semi)</option>
                            <option value="finished" <?= (($building['complexity_type'] ?? '') === 'finished') ? 'selected' : ''; ?>>Nihai Ürün (finished)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Birim Tipi</label>
                        <input type="text" name="unit_type" class="form-control" value="<?= htmlspecialchars($building['unit_type'] ?? 'Adet'); ?>">
                    </div>
                </div>

                <!-- GİRDİLER REÇETESİ -->
                <div class="card p-3 mb-3 bg-light border">
                    <h6 class="text-danger fw-bold"><i class="fas fa-sign-in-alt me-1"></i> Tükettiği Hammaddeler / Girdiler</h6>
                    <?php 
                    $existingInputs = !empty($inputs) ? $inputs : [['product_id' => '', 'amount' => '']];
                    foreach ($existingInputs as $idx => $in): 
                    ?>
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <select name="inputs[<?= $idx; ?>][product_id]" class="form-control">
                                <option value="">-- Hammadde Seçin (Buğday vb.) --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>" <?= ($in['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($product['name']); ?> (<?= $product['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="inputs[<?= $idx; ?>][amount]" class="form-control" value="<?= $in['amount'] ?? ''; ?>" placeholder="Miktar">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ÇIKTILAR REÇETESİ -->
                <div class="card p-3 mb-3 bg-light border">
                    <h6 class="text-success fw-bold"><i class="fas fa-sign-out-alt me-1"></i> Ürettiği Ürünler / Çıktılar</h6>
                    <?php 
                    $existingOutputs = !empty($outputs) ? $outputs : [['product_id' => '', 'amount' => '']];
                    foreach ($existingOutputs as $idx => $out): 
                    ?>
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <select name="outputs[<?= $idx; ?>][product_id]" class="form-control">
                                <option value="">-- Ürün Seçin --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>" <?= ($out['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($product['name']); ?> (<?= $product['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" name="outputs[<?= $idx; ?>][amount]" class="form-control" value="<?= $out['amount'] ?? ''; ?>" placeholder="Miktar">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Kurulum Maliyeti (Base Cost)</label>
                        <input type="number" step="0.01" name="base_cost" class="form-control" value="<?= htmlspecialchars($building['base_cost'] ?? '50000.00'); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Mevcut Görsel</label>
                    <div>
                        <?php if (!empty($building['image']) && $building['image'] !== 'default.png'): ?>
                            <img src="<?= base_url('public/uploads/buildings/' . htmlspecialchars($building['image'])); ?>" alt="" width="80" height="80" class="object-fit-cover rounded mb-2 border">
                        <?php else: ?>
                            <p class="text-muted">Görsel yok</p>
                        <?php endif; ?>
                    </div>
                    <label class="form-label">Yeni Görsel Yükle</label>
                    <input type="file" name="image" class="form-control">
                </div>
            </div>
        </div>

        <!-- Tesis Seviye Matrisi (Level 1 - 10) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 fw-bold"><i class="fas fa-layer-group me-2"></i> Tesis Seviye Matrisi (Level 1 - 10 Detaylı Parametreler)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-center small">
                        <thead class="table-light">
                            <tr>
                                <th>Seviye</th>
                                <th>Gerekli XP</th>
                                <th>Üretim Çarpanı</th>
                                <th>Tüketim Çarpanı</th>
                                <th>Yükseltme Bedeli (₺)</th>
                                <th>Bakım Gideri (₺)</th>
                                <th>Enerji (kW)</th>
                                <th>Su (Lt)</th>
                                <th>Personel</th>
                                <th>Aşınma (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                $levelMap = [];
                                if (!empty($levels)) {
                                    foreach ($levels as $l) {
                                        $levelMap[$l['level']] = $l;
                                    }
                                }

                                for ($i = 1; $i <= 10; $i++): 
                                    $curr = $levelMap[$i] ?? [];
                                    $lXp = $curr['required_xp'] ?? ($i === 1 ? 0 : $i * 500);
                                    $lProd = $curr['production_rate'] ?? (8 * $i);
                                    $lCons = $curr['consumption_amount'] ?? (2 * $i);
                                    $lCost = $curr['upgrade_cost'] ?? (($building['base_cost'] ?? 50000) * $i * 1.5);
                                    $lMaint = $curr['maintenance_cost'] ?? (100 * $i);
                                    $lEnergy = $curr['energy_consumption'] ?? (50 + ($i * 5));
                                    $lWater = $curr['water_consumption'] ?? (10 + ($i * 2));
                                    $lLabor = $curr['labor_required'] ?? (10 + ($i * 2));
                                    $lWear = $curr['wear_rate'] ?? 2.00;
                            ?>
                            <tr>
                                <td class="fw-bold bg-light">Seviye <?= $i; ?></td>
                                <td><input type="number" name="levels[<?= $i; ?>][required_xp]" value="<?= $lXp; ?>" class="form-control form-control-sm text-center text-success fw-bold" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][production_rate]" value="<?= $lProd; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][consumption_amount]" value="<?= $lCons; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][upgrade_cost]" value="<?= $lCost; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][maintenance_cost]" value="<?= $lMaint; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][energy_consumption]" value="<?= $lEnergy; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][water_consumption]" value="<?= $lWater; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" name="levels[<?= $i; ?>][labor_required]" value="<?= $lLabor; ?>" class="form-control form-control-sm text-center" required></td>
                                <td><input type="number" step="0.01" name="levels[<?= $i; ?>][wear_rate]" value="<?= $lWear; ?>" class="form-control form-control-sm text-center" required></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 pb-5">
            <button type="submit" class="btn btn-success px-4 py-2 fw-bold shadow-sm">Değişiklikleri ve Reçeteyi Kaydet</button>
            <a href="<?= base_url('admin/buildingtype'); ?>" class="btn btn-secondary px-4 py-2">İptal</a>
        </div>
    </form>
</div>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tesis ve Fabrika Yönetim Merkezi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .building-card {
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .building-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.08) !important;
        }
        .metric-badge {
            font-size: 0.75rem;
            padding: 3px 6px;
            border-radius: 4px;
        }
        .research-badge-card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 4px;
            text-align: center;
            font-size: 0.72rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="container-fluid py-4 px-4">

    <!-- Üst Kasa ve Depo Slot Paneli -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-dark text-white p-3 h-100 d-flex flex-row justify-content-between align-items-center">
                <div>
                    <h6 class="text-warning mb-1"><i class="fas fa-wallet me-2"></i> Şirket Kasası</h6>
                    <h3 class="fw-bold mb-0 text-success"><span id="player-money-display"><?= number_format($player['money'] ?? 0, 2); ?></span> ₺</h3>
                </div>
                <a href="/manager/app/playerbuilding" class="btn btn-outline-light btn-sm"><i class="fas fa-sync-alt me-1"></i> Yenile</a>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="fw-bold text-dark mb-0"><i class="fas fa-warehouse text-primary me-2"></i> Merkez Depo (Seviye <?= $warehouse_summary['level']; ?>)</h6>
                    <span class="badge bg-primary">Ürün Başına Slot Sınırı: <?= number_format($warehouse_summary['slot_limit'], 0); ?> Birim</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">Her mamul kendi özel depolama kapasitesiyle korunur.</small>
                    <form action="/manager/app/playerbuilding/upgradewarehouse" method="POST">
                        <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2 fw-bold" style="font-size: 0.75rem;" title="Depo Kapasitesini Artır">
                            <i class="fas fa-arrow-up me-1"></i> Depoyu Büyüt (<?= number_format($warehouse_summary['upgrade_cost'], 2); ?>₺)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- Hammadde Deposu Grid Paneli (Profesyonel Görünüm) -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-box-seam text-warning me-2"></i> Merkez Hammadde ve Ürün Deposu
                <?php 
                    $filteredInventory = array_filter($inventory, function($item) {
                        $type = strtolower($item['type'] ?? '');
                        return $type !== 'utility' && $type !== 'energy' && $type !== 'water';
                    });
                ?>
                <span class="badge bg-primary ms-2 fs-6 px-2 py-1"><?php echo count($filteredInventory); ?> Çeşit</span>
            </h5>
            <div class="d-flex align-items-center">
                <?php 
                    $utilityItems = array_filter($inventory, function($item) {
                        $type = strtolower($item['type'] ?? '');
                        return $type === 'utility' || $type === 'energy' || $type === 'water';
                    });
                ?>
                <div class="d-flex gap-2">
                    <?php foreach ($utilityItems as $util): ?>
                        <span class="badge bg-secondary bg-opacity-75 border border-secondary px-2 py-1 text-light" title="Şebeke Altyapı Kaynağı">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i><?php echo htmlspecialchars($util['product_name']); ?>: <strong><?php echo number_format($util['quantity'], 1); ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="card-body bg-light p-4">
            <div class="row g-3">
                <?php if (empty($filteredInventory)): ?>
                    <div class="col-12 text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        <p class="mb-0">Depoda kayıtlı hammadde veya mamul bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($filteredInventory as $inv): 
                        $maxLimit = (float)$warehouse_summary['slot_limit'];
                        $qty = (float)$inv['quantity'];
                        $fillPercent = min(100, ($qty / ($maxLimit > 0 ? $maxLimit : 500)) * 100);
                        
                        // Doluluk oranına göre progress bar rengi (Dinamik Uyarı)
                        $barColor = 'bg-success';
                        if ($fillPercent >= 80) $barColor = 'bg-warning';
                        if ($fillPercent >= 95) $barColor = 'bg-danger';
                    ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <div class="card h-100 shadow-sm border-0 item-card rounded-3 overflow-hidden" 
                                 style="cursor: pointer; transition: all 0.25s ease-in-out;" 
                                 onclick="openInventoryModal(<?php echo $inv['product_id']; ?>, '<?php echo htmlspecialchars($inv['product_name']); ?>')"
                                 onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.12)'"
                                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 .125rem .25rem rgba(0,0,0,.075)'">
                                
                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <!-- Üst Kısım: Ürün Resmi ve Adı -->
                                    <div>
                                        <div class="d-flex align-items-center mb-2">
                                            <?php if (!empty($inv['image'])): ?>
                                                <img src="/manager/public/uploads/products/<?= htmlspecialchars($inv['image']); ?>" width="36" height="36" class="rounded border bg-white object-fit-contain p-1 me-2 shadow-sm" alt="ürün">
                                            <?php else: ?>
                                                <div class="rounded border bg-white d-flex align-items-center justify-content-center me-2 shadow-sm text-secondary" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                                    <i class="bi bi-box"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h6 class="card-title text-truncate mb-0 fw-bold text-dark" style="font-size: 0.9rem;" title="<?php echo htmlspecialchars($inv['product_name']); ?>">
                                                <?php echo htmlspecialchars($inv['product_name']); ?>
                                            </h6>
                                        </div>

                                        <!-- Miktar ve Maliyet -->
                                        <div class="mb-2">
                                            <h4 class="text-primary fw-bold mb-0" style="font-size: 1.25rem;">
                                                <?php echo number_format($qty, 2); ?>
                                                <span class="text-muted fw-normal" style="font-size: 0.75rem;"> <?= htmlspecialchars($inv['unit_type']); ?></span>
                                            </h4>
                                            <span class="text-muted" style="font-size: 0.72rem;">Birim Maliyet: <strong>₺<?php echo number_format($inv['unit_cost'] ?? 0, 2); ?></strong></span>
                                        </div>
                                    </div>

                                    <!-- Alt Kısım: Kapasite Barı ve Detay -->
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.7rem;">
                                            <span class="text-muted">Kapasite : (%<?php echo round($fillPercent, 1); ?> Dolu)</span>
                                            <span class="fw-bold text-dark"><?php echo number_format($qty, 0); ?> / <?php echo number_format($maxLimit, 0); ?></span>
                                        </div>
                                        <div class="progress mb-1" style="height: 6px; border-radius: 3px;">
                                            <div class="progress-bar <?php echo $barColor; ?>" role="progressbar" style="width: <?php echo $fillPercent; ?>%" aria-valuenow="<?php echo $fillPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Aktif Tesisler -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-industry text-success me-2"></i> Aktif İşletmeleriniz ve Tesisleriniz (<?= count($my_buildings); ?>)</h5>
    </div>

    <div class="row g-4">
        <?php if (!empty($my_buildings)): ?>
            <?php foreach ($my_buildings as $b): ?>
                <?php
                    $unitCost = (float)($b['formula_details']['unit_cost'] ?? 0);
                    $totalCost = (float)($b['formula_details']['production_cost'] ?? 0);
                    $cycleSec = (int)($b['cycle_seconds'] ?? 600);
                    $remSec = (int)($b['rem_sec'] ?? 0);
                    $progress = (float)($b['progress'] ?? 0);
                    $barText = $b['bar_text'] ?? 'Üretim Süreci';
                    $barColor = $b['bar_color'] ?? 'bg-success';
                    $prodStatus = $b['production_status'] ?? 'waiting_for_input';

                    $inputs = $b['formula_details']['inputs'] ?? [];
                    $hasInput = !empty($inputs);
                    $hasEnoughInput = $b['formula_details']['has_enough_input'];
                    $isPaused = (int)$b['is_paused'] === 1;

                    $currentLevel = (int)($b['level'] ?? 1);
                    $nextLevel = $currentLevel + 1;
                    $baseCost = (float)($b['base_cost'] ?? 1000);
                    $upgradeCost = round($baseCost * $nextLevel * 1.5, 2);
                    $playerMoney = (float)($player['money'] ?? 0);
                    $canUpgrade = ($currentLevel < 10 && $playerMoney >= $upgradeCost);
                    $health = (float)$b['condition_health'];
                    $res = $b['formula_details']['researches'];
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                    <div class="card building-card shadow-sm h-100 bg-white <?= $isPaused ? 'border-warning' : ($health < 50 ? 'border-danger' : ''); ?>">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <?php if (!empty($b['image'])): ?>
                                    <img src="/manager/public/uploads/buildings/<?= htmlspecialchars($b['image']); ?>" width="52" height="52" class="me-3 rounded border object-fit-cover shadow-sm">
                                <?php else: ?>
                                    <img src="/manager/public/uploads/buildings/factory-default.png" width="52" height="52" class="me-3 rounded border object-fit-cover shadow-sm">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($b['building_name']); ?></h6>
                                        <span class="badge bg-secondary" style="font-size: 0.7rem;">Sev. <?= $b['level']; ?></span>
                                    </div>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <span class="badge bg-info text-dark metric-badge"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($b['province_name']); ?></span>
                                        <span class="badge bg-<?= $health < 50 ? 'danger' : 'success'; ?> text-white metric-badge">Sağlık: %<?= number_format($health, 0); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 0.72rem;">
                                    <span>Tesis Kondisyon Durumu (%<?= number_format($health, 1); ?>)</span>
                                    <span class="fw-bold text-<?= $health < 50 ? 'danger' : 'success'; ?>"><?= $health < 50 ? 'Üretim Durdu' : 'Üretim Aktif'; ?></span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 3px;">
                                    <div class="progress-bar <?= $health < 50 ? 'bg-danger' : ($health < 80 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?= $health; ?>%;"></div>
                                </div>
                            </div>

                            <?php if (empty($b['formula_details']['warning_message'])): ?>
    <div class="p-2 border rounded mb-2 bg-light d-flex flex-column justify-content-center small" style="min-height: 75px;">
        
        <!-- Üretilen Ana Ürün Gösterimi -->
        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="text-muted" style="font-size: 0.7rem;"><i class="fas fa-arrow-alt-circle-right text-success me-1"></i>Üretilen:</span>
            <div class="d-flex align-items-center">
                <?php if (!empty($b['formula_details']['product_image'])): ?>
                    <img src="/manager/public/uploads/products/<?= htmlspecialchars($b['formula_details']['product_image']); ?>" width="20" height="20" class="rounded border bg-white object-fit-contain me-1" alt="ürün">
                <?php endif; ?>
                <strong class="text-success" style="font-size: 0.75rem;"><?= htmlspecialchars($b['product_name'] ?? 'Mamul'); ?></strong>
            </div>
        </div>

        <!-- Reçete / Hammadde Girdileri Bölümü -->
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span><i class="fas fa-cubes text-muted me-1"></i>Reçete Girdileri:</span>
        </div>

        <?php if (!empty($b['formula_details']['inputs'])): ?>
            <?php foreach ($b['formula_details']['inputs'] as $inp): ?>
                <div class="d-flex justify-content-between align-items-center my-1">
                    <div class="d-flex align-items-center text-truncate" style="max-width: 65%;">
                        <?php if (!empty($inp['product_image'])): ?>
                            <img src="/manager/public/uploads/products/<?= htmlspecialchars($inp['product_image']); ?>" width="18" height="18" class="rounded border bg-white object-fit-contain me-1 flex-shrink-0" alt="girdi">
                        <?php endif; ?>
                        <span class="text-truncate" title="<?= htmlspecialchars($inp['product_name']); ?>">
                            <?= htmlspecialchars($inp['product_name']); ?>
                            <span class="badge bg-success ms-1 flex-shrink-0"><?= number_format($inp['amount'], 0); ?> <?= htmlspecialchars($inp['unit_type']); ?></span>
                        </span>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="d-flex justify-content-center align-items-center my-1">
                <span class="badge bg-secondary">Hammadde Gerekmez (Doğal Üretim).</span>
            </div>
        <?php endif; ?>

    </div>
<?php else: ?>
    <div class="alert alert-danger small d-flex justify-content-between align-items-center mb-1">
        <span><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($b['formula_details']['warning_message']); ?></span>
    </div>
<?php endif; ?>

                            <div class="row g-1 mb-3 text-center">
                                <div class="col-2">
                                    <div class="p-1 border rounded bg-light">
                                        <?php if (!empty($b['product_image'])): ?>
                                            <img src="/manager/public/uploads/products/<?= htmlspecialchars($b['product_image']); ?>" width="36" height="36" class="rounded border bg-white object-fit-contain flex-shrink-0" alt="girdi">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="p-1 border rounded bg-light">
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">
                                            <?= htmlspecialchars($b['product_name']); ?>
                                        </small>
                                        <strong class="text-success small">+<?= number_format($b['production_amount'], 2); ?> <?= htmlspecialchars($b['unit_type']); ?></strong>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="p-1 border rounded bg-light">
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Birim Maliyet</small>
                                        <strong class="text-dark small">₺<?= number_format($unitCost, 2); ?></strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-1 border rounded bg-light">
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Toplam Maliyet</small>
                                        <strong class="text-danger small">₺<?= number_format($totalCost, 2); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 building-timer-box"
                                 data-building-id="<?= $b['id']; ?>"
                                 data-health="<?= $health; ?>"
                                 data-remaining="<?= $remSec; ?>"
                                 data-cycle="<?= $cycleSec; ?>"
                                 data-status="<?= $prodStatus; ?>"
                                 data-paused="<?= $isPaused ? '1' : '0'; ?>"
                                 data-has-input="<?= $hasInput ? '1' : '0'; ?>"
                                 data-has-enough="<?= $hasEnoughInput ? '1' : '0'; ?>"
                                 data-bar-text="<?= htmlspecialchars($barText); ?>">
                                <div class="d-flex justify-content-between small fw-bold mb-1" style="font-size: 0.78rem;">
                                    <span class="text-muted timer-status-text"><?= $barText; ?></span>
                                    <span class="text-primary">%<span class="progress-percent"><?= number_format($progress, 1); ?></span> (<span class="timer-text"><?= gmdate("i:s", $remSec); ?></span>)</span>
                                </div>
                                <div class="progress" style="height: 10px; border-radius: 5px;">
                                    <div class="progress-bar main-progress-bar <?= $barColor; ?>" style="width: <?= $progress; ?>%;"></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#detailModal-<?= $b['id']; ?>">
                                    <i class="fas fa-search-plus me-1"></i> Detay & Raporlar
                                </button>
                                <div class="d-flex gap-1">
                                    <form action="/manager/app/playerbuilding/finish" method="POST" class="finish-form">
                                        <input type="hidden" name="building_id" value="<?= $b['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning fw-bold py-1 px-2 <?= $health < 50 ? 'disabled' : ''; ?>" style="font-size: 0.78rem;" title="Üretimi Anında Tamamla">
                                            <i class="fas fa-bolt"></i>
                                        </button>
                                    </form>

                                    <form action="/manager/app/playerbuilding/togglepause" method="POST" class="pause-form">
                                        <input type="hidden" name="building_id" value="<?= $b['id']; ?>">
                                        <button type="submit" class="btn btn-sm <?= $isPaused ? 'btn-success' : 'btn-secondary'; ?> py-1 px-2 fw-bold" style="font-size: 0.78rem;" title="<?= $isPaused ? 'Üretime Devam Et' : 'Üretimi Duraklat'; ?>">
                                            <i class="fas <?= $isPaused ? 'fa-play' : 'fa-pause'; ?>"></i>
                                        </button>
                                    </form>

                                    <form action="/manager/app/playerbuilding/sell/<?= $b['id']; ?>" method="POST" onsubmit="return confirm('Tesisi tasfiye etmek istediğinize emin misiniz?');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 fw-bold" style="font-size: 0.78rem;" title="Tesisi Tasfiye Et / Sat">
                                            <i class="fas fa-dollar-sign"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Detay Modal -->
                <div class="modal fade" id="detailModal-<?= $b['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-dark text-white py-3">
                                <h5 class="modal-title fw-bold"><i class="fas fa-industry text-warning me-2"></i> <?= htmlspecialchars($b['building_name']); ?> Tesis Yönetim Paneli</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body p-4">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-chart-line me-1"></i> Operasyonel Metrikler</h6>
                                <div class="row g-2 mb-4 text-center">
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded bg-white shadow-sm">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Sağlık Durumu</small>
                                            <strong class="text-dark fs-6">%<?= number_format($health, 1); ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded bg-white shadow-sm">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Enerji Tüketimi</small>
                                            <strong class="text-dark fs-6"><?= $b['formula_details']['energy_consumption']; ?> kW/h</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded bg-white shadow-sm">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">Personel İhtiyacı</small>
                                            <strong class="text-dark fs-6"><?= $b['formula_details']['labor_required']; ?> Kişi</strong>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-calculator me-1"></i> Üretim Hesaplama Parametreleri</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm align-middle small text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Faktör / Parametre</th>
                                                <th>Değer</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Girdi / Çıktı Tablosu Temel Miktarı</td>
                                                <td><strong><?= $b['formula_details']['base_output_amount']; ?> Adet</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Tesis Üretim Katsayısı</td>
                                                <td><strong><?= $b['formula_details']['production_rate']; ?>x</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Toprak / Bölge Verimliliği</td>
                                                <td><strong><?= number_format($b['formula_details']['soil_multiplier'], 2); ?>x</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Tesis Kondüsyonu (Sağlık Faktörü)</td>
                                                <td><strong>%<?= number_format($b['formula_details']['health_factor'] * 100, 1); ?></strong></td>
                                            </tr>
                                            <tr class="table-success">
                                                <td><strong>Nihai Üretim Miktarı</strong></td>
                                                <td><strong class="fs-6">+<?= number_format($b['production_amount'], 2); ?> Adet</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-file-invoice-dollar me-1"></i> Üretim Maliyeti ve Gider Kalemleri Cetveli</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm align-middle small">
                                        <thead class="table-dark text-center">
                                            <tr>
                                                <th>Gider Kalemi</th>
                                                <th>Toplam Tutar (₺)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($b['formula_details']['inputs'])): ?>
                                                <?php foreach ($b['formula_details']['inputs'] as $inp): ?>
                                                    <tr>
                                                        <td>Hammadde Girdisi (<?= htmlspecialchars($inp['product_name']); ?> <?= $inp['amount']; ?> Adet x ₺<?= number_format($inp['unit_cost'], 2); ?>)</td>
                                                        <td class="text-end fw-bold">₺<?= number_format($inp['total_cost'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td>Hammadde Girdisi (Gerekmiyor - Doğal Üretim)</td>
                                                    <td class="text-end fw-bold">₺0.00</td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td>Personel ve İşçilik Gideri (<?= $b['formula_details']['labor_required']; ?> İşçi, İl Çarpanı Dahil)</td>
                                                <td class="text-end fw-bold">₺<?= number_format($b['formula_details']['total_labor_expense'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Lojistik ve Nakliye Gideri (AR-GE İndirimli)</td>
                                                <td class="text-end fw-bold">₺<?= number_format($b['formula_details']['base_transport_cost'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Yasal Vergiler ve Fonlar (AR-GE İndirimli)</td>
                                                <td class="text-end fw-bold">₺<?= number_format($b['formula_details']['base_tax_rate'], 2); ?></td>
                                            </tr>
                                            <tr class="table-warning fw-bold">
                                                <td class="text-end">TOPLAM PARTİ ÜRETİM MALİYETİ:</td>
                                                <td class="text-end text-danger">₺<?= number_format($totalCost, 2); ?></td>
                                            </tr>
                                            <tr class="table-info fw-bold">
                                                <td class="text-end">BİRİM ÜRÜN BAŞINA MALİYET:</td>
                                                <td class="text-end text-dark">₺<?= number_format($unitCost, 2); ?> / Adet</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($health < 100.0): ?>
                                    <div class="card bg-warning bg-opacity-10 border border-warning mb-4">
                                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-tools text-warning me-1"></i> Bakım ve Onarım Gereksinimi</h6>
                                                <small class="text-muted">Kondisyonu yenilemek için gereken tamirat bedeli: <strong class="text-danger">₺<?= number_format($b['repair_cost'], 2); ?></strong></small>
                                            </div>
                                            <form action="/manager/app/playerbuilding/repair" method="POST">
                                                <input type="hidden" name="building_id" value="<?= $b['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-warning fw-bold px-3 <?= $playerMoney >= $b['repair_cost'] ? '' : 'disabled'; ?>">
                                                    <i class="fas fa-wrench me-1"></i> Bakım Yap
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="card bg-light border mb-4">
                                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-level-up-alt text-success me-1"></i> Tesis Modernizasyonu (Seviye <?= $currentLevel; ?> → <?= $nextLevel; ?>)</h6>
                                            <small class="text-muted">Yatırım Maliyeti: <strong class="text-danger">₺<?= number_format($upgradeCost, 2); ?></strong></small>
                                        </div>
                                        <div>
                                            <?php if ($currentLevel < 10): ?>
                                                <form action="/manager/app/playerbuilding/upgrade" method="POST">
                                                    <input type="hidden" name="building_id" value="<?= $b['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3 <?= $canUpgrade ? '' : 'disabled'; ?>">
                                                        <i class="fas fa-arrow-up me-1"></i> Seviye Atlat
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Maksimum Seviyede</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- AR-GE Sertifika Kartları -->
                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary mb-2"><i class="fas fa-certificate me-1"></i> Tesis Üzerindeki Aktif AR-GE Sertifikaları ve Etkileri</h6>
                                    <div class="row g-2">
                                        <div class="col"><div class="research-badge-card"><div class="text-success fw-bold mb-1"><i class="fas fa-seedling"></i> Biyoloji</div><span class="badge bg-dark mb-1">Sev. <?= $res['rBio']; ?></span><div class="text-muted" style="font-size: 0.65rem;">+%<?= (($res['rBio'] - 1) * 2); ?> Tarım Verimi</div></div></div>
                                        <div class="col"><div class="research-badge-card"><div class="text-primary fw-bold mb-1"><i class="fas fa-cogs"></i> Endüstri</div><span class="badge bg-dark mb-1">Sev. <?= $res['rInd']; ?></span><div class="text-muted" style="font-size: 0.65rem;">+%<?= (($res['rInd'] - 1) * 3); ?> Üretim Hızı</div></div></div>
                                        <div class="col"><div class="research-badge-card"><div class="text-info fw-bold mb-1"><i class="fas fa-microchip"></i> Teknoloji</div><span class="badge bg-dark mb-1">Sev. <?= $res['rTech']; ?></span><div class="text-muted" style="font-size: 0.65rem;">+%<?= (($res['rTech'] - 1) * 2); ?> Kapasite</div></div></div>
                                        <div class="col"><div class="research-badge-card"><div class="text-warning fw-bold mb-1"><i class="fas fa-truck"></i> Lojistik</div><span class="badge bg-dark mb-1">Sev. <?= $res['rLog']; ?></span><div class="text-muted" style="font-size: 0.65rem;">-%<?= min(50, ($res['rLog'] - 1) * 5); ?> Nakliye</div></div></div>
                                        <div class="col"><div class="research-badge-card"><div class="text-danger fw-bold mb-1"><i class="fas fa-chart-pie"></i> Finans</div><span class="badge bg-dark mb-1">Sev. <?= $res['rFin']; ?></span><div class="text-muted" style="font-size: 0.65rem;">-%<?= min(40, ($res['rFin'] - 1) * 4); ?> Vergi</div></div></div>
                                    </div>
                                </div>

                                <!-- Log Tabları -->
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-history me-1"></i> Fabrika İşlem Günlüğü ve Kayıtlar</h6>
                                <ul class="nav nav-tabs" id="logTab-<?= $b['id']; ?>" role="tablist">
                                    <li class="nav-item"><button class="nav-link active small fw-bold" id="input-tab-<?= $b['id']; ?>" data-bs-toggle="tab" data-bs-target="#input-pane-<?= $b['id']; ?>" type="button">Girdiler<span class="badge bg-secondary ms-1"><?= number_format($b['totals']['consumed'], 1); ?></span></button></li>
                                    <li class="nav-item"><button class="nav-link small fw-bold" id="output-tab-<?= $b['id']; ?>" data-bs-toggle="tab" data-bs-target="#output-pane-<?= $b['id']; ?>" type="button">Çıktılar<span class="badge bg-success ms-1"><?= number_format($b['totals']['produced'], 1); ?></span></button></li>
                                    <li class="nav-item"><button class="nav-link small fw-bold" id="cost-tab-<?= $b['id']; ?>" data-bs-toggle="tab" data-bs-target="#cost-pane-<?= $b['id']; ?>" type="button">Giderler<span class="badge bg-danger ms-1">₺<?= number_format($b['totals']['cost'], 2); ?></span></button></li>
                                    <li class="nav-item"><button class="nav-link small fw-bold" id="wear-tab-<?= $b['id']; ?>" data-bs-toggle="tab" data-bs-target="#wear-pane-<?= $b['id']; ?>" type="button">Bakım<span class="badge bg-warning text-dark ms-1"><?= $b['totals']['wear']; ?> Adet</span></button></li>
                                </ul>

                                <div class="tab-content border border-top-0 p-3 bg-light rounded-bottom" style="max-height: 200px; overflow-y: auto;">
                                    <div class="tab-pane fade show active" id="input-pane-<?= $b['id']; ?>" role="tabpanel">
                                        <?php if (!empty($b['input_logs'])): ?>
                                            <ul class="list-group list-group-flush small">
                                                <?php foreach ($b['input_logs'] as $log): ?>
                                                    <li class="list-group-item bg-transparent py-1 px-0 border-bottom"><span class="badge bg-secondary"><?= $log['created_at']; ?></span> <?= htmlspecialchars($log['description']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?><small class="text-muted">Hammadde tüketim kaydı bulunmuyor.</small><?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="output-pane-<?= $b['id']; ?>" role="tabpanel">
                                        <?php if (!empty($b['output_logs'])): ?>
                                            <ul class="list-group list-group-flush small">
                                                <?php foreach ($b['output_logs'] as $log): ?>
                                                    <li class="list-group-item bg-transparent py-1 px-0 border-bottom"><span class="badge bg-success"><?= $log['created_at']; ?></span> <?= htmlspecialchars($log['description']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?><small class="text-muted">Üretim çıktısı kaydı bulunmuyor.</small><?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="cost-pane-<?= $b['id']; ?>" role="tabpanel">
                                        <?php if (!empty($b['cost_logs'])): ?>
                                            <ul class="list-group list-group-flush small">
                                                <?php foreach ($b['cost_logs'] as $log): ?>
                                                    <li class="list-group-item bg-transparent py-1 px-0 border-bottom"><span class="badge bg-danger"><?= $log['created_at']; ?></span> <?= htmlspecialchars($log['description']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?><small class="text-muted">Gider ve bakım kaydı bulunmuyor.</small><?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="wear-pane-<?= $b['id']; ?>" role="tabpanel">
                                        <?php if (!empty($b['wear_logs'])): ?>
                                            <ul class="list-group list-group-flush small">
                                                <?php foreach ($b['wear_logs'] as $log): ?>
                                                    <li class="list-group-item bg-transparent py-1 px-0 border-bottom"><span class="badge bg-warning text-dark"><?= $log['created_at']; ?></span> <?= htmlspecialchars($log['description']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?><small class="text-muted">Tesis aşınma kaydı bulunmuyor.</small><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-5">
                <h6>Henüz aktif bir tesisiniz veya fabrikanız bulunmuyor.</h6>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hammadde Detay & Yönetim Modalı -->
<div class="modal fade" id="inventoryDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="modalProductName">Hammadde & Tesis Yönetim Paneli</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body" style="max-height: 82vh; overflow-y: auto;">
                <div class="row">
                    <div class="col-md-4 border-end">
                        <div class="mb-3">
                            <span class="text-muted small">Mevcut Stok Miktarı:</span>
                            <h3 id="modalItemQuantity" class="text-primary fw-bold">0.00</h3>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted small">Birim Maliyet / Piyasa Fiyatı:</span>
                            <h5 id="modalItemCost" class="text-success">₺0.00</h5>
                        </div>
                        <hr>
                        <form id="tradeForm" onsubmit="executeTrade(event)">
                            <input type="hidden" id="tradeProductId" name="product_id">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">İşlem Miktarı</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="tradeAmount" name="amount" required min="1" value="10">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm w-50" onclick="setTradeAction('buy')">
                                    <i class="bi bi-cart-plus"></i> Satın Al
                                </button>
                                <button type="submit" class="btn btn-danger btn-sm w-50" onclick="setTradeAction('sell')">
                                    <i class="bi bi-cart-dash"></i> Piyasaya Sat
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-8">
                        <div class="card mb-3 shadow-sm border-0">
                            <div class="card-header bg-light py-1"><small class="fw-bold text-secondary">1. Üretim Miktarı vs Ara Depoya Çekilen (Consumption)</small></div>
                            <div class="card-body p-2" style="height: 170px; position: relative;"><canvas id="chartProdCons"></canvas></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3 shadow-sm border-0">
                                    <div class="card-header bg-light py-1"><small class="fw-bold text-secondary">2. Cost - Harcanan Para Miktarı (₺)</small></div>
                                    <div class="card-body p-2" style="height: 170px; position: relative;"><canvas id="chartCost"></canvas></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card shadow-sm border-0">
                                    <div class="card-header bg-light py-1"><small class="fw-bold text-secondary">3. Enerji Tüketim Grafiği</small></div>
                                    <div class="card-body p-2" style="height: 170px; position: relative;"><canvas id="chartEnergy"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row">
                    <h6 class="text-muted small mb-2"><i class="bi bi-clock-history"></i> Bu Ürüne Ait Depo & Üretim Hareketleri (Loglar)</h6>
                    <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                        <table class="table table-sm table-striped text-center small mb-0">
                            <thead>
                                <tr>
                                    <th>Tesis</th>
                                    <th>İşlem Tipi</th>
                                    <th>Miktar</th>
                                    <th>Açıklama / Detay</th>
                                    <th>Zaman</th>
                                </tr>
                            </thead>
                            <tbody id="modalLogsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    function formatTime(seconds) {
        if (seconds <= 0) return "00:00";
        let m = Math.floor(seconds / 60);
        let s = seconds % 60;
        return (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
    }

    let timerBoxes = document.querySelectorAll(".building-timer-box");

    setInterval(function() {
        let needsReload = false;

        timerBoxes.forEach(function(box) {
            let health = parseFloat(box.getAttribute("data-health")) || 100;
            if (health < 50) return;

            let isPaused = box.getAttribute("data-paused") === "1";
            let hasInput = box.getAttribute("data-has-input") === "1";
            let hasEnough = box.getAttribute("data-has-enough") === "1";
            let barText = box.getAttribute("data-bar-text") || "";

            if (isPaused) return;
            if (barText.includes("BEKLENİYOR") || barText.includes("DURDURULDU")) return;
            if (hasInput && !hasEnough) return;

            let remSec = parseInt(box.getAttribute("data-remaining"));
            let cycleSec = parseInt(box.getAttribute("data-cycle")) || 600;

            if (remSec > 0) {
                remSec--;
                box.setAttribute("data-remaining", remSec);

                let elapsed = cycleSec - remSec;
                let progress = Math.min(100, (elapsed / cycleSec) * 100);

                let progressBar = box.querySelector(".main-progress-bar");
                let progressPercent = box.querySelector(".progress-percent");
                let timerText = box.querySelector(".timer-text");

                if (progressBar) progressBar.style.width = progress.toFixed(1) + "%";
                if (progressPercent) progressPercent.innerText = progress.toFixed(1);
                if (timerText) timerText.innerText = formatTime(remSec);
            } else {
                needsReload = true;
            }
        });

        if (needsReload) {
            location.reload();
        }
    }, 1000);

    // AJAX Form Dinleyicileri
    document.querySelectorAll(".finish-form, .pause-form").forEach(form => {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch(this.action, {
                method: "POST",
                body: formData,
                headers: { "X-Requested-With": "XMLHttpRequest" },
                credentials: "include"
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message || 'Bir hata oluştu.');
                }
            })
            .catch(() => location.reload());
        });
    });
});

let chartInstProdCons = null;
let chartInstCost = null;
let chartInstEnergy = null;
let currentTradeAction = 'buy';

function setTradeAction(action) {
    currentTradeAction = action;
}

function openInventoryModal(productId, productName) {
    document.getElementById('modalProductName').innerText = productName + ' - Yönetim ve Analiz Paneli';
    document.getElementById('tradeProductId').value = productId;

    fetch('/manager/app/playerbuilding/getInventoryItemDetail?product_id=' + productId, {
        method: 'GET',
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('modalItemQuantity').innerText = parseFloat(data.item.quantity).toFixed(2);
            document.getElementById('modalItemCost').innerText = '₺' + parseFloat(data.item.unit_cost || 0).toFixed(2);

            let tbody = document.getElementById('modalLogsTableBody');
            tbody.innerHTML = '';
            if (data.logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Bu ürüne ait henüz bir hareket kaydı bulunmuyor.</td></tr>';
            } else {
                data.logs.forEach(log => {
                    let badgeClass = 'bg-secondary';
                    let actionLabel = log.action_type;
                    if (log.action_type === 'production') {
                        badgeClass = 'bg-success';
                        actionLabel = 'Üretim';
                    } else if (log.action_type === 'consumption') {
                        badgeClass = 'bg-warning text-dark';
                        actionLabel = 'Ara Depoya Çekildi';
                    } else if (log.action_type === 'cost') {
                        badgeClass = 'bg-danger';
                        actionLabel = 'Maliyet';
                    }

                    tbody.innerHTML += `<tr>
                        <td class="fw-bold">${log.building_name || 'Tesis'}</td>
                        <td><span class="badge ${badgeClass}">${actionLabel}</span></td>
                        <td>${parseFloat(log.amount).toFixed(2)}</td>
                        <td class="text-start">${log.description || '-'}</td>
                        <td class="text-muted">${log.created_at}</td>
                    </tr>`;
                });
            }

            renderChartProdCons(data.charts.prod_cons);
            renderChartCost(data.charts.cost);
            renderChartEnergy(data.charts.energy);

            var myModal = new bootstrap.Modal(document.getElementById('inventoryDetailModal'));
            myModal.show();
        } else {
            alert(data.message);
        }
    })
    .catch(err => console.error('Hata:', err));
}

function renderChartProdCons(chartData) {
    const ctx = document.getElementById('chartProdCons').getContext('2d');
    if (chartInstProdCons) chartInstProdCons.destroy();
    chartInstProdCons = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels.length ? chartData.labels : ['Veri Yok'],
            datasets: [
                { label: 'Üretilen Miktar', data: chartData.production, backgroundColor: 'rgba(40, 167, 69, 0.7)' },
                { label: 'Ara Depoya Çekilen (Consumption)', data: chartData.consumption, backgroundColor: 'rgba(220, 53, 69, 0.7)' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}

function renderChartCost(chartData) {
    const ctx = document.getElementById('chartCost').getContext('2d');
    if (chartInstCost) chartInstCost.destroy();
    chartInstCost = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels.length ? chartData.labels : ['Veri Yok'],
            datasets: [
                { label: 'Harcanan Para (₺)', data: chartData.data, backgroundColor: 'rgba(255, 193, 7, 0.7)' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}

function renderChartEnergy(chartData) {
    const ctx = document.getElementById('chartEnergy').getContext('2d');
    if (chartInstEnergy) chartInstEnergy.destroy();
    chartInstEnergy = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels.length ? chartData.labels : ['Veri Yok'],
            datasets: [
                { label: 'Enerji Tüketimi (Birim)', data: chartData.data, backgroundColor: 'rgba(23, 162, 184, 0.7)' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}

function executeTrade(event) {
    event.preventDefault();
    let formData = new FormData(document.getElementById('tradeForm'));
    formData.append('action_type', currentTradeAction);

    fetch('/manager/app/playerbuilding/tradeInventoryItem', {
        method: 'POST',
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: 'include',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('Hata: ' + data.message);
        }
    })
    .catch(err => console.error('İşlem hatası:', err));
}
</script>
</body>
</html>
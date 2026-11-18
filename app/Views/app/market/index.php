

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perakende ve Market Yönetim Merkezi - Kapitalizm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .market-card { border-radius: 12px; border: 1px solid #e0e0e0; transition: transform 0.2s, box-shadow 0.2s; }
        .market-card:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08) !important; }
        .slot-box { background: #fdfdfd; border: 1px solid #dee2e6; border-radius: 8px; transition: all 0.2s; }
        .slot-box:hover { border-color: #adb5bd; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4 px-4">


    <!-- YÖNETİM PANELİ ÖZET KARTI -->
    <div class="row g-3 mb-4">
        <div class="col-xl-12">
            <div class="card border-0 shadow-sm bg-dark text-white p-4 rounded-4">
                <div class="row align-items-center g-4">
                    <!-- Sol: Şirket ve Oyuncu Bilgisi -->
                    <div class="col-md-4 border-end border-secondary">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold fs-3 shadow" style="width: 60px; height: 60px;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <span class="badge bg-warning text-dark mb-1" style="font-size: 0.7rem;">Yönetim Merkezi</span>
                                <h4 class="fw-bold mb-0 text-white"><?= htmlspecialchars($player['username'] ?? 'Şirket Sahibi'); ?></h4>
                                <span class="text-muted small" style="font-size: 0.75rem;"><i class="fas fa-store text-success me-1"></i> Aktif Mağaza: <strong><?= count($stores ?? []); ?></strong> Adet</span>
                            </div>
                        </div>
                    </div>

                    <!-- Orta: Şirket Kasası ve Net Akış Özeti -->
                    <div class="col-md-5 border-end border-secondary">
                        <div class="row g-3">
                            <div class="col-6">
                                <span class="text-muted d-block small" style="font-size: 0.7rem;"><i class="fas fa-wallet me-1 text-warning"></i> Şirket Kasası</span>
                                <h4 class="fw-bold text-success mb-0"><span id="player-money-display"><?= number_format($player['money'] ?? 0, 2); ?></span> ₺</h4>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block small" style="font-size: 0.7rem;"><i class="fas fa-chart-line me-1 text-info"></i> Net Kasa Akışı</span>
                                <?php 
                                $netFlow = ($company_cash_summary['total_in'] ?? 0) - ($company_cash_summary['total_out'] ?? 0);
                                $flowColor = $netFlow >= 0 ? 'info' : 'danger';
                                ?>
                                <h5 class="fw-bold text-<?= $flowColor; ?> mb-0">
                                    <?= number_format($netFlow, 2); ?> ₺
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Sağ: Hızlı İşlem Butonları -->
                    <div class="col-md-3 text-end d-flex flex-column gap-2">
                        <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#companyCashModal">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Şirket Kasa Defteri
                        </button>
                        <button class="btn btn-success btn-sm fw-bold shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#newMarketModal">
                            <i class="fas fa-store-alt me-1"></i> Yeni Mağaza Kur
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- MERKEZ DEPO -->
    <div class="card shadow-sm mb-4 border-0 rounded-3">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-box-seam text-warning me-2"></i> Merkez Deposu Hammadde ve Ürünler
                <?php 
                $filteredInventory = array_filter($inventory ?? [], function($item) {
                    $type = strtolower($item['type'] ?? '');
                    return $type !== 'utility' && $type !== 'energy' && $type !== 'water';
                });
                ?>
                <span class="badge bg-primary ms-2 fs-6 px-2 py-1"><?= count($filteredInventory); ?> Çeşit</span>
            </h5>
        </div>
        <div class="card-body bg-light p-4">
            <div class="row g-3">
                <?php if (empty($filteredInventory)): ?>
                    <div class="col-12 text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        <p class="mb-0">Merkez depoda kayıtlı hammadde veya mamul bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($filteredInventory as $inv):
                        $maxLimit = (float)($warehouse_summary['slot_limit'] ?? 500);
                        $qty = (float)$inv['quantity'];
                        $fillPercent = min(100, ($qty / ($maxLimit > 0 ? $maxLimit : 500)) * 100);
                        $barColor = $fillPercent >= 80 ? ($fillPercent >= 95 ? 'bg-danger' : 'bg-warning') : 'bg-success';
                    ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <div class="card h-100 shadow-sm border-0 item-card rounded-3 overflow-hidden bg-white">
                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center mb-2">
                                            <?php if (!empty($inv['image'])): ?>
                                                <img src="/manager/public/uploads/products/<?= htmlspecialchars($inv['image']); ?>" width="36" height="36" class="rounded border bg-white object-fit-contain p-1 me-2 shadow-sm" alt="ürün">
                                            <?php else: ?>
                                                <div class="rounded border bg-white d-flex align-items-center justify-content-center me-2 shadow-sm text-secondary" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                                    <i class="bi bi-box"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h6 class="card-title text-truncate mb-0 fw-bold text-dark" style="font-size: 0.9rem;">
                                                <?= htmlspecialchars($inv['product_name']); ?>
                                            </h6>
                                        </div>
                                        <div class="mb-2">
                                            <h4 class="text-primary fw-bold mb-0" style="font-size: 1.25rem;">
                                                <?= number_format($qty, 2); ?> <span class="text-muted fw-normal" style="font-size: 0.75rem;"><?= htmlspecialchars($inv['unit_type']); ?></span>
                                            </h4>
                                            <span class="text-muted" style="font-size: 0.72rem;">Birim Maliyet: <strong><?= number_format($inv['unit_cost'] ?? 0, 2); ?> ₺</strong></span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="progress mb-1" style="height: 6px; border-radius: 3px;">
                                            <div class="progress-bar <?= $barColor; ?>" role="progressbar" style="width: <?= $fillPercent; ?>%"></div>
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

    <!-- Hata ve Başarı Mesajları -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Mağazalar Listesi -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-store text-success me-2"></i>Aktif Marketleriniz ve Mağazalarınız (<?= count($stores ?? []); ?>)</h5>
    </div>

    <div class="row g-4">
        <?php if (!empty($stores)): ?>
            <?php foreach ($stores as $store): ?>
                <?php
                $storeLevel = (int)($store['level'] ?? 1);
                $isBakkal = ($store['shop_type_id'] === 'bakkal');
                $baseCapacity = (float)($store['storage_capacity'] ?? ($isBakkal ? 500 : 2000));
                $currentStock = (float)($store['current_stock_volume'] ?? 0.00);
                $fillPercent = min(100, ($baseCapacity > 0 ? ($currentStock / $baseCapacity) * 100 : 0));
                $barColor = $fillPercent >= 90 ? 'bg-danger' : ($fillPercent >= 70 ? 'bg-warning' : 'bg-success');
                $isStoreActive = ((int)($store['status'] ?? 1)) === 1;
                $defaultMaxSlots = (int)($store['shop_info']['max_slots'] ?? ($isBakkal ? 1 : 4));
                $maxSlots = $defaultMaxSlots + (($storeLevel - 1) * 1);
                ?>
                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                    <div class="card market-card shadow-sm h-100 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <div class="rounded border bg-light d-flex align-items-center justify-content-center me-3 text-primary shadow-sm" style="width: 52px; height: 52px; font-size: 1.5rem;">
                                    <i class="fas fa-store-alt"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($store['store_name'] ?? 'Mağaza'); ?></h6>
                                            <span class="badge bg-secondary" style="font-size: 0.65rem;">Seviye <?= $storeLevel ?> (<?= $maxSlots ?> Slot)</span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border-0" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v text-muted"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <form action="/manager/app/market/upgradeStore" method="POST" class="px-3 py-1">
                                                        <input type="hidden" name="store_id" value="<?= $store['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success w-100 mb-1">Seviye Atla (<?= ($storeLevel + 1) * 25000; ?> ₺)</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="/manager/app/market/sellStore" method="POST" class="px-3 py-1" onsubmit="return confirm('Bu işletmeyi tasfiye etmek istediğinize emin misiniz?');">
                                                        <input type="hidden" name="store_id" value="<?= $store['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">İşletmeyi Tasfiye Et</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <span class="text-muted small"><i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($store['province_name'] ?? 'Bölge'); ?></span>
                                </div>
                            </div>

                            <!-- Sabit Giderler ve Depo Barı -->
                            <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded mb-3 small border">
                                <div>
                                    <span class="text-muted d-block" style="font-size: 0.7rem;">Sabit Giderler (Her 120 Satışta):</span>
                                    <span class="fw-bold text-danger">Kira: <?= number_format($store['shop_info']['base_rent'] ?? 3000, 0) ?> ₺ | Maaş: <?= number_format($store['shop_info']['base_salary'] ?? 8000, 0) ?> ₺</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 0.72rem;">
                                    <span>Depo Doluluğu (%<?= round($fillPercent, 1); ?>)</span>
                                    <span class="fw-bold text-dark"><?= number_format($currentStock, 0); ?> / <?= number_format($baseCapacity, 0); ?></span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 3px;">
                                    <div class="progress-bar <?= $barColor; ?>" style="width: <?= $fillPercent; ?>%;"></div>
                                </div>
                            </div>

                            <!-- SLOTLAR -->
                            <div class="p-2 border rounded mb-3 bg-light small">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark"><i class="fas fa-th-large me-1 text-primary"></i>Satış Rafları:</span>
                                </div>
                                <div class="row g-2">
                                    <?php 
                                    $storeSlots = $store['slots'] ?? [];
                                    $slotsMap = [];
                                    foreach ($storeSlots as $s) {
                                        $slotsMap[(int)$s['slot_index']] = $s;
                                    }

                                    for ($i = 1; $i <= $maxSlots; $i++):
                                        $slotItem = $slotsMap[$i] ?? null;
                                        $hasProduct = $slotItem && !empty($slotItem['product_id']) && (float)$slotItem['amount'] > 0;
                                        
                                        $productInterval = (int)($slotItem['sales_interval_seconds'] ?? 30);
                                        if ($productInterval <= 0) $productInterval = 30;
                                        
                                        $lastUpdatedTs = (int)($slotItem['last_updated_ts'] ?? $now);
                                        $elapsed = $now - $lastUpdatedTs;
                                        if ($elapsed < 0) $elapsed = 0;
                                        
                                        $remainingSeconds = $productInterval - ($elapsed % $productInterval);
                                        if ($remainingSeconds < 0) $remainingSeconds = 0;
                                    ?>
                                        <div class="col-6">
                                            <div class="slot-box p-2 position-relative h-100 d-flex flex-column justify-content-between">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-light text-dark border" style="font-size: 0.6rem;">Slot #<?= $i; ?></span>
                                                    <?php if ($hasProduct): ?>
                                                        <span class="badge bg-success" style="font-size: 0.55rem;">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary opacity-50" style="font-size: 0.55rem;">Boş</span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($hasProduct): ?>
                                                    <div class="my-1">
                                                        <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size: 0.8rem;"><?= htmlspecialchars($slotItem['product_name'] ?? 'Ürün'); ?></h6>
                                                        <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.72rem;">
                                                            <span class="text-muted">Miktar:</span>
                                                            <span class="fw-bold text-primary"><?= number_format($slotItem['amount'], 1); ?> <?= $slotItem['unit_type'] ?? ''; ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center" style="font-size: 0.72rem;">
                                                            <span class="text-muted">Fiyat:</span>
                                                            <span class="fw-bold text-success"><?= number_format($slotItem['current_sell_price'] ?? 0, 2); ?> ₺</span>
                                                        </div>

                                                        <?php if ($isStoreActive): ?>
                                                            <div class="market-item-timer-box pt-1 border-top mt-1" 
                                                                 data-total-duration="<?= $productInterval; ?>" 
                                                                 data-remaining-seconds="<?= $remainingSeconds; ?>">
                                                                <div class="d-flex justify-content-between text-muted" style="font-size: 0.62rem;">
                                                                    <span><i class="fas fa-hourglass-half me-1"></i>Döngü:</span>
                                                                    <span class="fw-bold text-primary market-item-timer-text">00:00</span>
                                                                </div>
                                                                <div class="progress mt-1" style="height: 3px;">
                                                                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated market-item-progress-bar" style="width: 0%;"></div>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-danger fw-bold mt-1" style="font-size: 0.62rem;">İşletme Kapalı</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-2 text-center">
                                                        <button type="button" class="btn btn-outline-success btn-sm py-0 px-1 w-100" style="font-size: 0.65rem;" onclick="openAssignModal(<?= $store['id']; ?>, <?= $slotItem['id'] ?? 0; ?>, <?= $i; ?>)">
                                                            <i class="fas fa-sync-alt me-1"></i> Mal Ekle / Güncelle
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-2 empty-slot-wrapper">
                                                        <p class="text-muted small mb-1" style="font-size: 0.65rem;">Raf Boş</p>
                                                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 fw-bold w-100" style="font-size: 0.7rem;" onclick="openAssignModal(<?= $store['id']; ?>, 0, <?= $i; ?>)">
                                                            <i class="fas fa-plus me-1"></i> Mal Çek
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Raporlar Butonu -->
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#reportModal-<?= $store['id']; ?>">
                                    <i class="fas fa-chart-bar me-1"></i> Raporlar & Bilanço
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BİLANÇO VE TÜM LOGLAR MODALI (Eksiksiz Geri Getirildi) -->
                <div class="modal fade" id="reportModal-<?= $store['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-dark text-white px-4 py-3">
                                <h5 class="modal-title fw-bold"><i class="fas fa-balance-scale me-2 text-warning"></i><?= htmlspecialchars($store['store_name']); ?> - Bilanço ve Gider Raporları</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body bg-light p-4">
                                <ul class="nav nav-tabs mb-3" role="tablist">
                                    <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#balance-pane-<?= $store['id']; ?>" type="button"><i class="fas fa-chart-pie me-1"></i> Bilanço & Giderler</button></li>
                                    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#arge-pane-<?= $store['id']; ?>" type="button"><i class="fas fa-certificate me-1"></i> AR-GE Kartları</button></li>
                                    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#purchases-pane-<?= $store['id']; ?>" type="button"><i class="fas fa-boxes me-1"></i> Mal Alım Logları</button></li>
                                    <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#sales-pane-<?= $store['id']; ?>" type="button"><i class="fas fa-receipt me-1"></i> Satış & Gider Logları</button></li>
                                </ul>
                                <div class="tab-content">
                                    <!-- 1. BİLANÇO SEKMESİ -->
                                    <div class="tab-pane fade show active" id="balance-pane-<?= $store['id']; ?>">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-3">
                                                <div class="p-3 bg-white border rounded shadow-sm">
                                                    <span class="text-muted d-block small">Toplam Brüt Ciro</span>
                                                    <h4 class="fw-bold text-success mb-0"><?= number_format($store['financials']['total_revenue'] ?? 0, 2); ?> ₺</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="p-3 bg-white border rounded shadow-sm">
                                                    <span class="text-muted d-block small">Ödenen Toplam Giderler</span>
                                                    <h4 class="fw-bold text-danger mb-0"><?= number_format($store['financials']['total_expenses_paid'] ?? 0, 2); ?> ₺</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="p-3 bg-white border rounded shadow-sm">
                                                    <span class="text-muted d-block small">Toplam Vergi & Nakliye</span>
                                                    <h4 class="fw-bold text-warning mb-0"><?= number_format(($store['financials']['total_tax_amount'] ?? 0) + ($store['financials']['total_transport_cost'] ?? 0), 2); ?> ₺</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="p-3 bg-white border rounded shadow-sm">
                                                    <span class="text-muted d-block small">Net Mağaza Kârı</span>
                                                    <h4 class="fw-bold text-primary mb-0"><?= number_format($store['financials']['total_net_profit'] ?? 0, 2); ?> ₺</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Ürün Bazlı Ciro Tablosu -->
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-white fw-bold">Ürün Bazlı Satış ve Ciro Dağılımı</div>
                                            <div class="card-body p-0">
                                                <table class="table table-striped small text-center mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-start ps-3">Ürün Adı</th>
                                                            <th>Satılan Miktar</th>
                                                            <th>Toplam Ciro (₺)</th>
                                                            <th>Net Kâr (₺)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($store['product_revenues'])): ?>
                                                            <?php foreach ($store['product_revenues'] as $prev): ?>
                                                                <tr>
                                                                    <td class="fw-bold text-start ps-3"><?= htmlspecialchars($prev['product_name']); ?></td>
                                                                    <td><?= number_format($prev['sold_qty'], 2); ?> <?= $prev['unit_type']; ?></td>
                                                                    <td class="fw-bold text-success"><?= number_format($prev['revenue'], 2); ?> ₺</td>
                                                                    <td class="text-primary">+<?= number_format($prev['net_profit'], 2); ?> ₺</td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="4" class="text-muted py-3">Bu mağazada henüz satış gerçekleştirilmedi.</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. AR-GE SEKMESİ -->
                                    <div class="tab-pane fade" id="arge-pane-<?= $store['id']; ?>">
                                        <div class="p-3 bg-white border rounded shadow-sm">
                                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-award text-warning me-2"></i>Mağaza AR-GE ve Optimizasyon Kartları</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php if (!empty($activeCards)): ?>
                                                    <?php foreach ($activeCards as $card): ?>
                                                        <div class="border rounded p-2 bg-light d-flex align-items-center gap-2">
                                                            <i class="fas fa-check-circle text-success fs-5"></i>
                                                            <div>
                                                                <h6 class="mb-0 fw-bold small"><?= htmlspecialchars($card['card_name'] ?? $card['name'] ?? 'Sertifika'); ?></h6>
                                                                <span class="text-muted" style="font-size: 0.68rem;">Etki: Aktif</span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Aktif AR-GE kartı bulunmuyor.</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 3. MAL ALIM LOGLARI SEKMESİ -->
                                    <div class="tab-pane fade" id="purchases-pane-<?= $store['id']; ?>">
                                        <div class="table-responsive bg-white border rounded p-2" style="max-height: 300px;">
                                            <table class="table table-striped small text-center mb-0">
                                                <thead><tr><th>Tarih</th><th>Ürün</th><th>Miktar</th><th>Birim Maliyet</th></tr></thead>
                                                <tbody>
                                                    <?php 
                                                    $purchaseLogs = array_filter($store['logs'] ?? [], fn($l) => ($l['sale_type'] ?? '') === 'stock_transfer');
                                                    if (!empty($purchaseLogs)):
                                                        foreach ($purchaseLogs as $log): 
                                                    ?>
                                                        <tr><td><?= $log['created_at']; ?></td><td><?= htmlspecialchars($log['product_name'] ?? 'Ürün'); ?></td><td><?= number_format($log['amount'], 2); ?></td><td><?= number_format($log['unit_price'] ?? 0, 2); ?> ₺</td></tr>
                                                    <?php 
                                                        endforeach;
                                                    else:
                                                    ?>
                                                        <tr><td colspan="4" class="text-muted py-3">Mal alım kaydı bulunmuyor.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- 4. SATIŞ & GİDER LOGLARI SEKMESİ -->
                                    <div class="tab-pane fade" id="sales-pane-<?= $store['id']; ?>">
                                        <div class="table-responsive bg-white border rounded p-2" style="max-height: 300px;">
                                            <table class="table table-striped small text-center mb-0">
                                                <thead><tr><th>Tarih</th><th>İşlem Türü</th><th>Tutar</th><th>Net Kâr</th></tr></thead>
                                                <tbody>
                                                    <?php 
                                                    $salesLogs = array_filter($store['logs'] ?? [], fn($l) => ($l['sale_type'] ?? '') !== 'stock_transfer');
                                                    if (!empty($salesLogs)):
                                                        foreach ($salesLogs as $log): 
                                                    ?>
                                                        <tr><td><?= $log['created_at']; ?></td><td><?= htmlspecialchars($log['sale_type']); ?></td><td><?= number_format($log['total_revenue'] ?? 0, 2); ?> ₺</td><td><?= number_format($log['net_profit'] ?? 0, 2); ?> ₺</td></tr>
                                                    <?php 
                                                        endforeach;
                                                    else:
                                                    ?>
                                                        <tr><td colspan="4" class="text-muted py-3">Satış veya gider kaydı bulunmuyor.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAL ÇEKİM MODALI -->
                <div class="modal fade" id="assignProductModal-<?= $store['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <form action="/manager/app/market/assignProduct" method="POST">
                                <div class="modal-header bg-dark text-white px-3 py-2">
                                    <h6 class="modal-title fw-bold">
                                        <?= htmlspecialchars($store['store_name'] ?? 'Mağaza'); ?> - Slot Mal Çekimi
                                    </h6>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4 bg-light">
                                    <input type="hidden" name="store_id" value="<?= $store['id']; ?>">
                                    <input type="hidden" name="slot_id" id="modal-slot-id-input-<?= $store['id']; ?>" value="">
                                    <input type="hidden" name="slot_index" id="modal-slot-index-input-<?= $store['id']; ?>" value="1">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-secondary">Merkez Depodan Ürün Seç</label>
                                        <select name="product_id" class="form-select form-select-sm" required>
                                            <option value="" disabled selected>-- Merkez Depo Ürünleri --</option>
                                            <?php if (!empty($inventory)): ?>
                                                <?php foreach ($inventory as $whProd): ?>
                                                    <option value="<?= $whProd['product_id']; ?>">
                                                        <?= htmlspecialchars($whProd['product_name']); ?> (Depo Stoğu: <?= number_format($whProd['quantity'], 1); ?> <?= $whProd['unit_type'] ?? '' ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-secondary">Çekilecek Miktar</label>
                                        <input type="number" step="0.01" name="transfer_amount" class="form-control form-control-sm" required min="1" value="10">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-secondary">Özel Perakende Satış Fiyatı (₺)</label>
                                        <input type="number" step="0.01" name="custom_sell_price" class="form-control form-control-sm" placeholder="Boş bırakılırsa otomatik kâr marjı uygulanır">
                                    </div>
                                </div>
                                <div class="modal-footer bg-white py-2">
                                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-primary btn-sm fw-bold">Mal Çekimini Tamamla</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-5"><h6>Henüz aktif mağazanız yok.</h6></div>
        <?php endif; ?>
    </div>
</div>

<!-- ŞİRKET KASA DEFTERİ MODALI -->
<div class="modal fade" id="companyCashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white px-4 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Şirket Kasa Hareketleri ve Defteri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded shadow-sm">
                            <span class="text-muted d-block small">Toplam Giren Para</span>
                            <h4 class="fw-bold text-success mb-0">+<?= number_format($company_cash_summary['total_in'] ?? 0, 2); ?> ₺</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded shadow-sm">
                            <span class="text-muted d-block small">Toplam Çıkan Para</span>
                            <h4 class="fw-bold text-danger mb-0">-<?= number_format($company_cash_summary['total_out'] ?? 0, 2); ?> ₺</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded shadow-sm">
                            <span class="text-muted d-block small">Net Kasa Akışı</span>
                            <h4 class="fw-bold text-primary mb-0"><?= number_format(($company_cash_summary['total_in'] ?? 0) - ($company_cash_summary['total_out'] ?? 0), 2); ?> ₺</h4>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 fw-bold text-dark">Son Finansal İşlem ve Log Kayıtları</div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-striped small text-center mb-0">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Kategori</th>
                                        <th>İşletme</th>
                                        <th class="text-start">Açıklama</th>
                                        <th>Tutar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($company_cash_logs)): ?>
                                        <?php foreach ($company_cash_logs as $log): 
                                            $isIn = ($log['transaction_type'] ?? '') === 'in';
                                        ?>
                                            <tr>
                                                <td><?= $log['created_at']; ?></td>
                                                <td class="fw-bold"><?= htmlspecialchars($log['category']); ?></td>
                                                <td><?= htmlspecialchars($log['store_name'] ?? 'Şirket Geneli'); ?></td>
                                                <td class="text-start"><?= htmlspecialchars($log['description'] ?? ''); ?></td>
                                                <td class="fw-bold <?= $isIn ? 'text-success' : 'text-danger'; ?>">
                                                    <?= $isIn ? '+' : '-'; ?><?= number_format($log['amount'], 2); ?> ₺
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-muted py-4">Kasa hareketi bulunmuyor.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- YENİ MARKET MODALI -->
<div class="modal fade" id="newMarketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="/manager/app/market/store" method="POST">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold">Yeni Mağaza Kur</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">İşletme Adı</label>
                        <input type="text" name="store_name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Mağaza Tipi</label>
                        <select name="store_type_id" class="form-select form-select-sm" required>
                            <option value="" disabled selected>-- Tür Seçin --</option>
                            <?php foreach ($shopTypes as $sType): ?>
                                <option value="<?= $sType['id']; ?>"><?= htmlspecialchars($sType['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">İl / Bölge</label>
                        <select name="province_id" class="form-select form-select-sm" required>
                            <option value="" disabled selected>-- İl Seçin --</option>
                            <?php foreach ($provinces as $prov): ?>
                                <option value="<?= $prov['id']; ?>"><?= htmlspecialchars($prov['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-white py-2">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold">Kur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openAssignModal(storeId, slotId, slotIndex) {
    const slotIndexInput = document.getElementById('modal-slot-index-input-' + storeId);
    const slotIdInput = document.getElementById('modal-slot-id-input-' + storeId);
    
    if (slotIndexInput) slotIndexInput.value = slotIndex;
    if (slotIdInput) slotIdInput.value = slotId;

    const modalElement = document.getElementById('assignProductModal-' + storeId);
    if (modalElement) {
        let modalInstance = bootstrap.Modal.getInstance(modalElement);
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalElement);
        }
        modalInstance.show();
    }
}

// 1'er saniye pürüzsüz azalan sayaç ve progress bar motoru
document.addEventListener("DOMContentLoaded", function () {
    const timerBoxes = document.querySelectorAll('.market-item-timer-box');
    let isReloading = false;

    timerBoxes.forEach(box => {
        let totalDuration = parseInt(box.getAttribute('data-total-duration')) || 30;
        let remainingSeconds = parseInt(box.getAttribute('data-remaining-seconds')) || 0;
        
        const timerText = box.querySelector('.market-item-timer-text');
        const progressBar = box.querySelector('.market-item-progress-bar');

        function updateTimer() {
            if (remainingSeconds <= 0 && !isReloading) {
                isReloading = true;
                location.reload();
                return;
            }

            let minutes = Math.floor(remainingSeconds / 60);
            let seconds = remainingSeconds % 60;

            if (timerText) {
                timerText.innerText = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
            }

            if (progressBar) {
                let elapsed = totalDuration - remainingSeconds;
                let percent = Math.min(100, Math.max(0, (elapsed / totalDuration) * 100));
                progressBar.style.width = percent + "%";
            }

            if (remainingSeconds > 0) {
                remainingSeconds--;
            }
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    });
});
</script>
</body>
</html>
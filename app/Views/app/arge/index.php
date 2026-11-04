<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Şirket AR-GE Merkezi ve Teknoloji Yatırımları</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    
    <!-- Üst Bilgi ve Kasa Durumu -->
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-microchip text-primary me-2"></i>AR-GE ve Teknoloji Merkezi</h3>
            <p class="text-muted small mb-0">Şirketinizin üretim verimliliğini artırmak ve maliyetleri düşürmek için teknoloji sertifikaları satın alın.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="card border-0 shadow-sm bg-white p-3 d-inline-block rounded-3">
                <span class="text-muted small d-block">Şirket Kasası</span>
                <h4 class="fw-bold text-success mb-0"><?= number_format($player_money, 2); ?> ₺</h4>
            </div>
        </div>
    </div>

    <!-- Uyarı Mesaj Alanı -->
    <div id="alertContainer"></div>

    <!-- AR-GE Kartları Listesi -->
    <div class="row">
        <?php if (!empty($arge_cards)): ?>
            <?php foreach ($arge_cards as $card): ?>
                <?php 
                    $isOwned = in_array($card['card_key'], $owned_keys);
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 rounded-3">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($card['card_name']); ?></h5>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1"><?= !empty($card['duration_seconds']) ? round($card['duration_seconds']/86400).' Gün' : 'Kalıcı'; ?></span>
                            </div>
                            
                            <p class="text-muted small mb-3 flex-grow-1"><?= htmlspecialchars($card['description'] ?? 'Açıklama bulunmuyor.'); ?></p>
                            
                            <!-- Etki Rozetleri -->
                            <div class="mb-3">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if ((float)$card['effect_output'] > 1.0): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success small">Üretim +%<?= number_format(((float)$card['effect_output'] - 1.0) * 100, 1); ?></span>
                                    <?php endif; ?>
                                    <?php if ((float)$card['effect_input_cons'] < 1.0): ?>
                                        <span class="badge bg-info bg-opacity-10 text-dark small">Girdi -%<?= number_format((1.0 - (float)$card['effect_input_cons']) * 100, 1); ?></span>
                                    <?php endif; ?>
                                    <?php if ((float)$card['effect_energy'] < 1.0): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark small">Enerji -%<?= number_format((1.0 - (float)$card['effect_energy']) * 100, 1); ?></span>
                                    <?php endif; ?>
                                    <?php if ((float)$card['effect_labor'] < 1.0): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-dark small">İşçilik -%<?= number_format((1.0 - (float)$card['effect_labor']) * 100, 1); ?></span>
                                    <?php endif; ?>
                                    <?php if ((float)$card['effect_transport'] < 1.0): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-dark small">Lojistik -%<?= number_format((1.0 - (float)$card['effect_transport']) * 100, 1); ?></span>
                                    <?php endif; ?>
                                    <?php if ((float)$card['effect_tax'] < 1.0): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger small">Vergi -%<?= number_format((1.0 - (float)$card['effect_tax']) * 100, 1); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                                <div>
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Yatırım Bedeli</span>
                                    <span class="fw-bold text-danger"><?= number_format((float)$card['price'], 2); ?> ₺</span>
                                </div>
                                <div>
                                    <?php if ($isOwned): ?>
                                        <button class="btn btn-secondary btn-sm fw-bold px-3" disabled>
                                            <i class="fas fa-check me-1"></i> Aktif
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm fw-bold px-3 buy-card-btn" data-id="<?= $card['id']; ?>">
                                            <i class="fas fa-flask me-1"></i> Araştır / Satın Al
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm text-center py-5">
                    <p class="text-muted mb-0">Şu anda araştırılabilecek herhangi bir AR-GE sertifikası bulunmuyor.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- AJAX Satın Alma İşlemi -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('.buy-card-btn').click(function() {
        const btn = $(this);
        const cardId = btn.data('id');

        if (!confirm('Bu AR-GE sertifikası için şirket kasasından yatırım yapmak istediğinize emin misiniz?')) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> İşleniyor...');

        $.ajax({
            url: '/manager/argecards/buy', // ArgeCardsController içerisindeki buy metoduna giden rota
            type: 'POST',
            data: { card_id: cardId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#alertContainer').html(`
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> ${response.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#alertContainer').html(`
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> ${response.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    btn.prop('disabled', false).html('<i class="fas fa-flask me-1"></i> Araştır / Satın Al');
                }
            },
            error: function() {
                alert('Bir hata oluştu, lütfen tekrar deneyin.');
                btn.prop('disabled', false).html('<i class="fas fa-flask me-1"></i> Araştır / Satın Al');
            }
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
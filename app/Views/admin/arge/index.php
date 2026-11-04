<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - AR-GE Sertifikaları ve Kart Yönetimi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    
    <!-- Üst Başlık ve Yeni Şablon Ekleme Butonu -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-flask text-primary me-2"></i>AR-GE Sertifikaları ve Kart Şablonları</h3>
            <p class="text-muted small mb-0">Oyuncuların AR-GE merkezinden satın alabileceği teknoloji ve sertifika şablonlarını yönetin.</p>
        </div>
        <a href="/manager/admin/arge/create" class="btn btn-primary fw-bold shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> Yeni AR-GE Kartı Ekle
        </a>
    </div>

    <!-- Kartlar Tablosu -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>#ID</th>
                            <th>Teknik Key</th>
                            <th>Kart Adı</th>
                            <th>Yatırım Bedeli</th>
                            <th>Süre</th>
                            <th>Üretim Etkisi</th>
                            <th>Girdi Tasarrufu</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cards)): ?>
                            <?php foreach ($cards as $card): ?>
                                <tr>
                                    <td><?= $card['id']; ?></td>
                                    <td><code><?= htmlspecialchars($card['card_key']); ?></code></td>
                                    <td class="fw-bold text-success text-start"><?= htmlspecialchars($card['card_name']); ?></td>
                                    <td class="fw-bold text-danger"><?= number_format((float)$card['price'], 2); ?> ₺</td>
                                    <td><small class="text-muted"><?= !empty($card['duration_seconds']) ? round($card['duration_seconds']/86400).' Gün' : 'Süresiz'; ?></small></td>
                                    <td><span class="badge bg-success">+%<?= number_format(((float)$card['effect_output'] - 1.0) * 100, 1); ?></span></td>
                                    <td><span class="badge bg-info text-dark">-%<?= number_format((1.0 - (float)$card['effect_input_cons']) * 100, 1); ?></span></td>
                                    <td>
                                        <a href="/manager/admin/arge/edit/<?= $card['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="<?= base_url('admin/arge/delete'); ?>" method="POST" class="d-inline" onsubmit="return confirm('Bu AR-GE şablonunu silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="card_id" value="<?= $card['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Sil">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Sistemde kayıtlı herhangi bir AR-GE şablonu bulunmuyor.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
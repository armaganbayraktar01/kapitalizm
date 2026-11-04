<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Yeni AR-GE Sertifikası Tanımla</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-flask text-primary me-2"></i>Yeni AR-GE Sertifikası Ekle</h3>
                    <p class="text-muted small mb-0">Oyuncuların AR-GE merkezinden araştırıp satın alabileceği yeni bir teknoloji şablonu oluşturun.</p>
                </div>
                <a href="/manager/admin/arge" class="btn btn-outline-dark btn-sm fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Listeye Dön
                </a>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <form action="<?= base_url('admin/arge/store'); ?>" method="POST">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Teknik Anahtar (Key - Benzersiz)</label>
                                <input type="text" class="form-control" name="card_key" placeholder="Örn: machinery_tarim" required>
                                <div class="form-text" style="font-size: 0.75rem;">Sistem içinde benzersiz olmalıdır.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Görünen Sertifika Adı</label>
                                <input type="text" class="form-control" name="card_name" placeholder="Örn: Makineli Tarım Sertifikası" required>
                            </div>
                        </div>

                        <!-- YENİ: Kategori Alanı -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Kart Kategorisi (Sektör)</label>
                                <select class="form-select" name="category" required>
                                    <option value="general">Genel (Tüm Tesisler)</option>
                                    <option value="farm">Tarım (Farm / Agri)</option>
                                    <option value="factory">Sanayi (Factory / Industrial)</option>
                                    <option value="mine">Maden (Mine / Extraction)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Yatırım Bedeli (₺)</label>
                                <input type="number" step="0.01" class="form-control" name="price" value="50000.00" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Açıklama / Etkiler</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Üretim miktarını %5 artırır, girdi maliyetini %2 düşürür."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Yatırım Bedeli (Şirket Kasasından Düşecek Tutar - ₺)</label>
                                <input type="number" step="0.01" class="form-control" name="price" value="50000.00" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Geçerlilik Süresi (Saniye)</label>
                                <input type="number" class="form-control" name="duration_seconds" placeholder="Boş bırakılırsa süresizdir">
                            </div>
                        </div>

                        <hr class="my-4 text-muted">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-sliders-h text-primary me-1"></i> Tüm Çarpan ve Faktör Katsayıları</h6>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">Üretim Miktarı (effect_output)</label>
                                <input type="number" step="0.01" class="form-control" name="effect_output" value="1.05" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">Girdi Tüketimi (effect_input_cons)</label>
                                <input type="number" step="0.01" class="form-control" name="effect_input_cons" value="0.98" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">Enerji Tüketimi (effect_energy)</label>
                                <input type="number" step="0.01" class="form-control" name="effect_energy" value="1.00" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">İşçilik Gideri (effect_labor)</label>
                                <input type="number" step="0.01" class="form-control" name="effect_labor" value="1.00" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">Lojistik Gideri (effect_transport)</label>
                                <input type="number" step="0.01" class="form-control" name="effect_transport" value="1.00" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold small">Vergi Oranı (effect_tax)</label>
                                <input type="number" step="0.01" class="form-control" name="effect_tax" value="1.00" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="/manager/admin/arge" class="btn btn-secondary px-4">İptal</a>
                            <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                                <i class="fas fa-save me-1"></i> Şablonu Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
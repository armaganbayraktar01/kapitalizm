<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-cogs me-2"></i> Yönetim ve Konfigürasyon Paneli</h2>
        <p class="text-muted">Projenizin altyapı ayarlarını ve site genel parametrelerini buradan yönetin.</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Sekme Başlıkları -->
<ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
            <i class="fas fa-globe me-1"></i> Genel Site Ayarları
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="env-tab" data-bs-toggle="tab" data-bs-target="#env" type="button" role="tab">
            <i class="fas fa-server me-1"></i> Altyapı (.env) Ayarları
        </button>
    </li>
</ul>

<!-- Sekme İçerikleri -->
<div class="tab-content" id="settingsTabContent">
    
    <!-- 1. SEKME: Genel Site Ayarları (Veritabanı tabanlı) -->
    <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="card border-0 shadow-sm col-md-8">
            <div class="card-body p-4">
                <form action="" method="POST">
                    <input type="hidden" name="form_type" value="general">
                    
                    <h5 class="mb-3 text-primary border-bottom pb-2">Site Parametreleri</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Site Başlığı (Site Title)</label>
                        <input type="text" name="site_title" class="form-control" value="<?= htmlspecialchars($dbSettings['site_title'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Site Açıklaması (Description)</label>
                        <textarea name="site_description" class="form-control" rows="2"><?= htmlspecialchars($dbSettings['site_description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin E-Posta Adresi</label>
                        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($dbSettings['admin_email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bakım Modu</label>
                        <select name="maintenance_mode" class="form-select">
                            <option value="0" <?= ($dbSettings['maintenance_mode'] ?? '0') == '0' ? 'selected' : '' ?>>Kapalı (Normal Yayın)</option>
                            <option value="1" <?= ($dbSettings['maintenance_mode'] ?? '0') == '1' ? 'selected' : '' ?>>Açık (Bakım Modunda)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i> Site Ayarlarını Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. SEKME: Altyapı Ayarları (.env tabanlı) -->
    <div class="tab-pane fade" id="env" role="tabpanel">
        <div class="card border-0 shadow-sm col-md-8">
            <div class="card-body p-4">
                <form action="" method="POST">
                    <input type="hidden" name="form_type" value="env">
                    
                    <h5 class="mb-3 text-danger border-bottom pb-2">Veritabanı & Sunucu Konfigürasyonu</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">DB Host</label>
                        <input type="text" name="DB_HOST" class="form-control" value="<?= htmlspecialchars($env['DB_HOST'] ?? 'localhost') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Veritabanı Adı (DB Name)</label>
                        <input type="text" name="DB_NAME" class="form-control" value="<?= htmlspecialchars($env['DB_NAME'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Veritabanı Kullanıcı Adı (DB User)</label>
                        <input type="text" name="DB_USER" class="form-control" value="<?= htmlspecialchars($env['DB_USER'] ?? 'root') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Veritabanı Şifresi (DB Pass)</label>
                        <input type="password" name="DB_PASS" class="form-control" value="<?= htmlspecialchars($env['DB_PASS'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Base URL</label>
                        <input type="text" name="BASE_URL" class="form-control" value="<?= htmlspecialchars($env['BASE_URL'] ?? 'http://localhost/manager') ?>" required>
                    </div>

                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-save me-2"></i> Altyapı Ayarlarını Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
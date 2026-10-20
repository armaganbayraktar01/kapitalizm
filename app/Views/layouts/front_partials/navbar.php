    <!-- Üst Menü / Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= base_url('app/index') ?>">
                <i class="fas fa-cubes me-2 text-primary"></i> <?= get_setting('site_title') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#frontNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="frontNavbar">
                <!-- <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('app/index') ?>">Ana Sayfa</a>
                    </li>
                </ul> -->

                <!-- Oturum ve Yetki Durumuna Göre Sağ Menü -->
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item text-white me-3 d-none d-lg-inline">
                            <span>Merhaba, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı') ?></strong></span>
                        </li>
                        <?php if (is_admin()): ?>
                            <li class="nav-item me-2">
                                <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-user-shield me-1"></i> Yönetim Paneli
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="<?= base_url('auth/logout') ?>" class="btn btn-sm btn-danger">
                                <i class="fas fa-sign-out-alt me-1"></i> Çıkış Yap
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item me-2">
                            <a href="<?= base_url('auth/login') ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Giriş Yap
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('auth/register') ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-user-plus me-1"></i> Kayıt Ol
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

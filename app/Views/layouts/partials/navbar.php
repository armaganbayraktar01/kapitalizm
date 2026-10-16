<nav class="navbar navbar-expand navbar-light bg-white px-4 shadow-sm py-3 mb-4">
    <div class="container-fluid px-0">
        <!-- Mobil Ekranlar İçin Sidebar Toggle Butonu -->
        <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <span class="navbar-text fw-semibold text-secondary">
            Yönetim Oturumu Aktif
        </span>
        
        <!-- Sağ Üst Kullanıcı Dropdown Menüsü -->
        <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-dark fw-bold d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-2 text-primary fs-5"></i> 
                    <span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center" href="<?= base_url('admin/profile') ?>">
                            <i class="fas fa-user me-2 text-muted"></i> Profilim
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center" href="<?= base_url('admin/settings') ?>">
                            <i class="fas fa-cogs me-2 text-muted"></i> Sistem Ayarları
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger d-flex align-items-center" href="<?= base_url('auth/logout') ?>">
                            <i class="fas fa-sign-out-alt me-2"></i> Güvenli Çıkış
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
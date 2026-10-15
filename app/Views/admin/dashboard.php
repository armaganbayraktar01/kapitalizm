<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'Admin Panel' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar / Menü -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark text-white sidebar collapse min-vh-100 p-3">
                <h4 class="text-center py-3 border-bottom border-secondary">Manager Panel</h4>
                <ul class="nav flex-column mt-3">
                    <li class="nav-item mb-2">
                        <a href="<?= base_url('admin/dashboard') ?>" class="nav-link text-white active bg-primary rounded">
                            <i class="fas fa-home me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="<?= base_url('home/index') ?>" class="nav-link text-white" target="_blank">
                            <i class="fas fa-globe me-2"></i> Siteye Git
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a href="<?= base_url('auth/logout') ?>" class="nav-link text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Çıkış Yap
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Ana İçerik Alanı -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Hoş Geldiniz, <?= htmlspecialchars($username) ?></h1>
                    <span class="badge bg-success fs-6">Admin Yetkisi Aktif</span>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Yetki Durumu</h5>
                                <p class="card-text fs-5 fw-bold text-success">Tam Yetkili (Admin)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

</body>
</html>
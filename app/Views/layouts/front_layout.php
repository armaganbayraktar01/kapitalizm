<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? get_setting('site_title') ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
        }
    </style>
</head>
<body>

    <!-- Üst Menü / Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= base_url('home/index') ?>">
                <i class="fas fa-cubes me-2 text-primary"></i> <?= get_setting('site_title') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#frontNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="frontNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('home/index') ?>">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('admin/dashboard') ?>" target="_blank">
                            <i class="fas fa-user-shield me-1"></i> Yönetim Paneli
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Ana İçerik Alanı -->
    <main>
        <?= $content ?? '' ?>
    </main>

    <!-- Alt Alan (Footer) -->
    <footer class="bg-white text-muted py-4 border-top mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= get_setting('site_title') ?>. Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
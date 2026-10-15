<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'Manager' ?></title>
    <!-- Bootstrap v5 CDN (Hızlı test için) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<?php if (is_logged_in()): ?>
    <div class="d-flex align-items-center gap-3">
        <span>Merhaba, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
        <?php if (is_admin()): ?>
            <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-sm btn-outline-primary">Yönetim Paneli</a>
        <?php endif; ?>
        <a href="<?= base_url('auth/logout') ?>" class="btn btn-sm btn-danger">Çıkış Yap</a>
    </div>
<?php else: ?>
    <div>
        <a href="<?= base_url('auth/login') ?>" class="btn btn-sm btn-primary">Giriş Yap</a>
        <a href="<?= base_url('auth/register') ?>" class="btn btn-sm btn-success">Kayıt Ol</a>
    </div>
<?php endif; ?>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h1 class="text-primary mb-3">Manager Projesi</h1>
                <p class="lead"><?= $message ?></p>
                <hr>
                <span class="badge bg-success">MVC + Autoloader Aktif</span>
            </div>
        </div>
    </div>
</body>
</html>
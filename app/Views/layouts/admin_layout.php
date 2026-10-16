<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Admin Paneli' ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome İkonları -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { min-height: 100vh; background-color: #f8f9fa; }
        #sidebar { width: 260px; min-height: 100vh; background: #212529; transition: all 0.3s; }
        #sidebar .nav-link { color: #adb5bd; padding: 10px 15px; border-radius: 6px; margin-bottom: 5px; }
        #sidebar .nav-link:hover, #sidebar .nav-link.active { color: #fff; background-color: #0d6efd; }
        .main-content { flex: 1; }
    </style>

    <!-- jQuery ve jQuery UI CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

</head>
<body>

    <div class="d-flex">
        <!-- SOL SIDEBAR (Parçacıktan dahil edilir) -->
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- SAĞ ALAN -->
        <div class="main-content d-flex flex-column">
            <?php include __DIR__ . '/partials/navbar.php'; ?>

            <div class="container-fluid p-4">
                <?= $content ?? '' ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
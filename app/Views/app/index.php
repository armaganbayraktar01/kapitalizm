<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'Manager' ?></title>
    <!-- Bootstrap v5 CDN (Hızlı test için) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
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
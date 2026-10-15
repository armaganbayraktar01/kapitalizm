<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center py-5" style="height: 100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">Giriş Yap</h3>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger py-2"><?= $error ?></div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label">E-posta Adresi</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="<?= base_url('auth/register') ?>" class="text-decoration-none">Hesabın yok mu? Kayıt ol</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
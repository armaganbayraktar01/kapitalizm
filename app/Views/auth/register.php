
<!-- Kayıt Kartı Bölümü -->
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-8 col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4 fw-semibold text-secondary">Kayıt Ol</h3>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2"><?= $error ?></div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-medium">Kullanıcı Adı</label>
                            <input type="text" name="username" class="form-control py-2" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-medium">E-posta Adresi</label>
                            <input type="email" name="email" class="form-control py-2" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-medium">Şifre</label>
                            <input type="password" name="password" class="form-control py-2" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2 mt-2">Kayıt Ol</button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="<?= base_url('auth/login') ?>" class="text-decoration-none small">Zaten hesabın var mı? Giriş yap</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



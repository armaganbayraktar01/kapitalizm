<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-file-alt me-2"></i> Sayfa Yönetimi</h2>
        <p class="text-muted">Statik içerik sayfalarını veya özel modül/controller eşlemelerini buradan yönetin.</p>
    </div>
    <a href="<?= base_url('admin/pageCreate') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Yeni Sayfa Ekle
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Sayfa Başlığı</th>
                        <th>URL Slug</th>
                        <th>Tip</th>
                        <th>Bağlı Menü</th>
                        <th>Durum</th>
                        <th class="text-end pe-3">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pages)): ?>
                        <?php foreach ($pages as $p): ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?= $p['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($p['title']) ?></td>
                                <td><code><?= htmlspecialchars($p['slug']) ?></code></td>
                                <td>
                                    <?php if ($p['page_type'] === 'static'): ?>
                                        <span class="badge bg-info text-dark">Statik İçerik</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Modül (<?= htmlspecialchars($p['controller_name']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['menu_title'] ?? 'Bağlı Değil') ?></td>
                                <td>
                                    <?php if ($p['status'] == 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= base_url('admin/pageEdit/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?= base_url('admin/pageDelete/' . $p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu sayfayı silmek istediğinize emin misiniz?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Kayıtlı sayfa bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
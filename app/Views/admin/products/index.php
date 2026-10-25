<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold m-0"><i class="fas fa-boxes me-2 text-primary"></i>Ürün Kataloğu & Reçeteler</h1>
        <a href="<?= base_url('admin/product/create'); ?>" class="btn btn-primary fw-bold">
            <i class="fas fa-plus me-1"></i> Yeni Ürün & Reçete Ekle
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">Görsel</th>
                            <th>ID</th>
                            <th>Ürün Adı</th>
                            <th>Tür</th>
                            <th>Taban Fiyat</th>
                            <th>Üretim Reçetesi (Girdiler $\rightarrow$ Çıktı)</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Henüz kayıtlı bir ürün bulunmuyor.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <img src="<?= base_url('public/uploads/products/' . ($p['image'] ?: 'default.png')); ?>" alt="" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;">
                                    </td>
                                    <td><?= $p['id']; ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($p['name']); ?></td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-secondary';
                                            if ($p['type'] === 'raw') $badgeClass = 'bg-success';
                                            elseif ($p['type'] === 'semi') $badgeClass = 'bg-info text-dark';
                                            elseif ($p['type'] === 'finished') $badgeClass = 'bg-primary';
                                            elseif ($p['type'] === 'utility') $badgeClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $badgeClass; ?> text-uppercase"><?= htmlspecialchars($p['type']); ?></span>
                                    </td>
                                    <td><?= number_format($p['base_price'], 2); ?> TL</td>
                                    <td>
                                        <?php if (!empty($p['recipe_items'])): ?>
                                            <div class="small">
                                                <span class="text-muted">Girdiler:</span>
                                                <?php 
                                                    $parts = [];
                                                    foreach ($p['recipe_items'] as $item) {
                                                        $parts[] = '<strong>' . $item['quantity_required'] . 'x</strong> ' . htmlspecialchars($item['item_name']);
                                                    }
                                                    echo implode(' + ', $parts);
                                                ?>
                                                <br>
                                                <span class="text-success fw-bold">Çıktı: <?= $p['output_quantity']; ?> Adet <?= htmlspecialchars($p['name']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic small">Reçete yok (Hammadde / Kaynak)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= base_url('admin/product/edit/' . $p['id']); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= base_url('admin/product/delete/' . $p['id']); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
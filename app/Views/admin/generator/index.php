<div class="container-fluid px-4 py-4">
    <!-- Üst Başlık ve Buton -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><?= $title ?? 'Modül Yönetimi' ?></h2>
            <p class="text-muted small mb-0">Sistemdeki modüllerinizi listeleyebilir, sürükle-bırak ile sıralayabilir ve yenilerini üretebilirsiniz.</p>
        </div>
        <a href="<?= base_url('admin/modules/create') ?>" class="btn btn-success shadow-sm fw-bold">
            <i class="fas fa-magic me-2"></i>Yeni Modül Üret (RAD Sihirbazı)
        </a>
    </div>

    <!-- Modül Listesi Kartı -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase fs-7 text-secondary">
                        <tr>
                            <th class="py-3 px-4" style="width: 60px;"><i class="fas fa-grip-vertical"></i></th>
                            <th class="py-3">Modül Başlığı</th>
                            <th class="py-3">Rota (URL)</th>
                            <th class="py-3">İkon</th>
                            <th class="py-3 text-end px-4" style="width: 160px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-menu">
                        <?php if (!empty($menus)): ?>
                            <?php foreach ($menus as $menu): ?>
                                <tr id="menu-<?= $menu['id'] ?>" class="align-middle" style="cursor: grab;">
                                    <td class="px-4 text-muted">
                                        <i class="fas fa-bars handle" style="cursor: grab; padding: 10px;"></i>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            <i class="<?= htmlspecialchars($menu['icon'] ?? 'fas fa-folder') ?> me-2 text-primary"></i> 
                                            <?= htmlspecialchars($menu['title']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code class="text-primary bg-light px-2 py-1 rounded"><?= htmlspecialchars($menu['url']) ?></code>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?= htmlspecialchars($menu['icon'] ?? '-') ?></span>
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('admin/modules/edit/' . $menu['id']) ?>" class="btn btn-sm btn-outline-warning" title="Modülü Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= base_url('admin/modules/delete/' . $menu['id']) ?>" class="btn btn-sm btn-outline-danger" title="Modülü Kaldır" onclick="return confirm('Bu modülü silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                    Henüz kayıtlı modül bulunmuyor.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- jQuery ve jQuery UI (Sürükle Bırak İçin) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    $("#sortable-menu").sortable({
        handle: ".handle",
        axis: "y",
        helper: function(e, ui) {
            ui.children().each(function() {
                $(this).width($(this).width());
            });
            return ui;
        },
        update: function(event, ui) {
            var sortedIDs = $("#sortable-menu").sortable("toArray", {attribute: "id"});
            var cleanOrder = sortedIDs.map(function(id) {
                return id.replace('menu-', '');
            });

            $.ajax({
                url: "<?= base_url('admin/modules/sort') ?>",
                type: 'POST',
                dataType: 'json',
                data: { order: cleanOrder },
                success: function(response) {
                    if (response.status === 'success') {
                        console.log("Sıralama güncellendi.");
                    }
                }
            });
        }
    }).disableSelection();
});
</script>

<style>
.ui-sortable-helper {
    background-color: #ffffff !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>
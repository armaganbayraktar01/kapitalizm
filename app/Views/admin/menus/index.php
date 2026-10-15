<div class="container-fluid px-4 py-4">
    <!-- Üst Başlık ve Buton Alanı -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><?= $title ?? 'Menü Yönetimi' ?></h2>
            <p class="text-muted small mb-0">Sol taraftaki tutamaç ikonundan tutarak menülerinizi sürükleyip bırakabilirsiniz.</p>
        </div>
        <a href="<?= base_url('admin/menus/create') ?>" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus me-2"></i>Yeni Menü Ekle
        </a>
    </div>

    <!-- Menü Tablosu Kartı -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase fs-7 text-secondary">
                        <tr>
                            <th class="py-3 px-4" style="width: 60px;"><i class="fas fa-grip-vertical"></i></th>
                            <th class="py-3">Menü Başlığı</th>
                            <th class="py-3">URL / Link</th>
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
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($menu['title']) ?></span>
                                    </td>
                                    <td>
                                        <code class="text-primary bg-light px-2 py-1 rounded"><?= htmlspecialchars($menu['url']) ?></code>
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('admin/menus/edit/' . $menu['id']) ?>" class="btn btn-sm btn-outline-warning" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= base_url('admin/menus/delete/' . $menu['id']) ?>" class="btn btn-sm btn-outline-danger" title="Sil" onclick="return confirm('Bu menüyü silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                    Kayıtlı menü bulunamadı.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- jQuery ve jQuery UI CDN -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    $("#sortable-menu").sortable({
        handle: ".handle", // Sadece <i> ikonundan tutularak sürüklenebilir
        axis: "y",         // Sadece dikey (yukarı-aşağı) sürüklemeye izin ver
        helper: function(e, ui) {
            // Sürükleme esnasında tablonun sütun genişliklerinin bozulmasını engeller
            ui.children().each(function() {
                $(this).width($(this).width());
            });
            return ui;
        },
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            var sortedIDs = $("#sortable-menu").sortable("toArray", {attribute: "id"});
            var cleanOrder = sortedIDs.map(function(id) {
                return id.replace('menu-', '');
            });

            $.ajax({
                url: "<?= base_url('admin/menus/sort') ?>",
                type: 'POST',
                dataType: 'json',
                data: { order: cleanOrder },
                success: function(response) {
                    if (response.status === 'success') {
                        console.log("Sıralama başarıyla kaydedildi.");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Sıralama hatası: " + error);
                }
            });
        }
    }).disableSelection();
});
</script>

<style>
/* Sürükleme esnasındaki görünüm ve yer tutucu efekti */
.ui-sortable-helper {
    background-color: #ffffff !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
.ui-state-highlight {
    height: 50px;
    background-color: #f8f9fa;
    border: 2px dashed #dee2e6;
}
</style>
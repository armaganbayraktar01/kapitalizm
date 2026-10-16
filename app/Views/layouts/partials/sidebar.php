<nav id="sidebar" class="p-3 text-white d-none d-md-block">
    <div class="text-center py-2 mb-1 border-bottom border-secondary">
        <a class="navbar-brand fw-bold" href="<?= base_url('app/index') ?>">
            <i class="fas fa-cubes me-0 fw-bold text-primary"></i> <?= get_setting('site_title') ?> </br> Admin Paneli
        </a>
    </div>
    <ul class="nav flex-column">
        <?php 
        // Controller'dan gelen veya burada modelden çekilen hiyerarşik menüleri basalım
        $menuModel = new \App\Models\Module();
        $hierarchicalMenus = $menuModel->getActiveHierarchicalMenus();

        if (!empty($hierarchicalMenus)) {
            render_menu_tree($hierarchicalMenus);
        }
        ?>
    </ul>
</nav>
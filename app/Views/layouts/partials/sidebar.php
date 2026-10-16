<nav id="sidebar" class="p-3 text-white d-none d-md-block">
    <div class="text-center py-3 mb-3 border-bottom border-secondary">
        <h4 class="m-0 fw-bold text-primary">ManagerPanel</h4>
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
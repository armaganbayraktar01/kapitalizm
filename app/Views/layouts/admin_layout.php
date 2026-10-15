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

        <!-- SOL SIDEBAR -->
        <nav id="sidebar" class="p-3 text-white d-none d-md-block">
            <div class="text-center py-3 mb-3 border-bottom border-secondary">
                <h4 class="m-0 fw-bold text-primary">ManagerPanel</h4>
            </div>
            <ul class="nav flex-column">
                <?php 
                    $menuModel = new \App\Models\Menu();
                    $allMenus = $menuModel->getActiveMenus();
                    
                    // Ana menüleri ve alt menüleri ayıralım
                    $parents = [];
                    $children = [];

                    foreach ($allMenus as $menu) {
                        if (empty($menu['parent_id'])) {
                            $parents[] = $menu;
                        } else {
                            $children[$menu['parent_id']][] = $menu;
                        }
                    }

                    foreach ($parents as $parent):
                        $hasChild = isset($children[$parent['id']]);
                        $isParentActive = strpos($_SERVER['REQUEST_URI'], $parent['url']) !== false;
                        
                        // Eğer alt menülerden biri aktifse ana menü de aktif görünmeli
                        if ($hasChild) {
                            foreach ($children[$parent['id']] as $child) {
                                if (strpos($_SERVER['REQUEST_URI'], $child['url']) !== false) {
                                    $isParentActive = true;
                                }
                            }
                        }
                ?>
                <li class="nav-item mb-1">
                    <?php if ($hasChild): ?>
                        <!-- Alt Menüsü Olan Ana Menü (Collapse Toggle) -->
                        <a href="#menuCollapse<?= $parent['id'] ?>" data-bs-toggle="collapse" class="nav-link d-flex justify-content-between align-items-center <?= $isParentActive ? 'active' : '' ?>">
                            <span><i class="<?= htmlspecialchars($parent['icon']) ?> me-2"></i> <?= htmlspecialchars($parent['title']) ?></span>
                            <i class="fas fa-chevron-down small"></i>
                        </a>
                        <div class="collapse <?= $isParentActive ? 'show' : '' ?>" id="menuCollapse<?= $parent['id'] ?>">
                            <ul class="nav flex-column ms-3 ps-2 border-start border-secondary mt-1">
                                <?php foreach ($children[$parent['id']] as $child): 
                                    $isChildActive = strpos($_SERVER['REQUEST_URI'], $child['url']) !== false;
                                ?>
                                    <li class="nav-item my-1">
                                        <a href="<?= base_url($child['url']) ?>" class="nav-link py-1 <?= $isChildActive ? 'active text-white' : 'nav-link' ?>" target="<?= $child['target'] ?>">
                                            <i class="<?= htmlspecialchars($child['icon']) ?> me-2 small"></i> <?= htmlspecialchars($child['title']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Normal Tekil Menü -->
                        <a href="<?= base_url($parent['url']) ?>" class="nav-link <?= $isParentActive ? 'active' : '' ?>" target="<?= $parent['target'] ?>">
                            <i class="<?= htmlspecialchars($parent['icon']) ?> me-2"></i> <?= htmlspecialchars($parent['title']) ?>
                        </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- SAĞ ALAN (Navbar + Dinamik İçerik) -->
        <div class="main-content d-flex flex-column">
            <!-- ÜST NAVBAR -->
            <nav class="navbar navbar-expand navbar-light bg-white px-4 shadow-sm py-3">
                <div class="container-fluid px-0">
                    <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-text fw-semibold text-secondary">
                        Yönetim Oturumu Aktif
                    </span>
                    
                    <!-- Sağ Üst Kullanıcı Dropdown -->
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-dark fw-bold" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1 text-primary fs-5"></i> 
                                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item" href="#"><i class="fas.fa-user me-2 text-muted"></i> Profilim</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= base_url('auth/logout') ?>"><i class="fas fa-sign-out-alt me-2"></i> Çıkış Yap</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

<!-- DİNAMİK İÇERİK ALANI -->
            <div class="container-fluid p-4">
                <?php 
                    // Temel Controller sınıfından gelen $content değişkenini buraya basıyoruz
                    echo $content ?? ''; 
                ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $baseUrl = 'http://localhost/manager';
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void {
        header("Location: " . base_url($path));
        exit;
    }
}


// Oturum açan kullanıcının giriş durumunu ve rolünü
// hızlıca sorgulayabileceğimiz fonksiyonlar

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): void {
        if (!is_logged_in()) {
            redirect('auth/login');
        }
    }
}

if (!function_exists('admin_check')) {
    function admin_check(): void {
        if (!is_logged_in()) {
            redirect('auth/login');
        }
        if (!is_admin()) {
            // Admin değilse ana sayfaya veya hata sayfasına yönlendir
            redirect('home/index');
        }
    }
}

//setting helper
// Projenin herhangi bir yerinde (view dosyalarında veya controller'larda) get_setting('site_title') diyerek veritabanındaki ayarı anında okuyabilmek için system/Helpers/functions.php dosyasına şu fonksiyonu ekleyelim:

if (!function_exists('get_setting')) {
    function get_setting(string $key, $default = '') {
        // Performans için veritabanına sürekli gitmemek adına static önbellekleme (Cache) yapabiliriz
        static $settings = null;
        
        if ($settings === null) {
            try {
                $db = \System\Core\Database::getInstance();
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Exception $e) {
                $settings = [];
            }
        }

        return $settings[$key] ?? $default;
    }
}
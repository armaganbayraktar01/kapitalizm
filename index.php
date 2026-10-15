<?php
session_start();

// Hata gösterimi (Localhost için açık)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Profesyonel ve Esnek Autoloader
spl_autoload_register(function ($class) {
    // Örnek gelen $class: System\Core\Router veya App\Controllers\HomeController
    
    // Ters bölüleri (\) dizin ayracına (/) çeviriyoruz
    $class = str_replace('\\', '/', $class);
    
    // İlk ana klasör adını küçük harfe çevirerek sistem/app klasörleriyle eşleştiriyoruz
    // System/Core/Router -> system/Core/Router.php
    // App/Controllers/HomeController -> app/Controllers/HomeController.php
    $classParts = explode('/', $class);
    if (!empty($classParts[0])) {
        $classParts[0] = strtolower($classParts[0]); // System -> system, App -> app
    }
    
    $file = __DIR__ . '/' . implode('/', $classParts) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/system/Helpers/functions.php';

// Router sınıfını başlat
use System\Core\Router;

$router = new Router();
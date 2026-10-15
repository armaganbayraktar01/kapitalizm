<?php
session_start();

// Hata gösterimi (Localhost ortamında olduğumuz için açık tutuyoruz)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Otomatik yükleyici (Autoloader) veya dosya dahil etme
require_once __DIR__ . '/system/Helpers/functions.php';
require_once __DIR__ . '/system/Core/Database.php';

// Test amaçlı URL yakalama
$url = $_GET['url'] ?? 'home';
echo "Proje başarıyla ayarlandı! Gelen URL: " . htmlspecialchars($url);
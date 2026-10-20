<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class AdminController extends Controller {

    public function __construct() {
        //admin_check();
    }

    // Admin Panel Ana Sayfası / Dashboard
    public function dashboard() {
        $data = [
            'title' => 'Yönetim Paneli - Dashboard'
        ];
        $this->adminView('admin/dashboard', $data);
    }

    // ==========================================
    // SİSTEM VE SİTE AYARLARI
    // ==========================================

    public function settings() {
        $envPath = __DIR__ . '/../../.env';
        $message = '';
        $error = '';

        $settingModel = $this->model('Setting');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $type = $_POST['form_type'] ?? '';

            if ($type === 'env') {
                $dbHost = trim($_POST['DB_HOST'] ?? '');
                $dbName = trim($_POST['DB_NAME'] ?? '');
                $dbUser = trim($_POST['DB_USER'] ?? '');
                $dbPass = trim($_POST['DB_PASS'] ?? '');
                $baseUrl = trim($_POST['BASE_URL'] ?? '');

                $envContent = "DB_HOST={$dbHost}\n";
                $envContent .= "DB_NAME={$dbName}\n";
                $envContent .= "DB_USER={$dbUser}\n";
                $envContent .= "DB_PASS={$dbPass}\n";
                $envContent .= "BASE_URL={$baseUrl}\n";

                if (file_put_contents($envPath, $envContent) !== false) {
                    $message = 'Altyapı (.env) ayarları başarıyla güncellendi!';
                } else {
                    $error = '.env dosyası güncellenirken hata oluştu.';
                }
            } elseif ($type === 'general') {
                unset($_POST['form_type']);
                $settingModel->updateBatch($_POST);
                $message = 'Sistem ayarları başarıyla güncellendi!';
            }
        }

        $envData = [];
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                list($key, $value) = explode('=', $line, 2) + ['', ''];
                $envData[trim($key)] = trim($value);
            }
        }

        $dbSettings = $settingModel->getAll();

        $data = [
            'title' => 'Sistem ve Site Ayarları',
            'env' => $envData,
            'dbSettings' => $dbSettings,
            'message' => $message,
            'error' => $error
        ];

        $this->adminView('admin/settings', $data);
    }
}
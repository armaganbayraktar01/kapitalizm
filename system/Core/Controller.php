<?php
namespace System\Core;

class Controller {

    // Model yükleme fonksiyonu
    public function model(string $model) {
        $modelClass = "App\\Models\\" . $model;
        if (class_exists($modelClass)) {
            return new $modelClass();
        }
        throw new \Exception("Model bulunamadı: {$modelClass}");
    }

    // Ön yüz görünüm ve layout yükleme metodu
    public function view(string $viewName, array $data = []) {
        // Verileri view dosyası içinde değişken olarak açalım
        if (!empty($data)) {
            extract($data);
        }

        $viewFile = __DIR__ . '/../../app/Views/' . $viewName . '.php';
        
        if (file_exists($viewFile)) {
            // Görünümün içeriğini tampona (output buffering) alalım
            ob_start();
            require_once $viewFile;
            $content = ob_get_clean();

            // Ön yüz ana şablonunu (layout) yükleyelim ve içine $content değişkenini basalım
            $layoutFile = __DIR__ . '/../../app/Views/layouts/front_layout.php';
            if (file_exists($layoutFile)) {
                require_once $layoutFile;
            } else {
                echo $content; // Layout yoksa direkt içeriği bas
            }
        } else {
            throw new \Exception("Görünüm dosyası bulunamadı: {$viewName}");
        }
    }

    // Admin paneli için ayrı bir layout yükleme metodu (Daha önce kurduğumuz yapı)
    public function adminView(string $viewName, array $data = []) {
        if (!empty($data)) {
            extract($data);
        }

        $viewFile = __DIR__ . '/../../app/Views/' . $viewName . '.php';
        
        if (file_exists($viewFile)) {
            ob_start();
            require_once $viewFile;
            $content = ob_get_clean();

            $layoutFile = __DIR__ . '/../../app/Views/layouts/admin_layout.php';
            if (file_exists($layoutFile)) {
                require_once $layoutFile;
            } else {
                echo $content;
            }
        } else {
            throw new \Exception("Admin görünüm dosyası bulunamadı: {$viewName}");
        }
    }
}
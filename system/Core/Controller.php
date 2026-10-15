<?php
namespace System\Core;

class Controller {
    /**
     * Model sınıflarını yüklemek için yardımcı metod
     */
    public function model(string $modelName) {
        $modelClass = "App\\Models\\" . $modelName;
        if (class_exists($modelClass)) {
            return new $modelClass();
        }
        throw new \Exception("Model bulunamadı: " . $modelName);
    }

    /**
     * View (Görünüm) dosyalarını yüklemek ve veri göndermek için metod
     */
    public function view(string $viewName, array $data = []) {
        // Dizideki anahtarları değişken haline getirir (örn: $data['title'] -> $title olur)
        extract($data);

        $viewFile = __DIR__ . '/../../app/Views/' . $viewName . '.php';

        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            throw new \Exception("View dosyası bulunamadı: " . $viewName);
        }
    }
}
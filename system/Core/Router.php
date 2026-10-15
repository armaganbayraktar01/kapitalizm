<?php
namespace System\Core;

class Router {
    protected $controller = 'HomeController'; // String veya Obje alabilmesi için tipini kaldırdık
    protected string $method = 'index';            
    protected array $params = [];                  

    public function __construct() {
        $url = $this->parseUrl();

        if (isset($url[0])) {
            $controllerName = ucfirst(strtolower($url[0])) . 'Controller';
            $controllerFile = __DIR__ . '/../../app/Controllers/' . $controllerName . '.php';

            if (file_exists($controllerFile)) {
                $this->controller = $controllerName;
                unset($url[0]);
            }
        }

        // Sınıfın dosya yolu autoloader sayesinde otomatik yükleniyor
        $controllerClass = "App\\Controllers\\" . $this->controller;
        
        // Burada nesne oluşturup $this->controller değişkenine atıyoruz
        $this->controller = new $controllerClass();

        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        $this->params = $url ? array_values($url) : [];

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    protected function parseUrl(): array {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }
}
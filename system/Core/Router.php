<?php
// namespace System\Core;

// use App\Models\Page;

// class Router {
//     protected $controller = 'AppController'; 
//     protected string $method = 'index';            
//     protected array $params = [];                

//     public function __construct() {
//         $url = $this->parseUrl();

//         // 1. Controller Tespiti
//         if (isset($url[0]) && strtolower($url[0]) === 'admin') {
//             $controllerName = 'AdminController'; // Varsayılan admin yöneticisi
            
//             if (isset($url[1])) {
//                 //$possibleController = ucfirst(strtolower($url[1])) . 'Controller';
//                 //$possibleFile = __DIR__ . '/../../app/Controllers/' . $possibleController . '.php';

//                 $inputName = ucfirst(strtolower($url[1]));
//                 $possibleController = $inputName . 'Controller'; // Örn: BuildingController
//                 $possibleFile = __DIR__ . '/../../app/Controllers/' . $possibleController . '.php';

//                 // Eğer tekil hali yoksa çoğul dene (BuildingsController)
//                 if (!file_exists($possibleFile)) {
//                     $possibleController = $inputName . 'sController';
//                     $possibleFile = __DIR__ . '/../../app/Controllers/' . $possibleController . '.php';
//                 }
              

//                 // Eğer ikinci kelimeye ait gerçek bir Controller varsa (Örn: GeneratorController)
//                 if (file_exists($possibleFile)) {
//                     $controllerName = $possibleController;
//                     unset($url[0], $url[1]); // admin ve controller adını düş
//                 } else {
//                     // Yoksa (Örn: admin/dashboard), sadece 'admin' kelimesini düş, kalanı AdminController yürütsün
//                     unset($url[0]);
//                 }
//             } else {
//                 unset($url[0]);
//             }

//             $this->controller = $controllerName;
//             $url = array_values($url);
//         } else {
//             // Standart ön yüz controller tespiti
//             if (isset($url[0])) {
//                 $controllerName = ucfirst(strtolower($url[0])) . 'Controller';
//                 $controllerFile = __DIR__ . '/../../app/Controllers/' . $controllerName . '.php';

//                 if (file_exists($controllerFile)) {
//                     $this->controller = $controllerName;
//                     unset($url[0]);
//                     $url = array_values($url); 
//                 } else {
//                     // Fiziksel controller yoksa dinamik sayfa sistemine bak (CMS Page Slug)
//                     $fullSlug = implode('/', $url);
//                     $pageModel = new Page();
//                     $page = $pageModel->findBySlug($fullSlug);

//                     if ($page) {
//                         if ($page['page_type'] === 'static') {
//                             $this->controller = 'PageController';
//                             $this->method = 'show';
//                             $this->params = [$page];
//                             $this->dispatch();
//                             return;
//                         } elseif ($page['page_type'] === 'controller') {
//                             $targetController = $page['controller_name']; 
//                             $targetAction = !empty($page['action_name']) ? $page['action_name'] : 'index';
                            
//                             $targetFile = __DIR__ . '/../../app/Controllers/' . $targetController . '.php';
//                             if (file_exists($targetFile)) {
//                                 $this->controller = $targetController;
//                                 $this->method = $targetAction;
//                                 $this->params = [];
//                                 $this->dispatch();
//                                 return;
//                             }
//                         }
//                     }

//                     $this->controller = 'AppController';
//                     $this->method = 'index';
//                     $this->params = $url;
//                     $this->dispatch();
//                     return;
//                 }
//             }
//         }

//         // Sınıfı yükle
//         $controllerClass = "App\\Controllers\\" . $this->controller;
//         $this->controller = new $controllerClass();

//         // 2. Method Tespiti (Standart RESTful)
//         if (isset($url[0])) {
//             if (method_exists($this->controller, $url[0])) {
//                 $this->method = $url[0];
//                 unset($url[0]);
//                 $url = array_values($url);
//             }
//         }

//         // 3. Kalan segmentler parametredir (örn: edit/5 -> $params = [5])
//         $this->params = $url ? array_values($url) : [];

//         // Güvenlik / Geçerlilik kontrolü
//         if (!method_exists($this->controller, $this->method)) {
//             // Eğer AdminController çağrıldıysa ve index metodu yoksa varsayılan olarak dashboard metodunu çalıştır
//             if (method_exists($this->controller, 'dashboard')) {
//                 $this->method = 'dashboard';
//             } else {
//                 $this->method = 'index';
//             }
//         }

//         call_user_func_array([$this->controller, $this->method], $this->params);


//     }

//     protected function dispatch() {
//         $controllerClass = "App\\Controllers\\" . $this->controller;
//         $controllerInstance = new $controllerClass();
//         call_user_func_array([$controllerInstance, $this->method], $this->params);
//     }

//     protected function parseUrl(): array {
//         if (isset($_GET['url'])) {
//             return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
//         }
//         return [];
//     }
// }


namespace System\Core;

use App\Models\Page;

class Router {
    protected $controller = 'AppController'; 
    protected string $method = 'index';            
    protected array $params = [];                

    public function __construct() {
        $url = $this->parseUrl();

        // 1. Controller Tespiti (Admin veya App Önekleri)
        if (isset($url[0]) && (strtolower($url[0]) === 'admin' || strtolower($url[0]) === 'app')) {
            $prefix = strtolower($url[0]);
            $controllerName = ($prefix === 'admin') ? 'AdminController' : 'AppController'; 
            
            if (isset($url[1])) {
                $inputName = ucfirst(strtolower($url[1]));
                $possibleController = $inputName . 'Controller'; // Örn: BuildingsController
                $possibleFile = __DIR__ . '/../../app/Controllers/' . $possibleController . '.php';

                // Eğer tekil hali yoksa çoğul dene (BuildingsController vb.)
                if (!file_exists($possibleFile)) {
                    $possibleController = $inputName . 'sController';
                    $possibleFile = __DIR__ . '/../../app/Controllers/' . $possibleController . '.php';
                }

                // Eğer ikinci kelimeye ait gerçek bir Controller varsa
                if (file_exists($possibleFile)) {
                    $controllerName = $possibleController;
                    unset($url[0], $url[1]); // prefix ve controller adını düş
                } else {
                    // Yoksa, sadece öneki düş
                    unset($url[0]);
                }
            } else {
                unset($url[0]);
            }

            $this->controller = $controllerName;
            $url = array_values($url);
        } else {
            // Standart ön yüz controller tespiti
            if (isset($url[0])) {
                $controllerName = ucfirst(strtolower($url[0])) . 'Controller';
                $controllerFile = __DIR__ . '/../../app/Controllers/' . $controllerName . '.php';

                if (file_exists($controllerFile)) {
                    $this->controller = $controllerName;
                    unset($url[0]);
                    $url = array_values($url); 
                } else {
                    // Fiziksel controller yoksa dinamik sayfa sistemine bak (CMS Page Slug)
                    $fullSlug = implode('/', $url);
                    $pageModel = new Page();
                    $page = $pageModel->findBySlug($fullSlug);

                    if ($page) {
                        if ($page['page_type'] === 'static') {
                            $this->controller = 'PageController';
                            $this->method = 'show';
                            $this->params = [$page];
                            $this->dispatch();
                            return;
                        } elseif ($page['page_type'] === 'controller') {
                            $targetController = $page['controller_name']; 
                            $targetAction = !empty($page['action_name']) ? $page['action_name'] : 'index';
                            
                            $targetFile = __DIR__ . '/../../app/Controllers/' . $targetController . '.php';
                            if (file_exists($targetFile)) {
                                $this->controller = $targetController;
                                $this->method = $targetAction;
                                $this->params = [];
                                $this->dispatch();
                                return;
                            }
                        }
                    }

                    $this->controller = 'AppController';
                    $this->method = 'index';
                    $this->params = $url;
                    $this->dispatch();
                    return;
                }
            }
        }

        // Sınıfı yükle
        $controllerClass = "App\\Controllers\\" . $this->controller;
        $this->controller = new $controllerClass();

        // 2. Method Tespiti (Standart RESTful)
        if (isset($url[0])) {
            if (method_exists($this->controller, $url[0])) {
                $this->method = $url[0];
                unset($url[0]);
                $url = array_values($url);
            }
        }

        // 3. Kalan segmentler parametredir (örn: edit/5 -> $params = [5])
        $this->params = $url ? array_values($url) : [];

        // Güvenlik / Geçerlilik kontrolü
        if (!method_exists($this->controller, $this->method)) {
            if (method_exists($this->controller, 'dashboard')) {
                $this->method = 'dashboard';
            } else {
                $this->method = 'index';
            }
        }

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    protected function dispatch() {
        $controllerClass = "App\\Controllers\\" . $this->controller;
        $controllerInstance = new $controllerClass();
        call_user_func_array([$controllerInstance, $this->method], $this->params);
    }

    protected function parseUrl(): array {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }
}
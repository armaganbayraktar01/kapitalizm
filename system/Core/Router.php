<?php
// namespace System\Core;

// use App\Models\Page;

// class Router {
//     protected $controller = 'HomeController'; 
//     protected string $method = 'index';            
//     protected array $params = [];                

//     public function __construct() {
//         $url = $this->parseUrl();

//         // 1. Adım: Fiziksel bir Controller var mı kontrol et
//         if (isset($url[0])) {
//             $controllerName = ucfirst(strtolower($url[0])) . 'Controller';
//             $controllerFile = __DIR__ . '/../../app/Controllers/' . $controllerName . '.php';

//             if (file_exists($controllerFile)) {
//                 $this->controller = $controllerName;
//                 unset($url[0]);
//                 $url = array_values($url); 
//             } else {
//                 // Fiziksel controller yoksa sayfa tablosuna bak (Statik / Modüler sayfa)
//                 $fullSlug = implode('/', $url);
//                 $pageModel = new Page();
//                 $page = $pageModel->findBySlug($fullSlug);

//                 if ($page) {
//                     if ($page['page_type'] === 'static') {
//                         $this->controller = 'PageController';
//                         $this->method = 'show';
//                         $this->params = [$page];
//                         $this->dispatch();
//                         return;
//                     } elseif ($page['page_type'] === 'controller') {
//                         $targetController = $page['controller_name']; 
//                         $targetAction = !empty($page['action_name']) ? $page['action_name'] : 'index';
                        
//                         $targetFile = __DIR__ . '/../../app/Controllers/' . $targetController . '.php';
//                         if (file_exists($targetFile)) {
//                             $this->controller = $targetController;
//                             $this->method = $targetAction;
//                             $this->params = [];
//                             $this->dispatch();
//                             return;
//                         }
//                     }
//                 }
//             }
//         }

//         // Controller sınıfını yükle
//         $controllerClass = "App\\Controllers\\" . $this->controller;
//         $this->controller = new $controllerClass();

//         // 2. Adım: Metot Tespiti (Örn: menus)
//         if (isset($url[0])) {
//             if (method_exists($this->controller, $url[0])) {
//                 $this->method = $url[0];
//                 unset($url[0]);
//                 $url = array_values($url);
//             }
//         }

//         // 3. Adım: Alt Eylem / Sub-Action Tespiti (create, edit, delete, sort vb.)
//         if (isset($url[0])) {
//             $actionSegment = $url[0]; // create, edit, sort vb.
            
//             $possibleMethod1 = 'menu' . ucfirst($actionSegment); // örn: menuCreate, menuSort
//             $possibleMethod2 = 'page' . ucfirst($actionSegment); // örn: pageCreate
//             $possibleMethod3 = $this->method . ucfirst($actionSegment); 

//             if (method_exists($this->controller, $possibleMethod1)) {
//                 $this->method = $possibleMethod1;
//                 unset($url[0]);
//                 $url = array_values($url);
//             } elseif (method_exists($this->controller, $possibleMethod2)) {
//                 $this->method = $possibleMethod2;
//                 unset($url[0]);
//                 $url = array_values($url);
//             } elseif (method_exists($this->controller, $actionSegment)) {
//                 $this->method = $actionSegment;
//                 unset($url[0]);
//                 $url = array_values($url);
//             }
//         }

//         $this->params = $url ? array_values($url) : [];

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
    protected $controller = 'HomeController'; 
    protected string $method = 'index';            
    protected array $params = [];                

    public function __construct() {
        $url = $this->parseUrl();

        // // 1. Controller Tespiti
        // if (isset($url[0])) {
        //     $controllerName = ucfirst(strtolower($url[0])) . 'Controller';
        //     $controllerFile = __DIR__ . '/../../app/Controllers/' . $controllerName . '.php';

        //     if (file_exists($controllerFile)) {
        //         $this->controller = $controllerName;
        //         unset($url[0]);
        //         $url = array_values($url); 
        //     } else {
        //         // Fiziksel controller yoksa dinamik sayfa sistemine bak (CMS Page Slug)
        //         $fullSlug = implode('/', $url);
        //         $pageModel = new Page();
        //         $page = $pageModel->findBySlug($fullSlug);

        //         if ($page) {
        //             if ($page['page_type'] === 'static') {
        //                 $this->controller = 'PageController';
        //                 $this->method = 'show';
        //                 $this->params = [$page];
        //                 $this->dispatch();
        //                 return;
        //             } elseif ($page['page_type'] === 'controller') {
        //                 $targetController = $page['controller_name']; 
        //                 $targetAction = !empty($page['action_name']) ? $page['action_name'] : 'index';
                        
        //                 $targetFile = __DIR__ . '/../../app/Controllers/' . $targetController . '.php';
        //                 if (file_exists($targetFile)) {
        //                     $this->controller = $targetController;
        //                     $this->method = $targetAction;
        //                     $this->params = [];
        //                     $this->dispatch();
        //                     return;
        //                 }
        //             }
        //         }

        //         // Eşleşen hiçbir şey yoksa HomeController'a yönlendir
        //         $this->controller = 'HomeController';
        //         $this->method = 'index';
        //         $this->params = $url;
        //         $this->dispatch();
        //         return;
        //     }
        // }
        // 1. Controller Tespiti
        if (isset($url[0])) {
            // ÖZEL KURAL: Eğer 'admin/modules' ile başlıyorsa direkt GeneratorController'a yönlendir
            if (strtolower($url[0]) === 'admin' && isset($url[1]) && strtolower($url[1]) === 'modules') {
                $controllerName = 'GeneratorController';
                $controllerFile = __DIR__ . '/../../app/Controllers/GeneratorController.php';
                
                if (file_exists($controllerFile)) {
                    $this->controller = $controllerName;
                    // 'admin' ve 'modules' segmentlerini URL dizisinden düşür ki geriye [edit, 5] kalsın
                    unset($url[0], $url[1]);
                    $url = array_values($url);
                }
            } else {
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

                    // Eşleşen hiçbir şey yoksa HomeController'a yönlendir
                    $this->controller = 'HomeController';
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
            $this->method = 'index';
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
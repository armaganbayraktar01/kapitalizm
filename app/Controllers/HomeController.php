<?php
namespace App\Controllers;

use System\Core\Controller;

class HomeController extends Controller {
    public function index($param = null) {
        // Görünüme gönderilecek veriler
        $data = [
            'title' => 'Ana Sayfa',
            'message' => 'xxx'
        ];

        // View çağrısı
        $this->view('home/index', $data);
    }
}
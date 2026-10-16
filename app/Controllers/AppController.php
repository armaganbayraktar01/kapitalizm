<?php
namespace App\Controllers;

use System\Core\Controller;

class AppController extends Controller {
    public function index($param = null) {
        // Görünüme gönderilecek veriler
        $data = [
            'title' => 'Ana Sayfa',
            'message' => 'xxx'
        ];

        // View çağrısı
        $this->view('app/index', $data);
    }
}
<?php
namespace App\Controllers;

use System\Core\Controller;

class PageController extends Controller {

    // Veritabanından gelen statik sayfayı ekrana basar
    public function show($page) {
        // Eğer sayfa bulunamadıysa veya dizi gelmediyse ana sayfaya yönlendir
        if (!$page || !is_array($page)) {
            redirect('home/index');
        }

        $data = [
            'title' => $page['title'],
            'page' => $page
        ];

        // Ön yüz şablonumuza (front layout) uygun bir view üzerinden içeriği gösterelim
        // Örn: app/Views/page/show.php
        $this->view('page/show', $data);
    }
}
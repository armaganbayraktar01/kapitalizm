<?php
namespace App\Controllers;

use System\Core\Controller;

class InvoiceControllers extends Controller {

    public function index() {
        $data = [
            'title' => 'Fatura Yönetimi'
        ];

        echo '<div class="container mt-4"><h3>Fatura Yönetimi Modülü</h3><p>Bu controller otomatik üretildi.</p></div>';
    }
}

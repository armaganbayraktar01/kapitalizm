<?php

namespace App\Controllers;

use System\Core\Controller;
use App\Services\NpcSourcingService;
use Exception;

class NpcRetailController extends Controller 
{
    private NpcSourcingService $sourcingService;

    public function __construct() 
    {
        parent::__construct();
        $this->sourcingService = new NpcSourcingService();
    }

    /**
     * Otomatik veya manuel tedarik başlatma API isteği
     */
    public function triggerSourcing(): void 
    {
        header('Content-Type: application/json');

        try {
            $storeId = (int)($_POST['store_id'] ?? 0);
            $productId = (int)($_POST['product_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);

            if ($storeId <= 0 || $productId <= 0 || $amount <= 0) {
                throw new Exception("Geçersiz parametre değerleri iletildi.");
            }

            // Servis üzerinden 4 aşamalı tedarik sürecini tetikle
            $result = $this->sourcingService->executeAutoSourcing($storeId, $productId, $amount);

            if (!$result['success']) {
                http_response_code(400);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'API_EXECUTION_FAILED',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
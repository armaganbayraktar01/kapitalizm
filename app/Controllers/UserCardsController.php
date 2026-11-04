<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class UserCardsController extends Controller {

    /**
     * Oyuncunun Sahip Olduğu Kartları Listele
     */
    public function index() {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

        // Süresi dolan kartları otomatik pasife çek
        $db->prepare("UPDATE user_cards SET is_active = 0 WHERE user_id = ? AND expire_date IS NOT NULL AND expire_date < NOW()")
           ->execute([$userId]);

        // Oyuncunun kartlarını çek
        $stmt = $db->prepare("SELECT * FROM user_cards WHERE user_id = ? ORDER BY is_active DESC, id DESC");
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // View'e aktar
        $this->view('app/cards/index', [
            'cards' => $cards
        ]);
    }
}
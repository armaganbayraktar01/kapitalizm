<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class ArgeCardsController extends Controller {

    // AR-GE Merkezindeki Mevcut Sertifika ve Kartları Listele
    public function index() {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

        // AR-GE merkezindeki şablonlar
        $argeCards = $db->query("SELECT * FROM research_cards ORDER BY price ASC")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Şirket kasası
        $player = $db->prepare("SELECT money FROM users WHERE id = ?");
        $player->execute([$userId]);
        $playerMoney = (float)$player->fetchColumn();

        // Şirketin daha önceden açtığı/satın aldığı aktif kartlar
        $myCards = $db->prepare("SELECT card_key FROM user_cards WHERE user_id = ? AND is_active = 1");
        $myCards->execute([$userId]);
        $ownedCardKeys = $myCards->fetchAll(\PDO::FETCH_COLUMN);

        $this->view('app/arge/index', [
            'arge_cards' => $argeCards,
            'player_money' => $playerMoney,
            'owned_keys' => $ownedCardKeys
        ]);
    }

    // AR-GE Yatırımı Yap / Sertifika Satın Al
    public function buy() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;
            $cardId = (int)($_POST['card_id'] ?? 0);

            try {
                $db->beginTransaction();

                // 1. AR-GE kartı şablonunu al
                $stmt = $db->prepare("SELECT * FROM research_cards WHERE id = ?");
                $stmt->execute([$cardId]);
                $card = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$card) {
                    throw new \Exception('AR-GE sertifikası bulunamadı.');
                }

                // 2. Şirket kasası kontrolü
                $uStmt = $db->prepare("SELECT money FROM users WHERE id = ? FOR UPDATE");
                $uStmt->execute([$userId]);
                $money = (float)$uStmt->fetchColumn();

                if ($money < (float)$card['price']) {
                    throw new \Exception('Şirket kasasında yeterli bütçe bulunmuyor!');
                }

                // 3. Ücreti kasadan düş
                $db->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                   ->execute([$card['price'], $userId]);

                // 4. Süre hesaplama
                $expireDate = null;
                if (!empty($card['duration_seconds']) && (int)$card['duration_seconds'] > 0) {
                    $expireDate = date('Y-m-d H:i:s', time() + (int)$card['duration_seconds']);
                }

                // 5. Oyuncunun aktif AR-GE kartlarına ekle (Tüm çarpan faktörleriyle birlikte)
                // INSERT sorgusuna 'category' alanını ekleyin:
                $ins = $db->prepare("INSERT INTO user_cards (user_id, card_key, card_name, category, description, level, effect_output, effect_input_cons, effect_energy, effect_labor, effect_transport, effect_tax, expire_date, is_active, start_date) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");

                $ins->execute([
                    $userId, 
                    $card['card_key'], 
                    $card['card_name'], 
                    $card['category'] ?? 'general', 
                    $card['description'], 
                    $card['effect_output'], 
                    $card['effect_input_cons'], 
                    $card['effect_energy'], 
                    $card['effect_labor'], 
                    $card['effect_transport'], 
                    $card['effect_tax'],
                    $expireDate
                ]);

                $db->commit();
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'AR-GE sertifikası başarıyla araştırıldı ve şirket tesislerinde aktifleşti!'
                ]);
                exit;
            } catch (\Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                echo json_encode([
                    'status' => 'error', 
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        }
    }
}
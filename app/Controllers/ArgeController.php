<?php
namespace App\Controllers;

use System\Core\Controller;
use System\Core\Database;

class ArgeController extends Controller {

    /**
     * Admin: Sistemdeki Tüm AR-GE Sertifika Şablonlarını Listele
     */
    public function index() {
        $db = Database::getInstance();
        $cards = $db->query("SELECT * FROM research_cards ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('admin/arge/index', [
            'cards' => $cards
        ]);
    }

    /**
     * Admin: Yeni AR-GE Kartı Ekleme Formunu Göster
     */
    public function create() {
        $this->view('admin/arge/create');
    }

    /**
     * Admin: Yeni AR-GE Kartını Veritabanına Kaydet
     */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $cardKey         = trim($_POST['card_key'] ?? '');
            $cardName        = trim($_POST['card_name'] ?? '');
            $description     = trim($_POST['description'] ?? '');
            $category        = trim($_POST['category'] ?? 'general');
            $price           = (float)($_POST['price'] ?? 0);
            $durationSeconds = !empty($_POST['duration_seconds']) ? (int)$_POST['duration_seconds'] : null;
            
            $effectOutput    = (float)($_POST['effect_output'] ?? 1.00);
            $effectInputCons = (float)($_POST['effect_input_cons'] ?? 1.00);
            $effectEnergy    = (float)($_POST['effect_energy'] ?? 1.00);
            $effectLabor     = (float)($_POST['effect_labor'] ?? 1.00);
            $effectTransport = (float)($_POST['effect_transport'] ?? 1.00);
            $effectTax       = (float)($_POST['effect_tax'] ?? 1.00);

            
            if (empty($cardKey) || empty($cardName)) {
                redirect('/manager/admin/arge');
            }

            try {
                $db->beginTransaction();

            // INSERT sorgusuna 'category' sütununu ekleyin:
            $stmt = $db->prepare("INSERT INTO research_cards (card_key, card_name, category, description, price, duration_seconds, effect_output, effect_input_cons, effect_energy, effect_labor, effect_transport, effect_tax) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $cardKey, $cardName, $category, $description, $price, $durationSeconds,
                $effectOutput, $effectInputCons, $effectEnergy, $effectLabor, $effectTransport, $effectTax
            ]);

            $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("AR-GE Kartı Oluşturma Hatası: " . $e->getMessage());
            }

            redirect('/manager/admin/arge');
        }
    }

    /**
     * Admin: Mevcut AR-GE Kartını Düzenleme Formunu Göster
     */
    public function edit($id) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM research_cards WHERE id = ?");
        $stmt->execute([$id]);
        $card = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$card) {
            redirect('/manager/admin/arge');
        }

        $this->view('admin/arge/edit', [
            'card' => $card
        ]);
    }

    /**
     * Admin: AR-GE Kartını Güncelle
     */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $cardId          = (int)($_POST['card_id'] ?? 0);
            $cardName        = trim($_POST['card_name'] ?? '');
            $description     = trim($_POST['description'] ?? '');
            $price           = (float)($_POST['price'] ?? 0);
            $durationSeconds = !empty($_POST['duration_seconds']) ? (int)$_POST['duration_seconds'] : null;
            
            $effectOutput    = (float)($_POST['effect_output'] ?? 1.00);
            $effectInputCons = (float)($_POST['effect_input_cons'] ?? 1.00);
            $effectEnergy    = (float)($_POST['effect_energy'] ?? 1.00);
            $effectLabor     = (float)($_POST['effect_labor'] ?? 1.00);
            $effectTransport = (float)($_POST['effect_transport'] ?? 1.00);
            $effectTax       = (float)($_POST['effect_tax'] ?? 1.00);
            $category = trim($_POST['category'] ?? 'general');


            if ($cardId <= 0) {
                redirect('/manager/admin/arge');
            }

            try {
                $db->beginTransaction();

                // UPDATE sorgusuna category = ? ekleyin:
                $stmt = $db->prepare("UPDATE research_cards SET card_name = ?, category = ?, description = ?, price = ?, duration_seconds = ?, effect_output = ?, effect_input_cons = ?, effect_energy = ?, effect_labor = ?, effect_transport = ?, effect_tax = ? WHERE id = ?");

                $stmt->execute([
                    $cardName, $category, $description, $price, $durationSeconds,
                    $effectOutput, $effectInputCons, $effectEnergy, $effectLabor, $effectTransport, $effectTax, $cardId
                ]);

                $db->commit();
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("AR-GE Kartı Güncelleme Hatası: " . $e->getMessage());
            }

            redirect('/manager/admin/arge');
        }
    }

    /**
     * Admin: AR-GE Kartını Sil
     */
    public function delete($id = null) {
        $db = Database::getInstance();
        $cardId = (int)($id ?? $_POST['card_id'] ?? 0);

        if ($cardId > 0) {
            $stmt = $db->prepare("DELETE FROM research_cards WHERE id = ?");
            $stmt->execute([$cardId]);
        }

        redirect('/manager/admin/arge');
    }
}
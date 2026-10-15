<?php
namespace App\Controllers;

use System\Core\Controller;

class AuthController extends Controller {

    // Kayıt Sayfası ve İşlemi
    public function register() {
        $error = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                $error = 'Lütfen tüm alanları doldurun.';
            } else {
                $userModel = $this->model('User');
                
                // E-posta kullanımda mı kontrol et
                if ($userModel->findByEmail($email)) {
                    $error = 'Bu e-posta adresi zaten kayıtlı.';
                } else {
                    // İlk kayıt olanı admin yapalım veya varsayılan user yapalım
                    $role = ($email === 'admin@manager.com') ? 'admin' : 'user';

                    $saved = $userModel->create([
                        'username' => $username,
                        'email'    => $email,
                        'password' => $password,
                        'role'     => $role
                    ]);

                    if ($saved) {
                        redirect('auth/login');
                    } else {
                        $error = 'Kayıt sırasında bir hata oluştu.';
                    }
                }
            }
        }

        $this->view('auth/register', ['error' => $error]);
    }

    // Giriş Sayfası ve İşlemi
    public function login() {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Lütfen tüm alanları doldurun.';
            } else {
                $userModel = $this->model('User');
                $user = $userModel->findByEmail($email);

                if ($user && password_verify($password, $user['password'])) {
                    // Oturum bilgilerini kaydet
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Rolüne göre yönlendir
                    if ($user['role'] === 'admin') {
                        redirect('admin/dashboard');
                    } else {
                        redirect('home/index');
                    }
                } else {
                    $error = 'E-posta veya şifre hatalı.';
                }
            }
        }

        $this->view('auth/login', ['error' => $error]);
    }

    // Çıkış Yapma
    public function logout() {
        session_destroy();
        redirect('auth/login');
    }
}
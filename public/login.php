<?php
session_start();
require_once '../includes/google-config.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Jika ada kode autentikasi dari Google
if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (!isset($token['error'])) {
            $client->setAccessToken($token['access_token']);
            $google_oauth = new Google_Service_Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();

            $google_id = $google_account_info->id;
            $email = $google_account_info->email;
            $name = $google_account_info->name;

            // Cek apakah pengguna sudah ada di database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
            $stmt->execute([$google_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Pengguna baru, simpan ke database tanpa verifikasi email
                $stmt = $pdo->prepare("INSERT INTO users (google_id, name, email, role, email_verified) VALUES (?, ?, ?, 'nasabah', TRUE)");
                $stmt->execute([$google_id, $name, $email]);

                $_SESSION['user'] = [
                    'id' => $pdo->lastInsertId(),
                    'google_id' => $google_id,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'nasabah',
                    'email_verified' => true
                ];

                header('Location: ../templates/nasabah/dashboard.php');
                exit();
            } else {
                // Pengguna lama, update session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'google_id' => $user['google_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'email_verified' => $user['email_verified']
                ];

                $redirectPath = $user['role'] === 'admin' ? '../templates/admin/dashboard.php' : '../templates/nasabah/dashboard.php';
                header("Location: $redirectPath");
                exit();
            }
        } else {
            header('Location: ../public/login.php?error=' . urlencode('Login dengan Google gagal: ' . $token['error']));
            exit();
        }
    } catch (Exception $e) {
        header('Location: ../public/login.php?error=' . urlencode('Error autentikasi: ' . $e->getMessage()));
        exit();
    }
} else {
    $loginUrl = $client->createAuthUrl();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Koperasi Terpadu</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #6b7280, #1f2937);
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        .google-btn {
            transition: all 0.3s ease;
        }
        .google-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: #f1f5f9;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="card p-8 rounded-xl shadow-lg w-full max-w-md fade-in">
        <!-- Logo atau Ikon -->
        <div class="flex justify-center mb-6">
            <i class="fas fa-users text-4xl text-gray-700"></i>
        </div>

        <!-- Judul -->
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Sistem Koperasi Terpadu</h2>
        <p class="text-center text-gray-500 text-sm mb-8">Masuk dengan akun Google Anda untuk melanjutkan</p>

        <!-- Pesan Error -->
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6 text-center text-sm border border-red-200">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Tombol Login Google -->
        <div class="flex justify-center">
            <a href="<?php echo $loginUrl; ?>" 
               class="google-btn flex items-center justify-center w-full bg-white border border-gray-300 text-gray-700 py-3 px-6 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C4.01 20.07 7.77 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.77 1 4.01 3.93 2.18 7.07L5.84 9.91c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span class="text-base font-medium">Masuk dengan Google</span>
            </a>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-400 text-xs mt-6">
            &copy; <?php echo date('Y'); ?> Koperasi Terpadu. All rights reserved.
        </p>
    </div>
</body>
</html>

<?php
}
?>
<?php
session_start();
require_once '../includes/google-config.php';
require_once '../config/database.php';

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

                // Set session untuk pengguna baru
                $_SESSION['user'] = [
                    'id' => $pdo->lastInsertId(),
                    'google_id' => $google_id,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'nasabah',
                    'email_verified' => true
                ];

                // Langsung redirect ke dashboard nasabah
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

                // Redirect ke dashboard sesuai role
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
    header('Location: ../public/login.php');
    exit();
}
?>
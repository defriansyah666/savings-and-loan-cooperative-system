<?php
session_start();
require_once './config/database.php';

try {
    // Ambil semua pemberitahuan dari database, tanpa kolom image
    $stmt = $pdo->query("SELECT title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 6");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Gagal mengambil data pemberitahuan: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Koperasi Terpadu</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #f9fafb;
            font-family: 'Arial', sans-serif;
        }
        .header-section {
            background: linear-gradient(to bottom right, #ffffff, #f3f4f6);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .header-title {
            background: linear-gradient(to right, #1e40af, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .container-card {
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            border-radius: 20px;
        }
        .notification-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            transition: all 0.3s ease;
            padding: 1.5rem;
        }
        .notification-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .google-btn {
            transition: all 0.3s ease;
            background: #ffffff;
            border: 2px solid #e5e7eb;
        }
        .google-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            background: #f1f5f9;
        }
        .title-line::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, #2563eb, #60a5fa);
            margin: 12px auto 0;
            border-radius: 2px;
        }
        .sidebar {
            transition: all 0.3s ease;
            width: 260px;
        }
        .sidebar a:hover {
            background-color: #f1f5f9;
            color: #2563eb;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                height: 100%;
                z-index: 50;
                top: 0;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .notification-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Sidebar Navigation (Mobile) -->
    <div id="sidebar" class="sidebar bg-white border-r border-gray-200 p-5 fixed h-full z-50 md:hidden">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                <i class="fas fa-user-circle mr-2 text-blue-600"></i> Koperasi Terpadu
            </h2>
            <button id="close-sidebar" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="space-y-1">
            <a href="./public/login.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-sign-in-alt mr-2"></i> Login</a>
        </nav>
    </div>

    <!-- Main Content Wrapper -->
    <div class="flex-1 w-full main-content md:ml-0 md:pl-2">
        <!-- Mobile Menu Button -->
        <button id="toggle-sidebar" class="md:hidden fixed top-4 left-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 z-50">
            <i class="fas fa-ellipsis-v"></i>
        </button>

        <!-- Header Section -->
        <header class="header-section w-full py-6 md:py-8 fade-in">
            <div class="container mx-auto max-w-6xl px-4 md:px-6 flex flex-col items-center">
                <h1 class="text-3xl md:text-5xl font-extrabold header-title mb-4 text-center">Sistem Koperasi Terpadu</h1>
                <p class="text-base md:text-lg text-gray-600 mb-6 text-center max-w-2xl">
                    Kelola keuangan koperasi Anda dengan mudah, aman, dan terpercaya. Bergabunglah sekarang!
                </p>
                <a href="./public/login.php" class="google-btn flex items-center justify-center text-gray-700 font-medium py-2 md:py-3 px-6 md:px-8 rounded-full">
                    <svg class="w-5 h-5 md:w-6 md:h-6 mr-2 md:mr-3" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C4.01 20.07 7.77 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.77 1 4.01 3.93 2.18 7.07L5.84 9.91c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span>Gabung Bersama Kami</span>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto max-w-6xl px-4 md:px-6 py-8 md:py-12 fade-in">
            <div class="container-card p-6 md:p-10">
                <h2 class="text-2xl md:text-3xl font-semibold text-gray-800 mb-6 md:mb-8 text-center title-line">Pemberitahuan Terbaru</h2>

                <?php if (isset($error)): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 text-center text-sm border border-red-100 flex items-center justify-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php elseif (empty($notifications)): ?>
                    <div class="flex flex-col items-center justify-center text-gray-500 py-12 md:py-16">
                        <p class="text-base md:text-lg font-medium">Belum ada pemberitahuan saat ini.</p>
                        <p class="text-sm text-gray-400 mt-2 md:mt-3">Bergabunglah untuk mendapatkan informasi terbaru!</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-8">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-card">
                                <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-2 md:mb-3 flex items-center">
                                    <i class="fas fa-bell text-blue-500 mr-2 md:mr-3"></i>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h3>
                                <p class="text-sm md:text-base text-gray-600 leading-relaxed"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <p class="text-xs md:text-sm text-gray-400 mt-3 md:mt-4"><?php echo date('d M Y H:i', strtotime($notification['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full py-6 md:py-8 text-center text-gray-400 text-xs md:text-sm fade-in">
            © <?php echo date('Y'); ?> Koperasi Terpadu. All rights reserved.
        </footer>
    </div>

    <!-- JavaScript -->
    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleSidebar = document.getElementById('toggle-sidebar');
        const closeSidebar = document.getElementById('close-sidebar');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && 
                !sidebar.contains(e.target) && 
                !toggleSidebar.contains(e.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>
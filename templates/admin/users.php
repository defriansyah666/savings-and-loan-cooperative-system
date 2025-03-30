<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

// Ambil semua user
$stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id ASC");
$all_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Daftar Semua User</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s ease;
            width: 260px;
        }
        .sidebar a:hover {
            background-color: #f1f5f9;
            color: #2563eb;
        }
        .user-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        .user-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #2563eb;
        }
        .role-admin {
            background-color: #2563eb;
            color: white;
        }
        .role-nasabah {
            background-color: #16a34a;
            color: white;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                height: 100%;
                z-index: 50;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-white font-sans antialiased">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar bg-white border-r border-gray-200 p-5 fixed h-full z-50 md:relative md:left-0">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-shield-alt mr-2 text-blue-600"></i> Admin Panel
                </h2>
                <button id="close-sidebar" class="md:hidden text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="space-y-1">
                <a href="dashboard.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="transactions.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-exchange-alt mr-2"></i> Transaksi</a>
                <a href="loans.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
                <a href="installments.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-calendar-check mr-2"></i> Angsuran</a>
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-users mr-2"></i> Semua User</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <h1 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-users mr-2 text-blue-600"></i> Daftar Semua User
            </h1>
            
            <!-- Daftar User dengan Card -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
                <?php if (empty($all_users)): ?>
                <div class="col-span-full text-center text-gray-500 py-10">
                    <i class="fas fa-users-slash text-4xl mb-2"></i>
                    <p>Belum ada pengguna terdaftar.</p>
                </div>
                <?php else: ?>
                <?php foreach ($all_users as $user): ?>
                <div class="user-card bg-white rounded-lg p-4 md:p-5 flex flex-col space-y-3">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-circle text-blue-600 text-xl md:text-2xl"></i>
                        <h3 class="text-base md:text-lg font-medium text-gray-800 truncate"><?php echo htmlspecialchars($user['name']); ?></h3>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-id-badge text-gray-500"></i>
                        <p class="text-sm text-gray-600">ID: <?php echo $user['id']; ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-envelope text-gray-500"></i>
                        <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-tag text-gray-500"></i>
                        <span class="role-<?php echo $user['role']; ?> text-xs font-semibold px-2 py-1 rounded">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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
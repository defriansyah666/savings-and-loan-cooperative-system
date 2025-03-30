<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

$stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'nasabah' ORDER BY name ASC");
$nasabah_list = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sidebar {
            transition: all 0.3s ease;
            width: 260px; /* Slightly adjusted width */
        }
        .sidebar a:hover {
            background-color: #f1f5f9;
            color: #2563eb;
        }
        .card {
            transition: all 0.2s ease;
        }
        .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .btn-primary {
            background-color: #2563eb;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
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
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="transactions.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-exchange-alt mr-2"></i> Transaksi</a>
                <a href="loans.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
                <a href="installments.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-calendar-check mr-2"></i> Angsuran</a>
                <a href="users.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-users mr-2"></i> Semua User</a>
                <a href="notifications.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-bell mr-2"></i> Pemberitahuan</a>
                <a href="#" id="logout-btn" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i> <!-- Changed to vertical ellipsis -->
            </button>

            <h1 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6">Selamat Datang, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h1>
            
            <!-- Pilih Nasabah -->
            <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100 mb-6">
                <h2 class="text-lg font-medium text-gray-700 mb-4">Pilih Nasabah</h2>
                <form method="GET" action="nasabah_detail.php" class="space-y-4">
                    <select name="nasabah_id" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">-- Pilih Nasabah --</option>
                        <?php foreach ($nasabah_list as $nasabah): ?>
                        <option value="<?php echo $nasabah['id']; ?>">
                            <?php echo htmlspecialchars($nasabah['name']) . ' (' . htmlspecialchars($nasabah['email']) . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary text-white p-3 rounded-lg w-full font-medium">Lihat Detail</button>
                </form>
            </div>
            
            <!-- Informasi Tambahan -->
            <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100">
                <h2 class="text-lg font-medium text-gray-700 mb-4">Informasi Cepat</h2>
                <p class="text-gray-600 mb-3">Kelola sistem dengan mudah:</p>
                <ul class="space-y-2 text-gray-600">
                    <li class="flex items-center"><i class="fas fa-check-circle mr-2 text-blue-500"></i> Pilih nasabah untuk melihat aktivitas mereka.</li>
                    <li class="flex items-center"><i class="fas fa-check-circle mr-2 text-blue-500"></i> <a href="transactions.php" class="text-blue-600 hover:underline">Lihat semua transaksi</a> untuk approval.</li>
                    <li class="flex items-center"><i class="fas fa-check-circle mr-2 text-blue-500"></i> <a href="loans.php" class="text-blue-600 hover:underline">Kelola semua pinjaman</a>.</li>
                    <li class="flex items-center"><i class="fas fa-check-circle mr-2 text-blue-500"></i> <a href="installments.php" class="text-blue-600 hover:underline">Pantau semua angsuran</a>.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const toggleSidebar = document.getElementById('toggle-sidebar');
        const closeSidebar = document.getElementById('close-sidebar');
        const mainContent = document.getElementById('main-content');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && 
                !sidebar.contains(e.target) && 
                !toggleSidebar.contains(e.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        // SweetAlert for Logout
        document.getElementById('logout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Logout',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#dc2626',
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../public/logout.php';
                }
            });
        });
    </script>
</body>
</html>
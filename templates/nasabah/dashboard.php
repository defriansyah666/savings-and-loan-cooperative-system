<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'nasabah') {
    header('Location: ../../public/login.php');
    exit();
}

// Ambil saldo total dari semua rekening
$stmt = $pdo->prepare("SELECT SUM(balance) as total_balance FROM accounts WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$total_balance = $stmt->fetch()['total_balance'] ?? 0;

// Ambil jumlah transaksi pending
$stmt = $pdo->prepare("SELECT COUNT(*) as pending_transactions FROM transactions WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user']['id']]);
$pending_transactions = $stmt->fetch()['pending_transactions'] ?? 0;

// Ambil jumlah pinjaman aktif
$stmt = $pdo->prepare("SELECT COUNT(*) as active_loans FROM loans WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user']['id']]);
$active_loans = $stmt->fetch()['active_loans'] ?? 0;

// Ambil angsuran yang jatuh tempo bulan ini
$stmt = $pdo->prepare("SELECT COUNT(*) as due_installments FROM installments WHERE loan_id IN (SELECT id FROM loans WHERE user_id = ?) AND status = 'pending' AND due_date <= LAST_DAY(NOW())");
$stmt->execute([$_SESSION['user']['id']]);
$due_installments = $stmt->fetch()['due_installments'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Nasabah Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .sidebar {
            transition: all 0.3s ease;
            width: 260px;
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
                    <i class="fas fa-user-circle mr-2 text-blue-600"></i> Nasabah Portal
                </h2>
                <button id="close-sidebar" class="md:hidden text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="space-y-1">
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="accounts.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-university mr-2"></i> Rekening</a>
                <a href="deposit.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-arrow-down mr-2"></i> Simpan Uang</a>
                <a href="withdrawal.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-arrow-up mr-2"></i> Tarik Uang</a>
                <a href="transactions.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-exchange-alt mr-2"></i> Riwayat Transaksi</a>
                <a href="loans.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
                <a href="installments.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-calendar-check mr-2"></i> Angsuran</a>
                <a href="#" id="logout-btn" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <h1 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-home mr-2 text-blue-600"></i> Selamat Datang, <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
            </h1>
            
            <!-- Ringkasan -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
                <!-- Saldo Total -->
                <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100">
                    <h2 class="text-base md:text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-wallet mr-2 text-green-500"></i> Saldo Total
                    </h2>
                    <p class="text-xl md:text-2xl font-bold text-green-600">Rp <?php echo number_format($total_balance, 2, ',', '.'); ?></p>
                </div>
                
                <!-- Transaksi Pending -->
                <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100">
                    <h2 class="text-base md:text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-hourglass-half mr-2 text-yellow-500"></i> Transaksi Pending
                    </h2>
                    <p class="text-xl md:text-2xl font-bold text-yellow-600"><?php echo $pending_transactions; ?></p>
                </div>
                
                <!-- Pinjaman Aktif -->
                <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100">
                    <h2 class="text-base md:text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-hand-holding-usd mr-2 text-blue-500"></i> Pinjaman Aktif
                    </h2>
                    <p class="text-xl md:text-2xl font-bold text-blue-600"><?php echo $active_loans; ?></p>
                </div>
                
                <!-- Angsuran Jatuh Tempo -->
                <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100">
                    <h2 class="text-base md:text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-calendar-alt mr-2 text-red-500"></i> Angsuran Bulan Ini
                    </h2>
                    <p class="text-xl md:text-2xl font-bold text-red-600"><?php echo $due_installments; ?></p>
                </div>
            </div>
            
            <!-- Informasi Tambahan -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <h2 class="text-base md:text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i> Informasi Cepat
                </h2>
                <p class="text-gray-600 mb-3">Kelola keuangan Anda dengan mudah:</p>
                <ul class="space-y-2 text-gray-600 text-sm md:text-base">
                    <li class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-blue-500"></i>
                        <a href="deposit.php" class="text-blue-600 hover:underline">Simpan uang</a> untuk menambah saldo.
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-blue-500"></i>
                        <a href="withdrawal.php" class="text-blue-600 hover:underline">Tarik uang</a> kapan saja.
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-blue-500"></i>
                        <a href="loans.php" class="text-blue-600 hover:underline">Ajukan pinjaman</a> untuk kebutuhan Anda.
                    </li>
                    <li class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-blue-500"></i>
                        <a href="installments.php" class="text-blue-600 hover:underline">Bayar angsuran</a> tepat waktu.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Script SweetAlert untuk Logout -->
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
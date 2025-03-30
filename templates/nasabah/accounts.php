<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'nasabah') {
    header('Location: ../../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_number = $_POST['account_number'];
    $bank_name = $_POST['bank_name'];
    $account_holder = $_POST['account_holder'];
    
    $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, bank_name, account_holder) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user']['id'], $account_number, $bank_name, $account_holder]);
    header('Location: accounts.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$accounts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Rekening</title>
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
        .table-container {
            overflow-x: auto;
        }
        .table-row:hover {
            background-color: #f9fafb;
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
                    <i class="fas fa-user-circle mr-2 text-blue-600"></i> Nasabah Portal
                </h2>
                <button id="close-sidebar" class="md:hidden text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="space-y-1">
                <a href="dashboard.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-university mr-2"></i> Rekening</a>
                <a href="deposit.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-arrow-down mr-2"></i> Simpan Uang</a>
                <a href="withdrawal.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-arrow-up mr-2"></i> Tarik Uang</a>
                <a href="transactions.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-exchange-alt mr-2"></i> Riwayat Transaksi</a>
                <a href="loans.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
                <a href="installments.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-calendar-check mr-2"></i> Angsuran</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <h1 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-university mr-2 text-blue-600"></i> Kelola Rekening
            </h1>
            
            <!-- Form Tambah Rekening -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
                <h2 class="text-base md:text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i> Tambah Rekening
                </h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Nomor Rekening</label>
                        <input type="text" name="account_number" placeholder="Masukkan nomor rekening" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Nama Bank</label>
                        <input type="text" name="bank_name" placeholder="Masukkan nama bank" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Nama Pemilik</label>
                        <input type="text" name="account_holder" placeholder="Masukkan nama pemilik" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <button type="submit" class="btn-primary text-white p-3 rounded-lg w-full md:w-auto font-medium">Tambah Rekening</button>
                </form>
            </div>
            
            <!-- Daftar Rekening -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <h2 class="text-base md:text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-list mr-2"></i> Daftar Rekening
                </h2>
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">No. Rekening</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Bank</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Pemilik</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="4" class="p-4 text-center text-gray-500">Belum ada rekening terdaftar.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($accounts as $account): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($account['account_number']); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($account['account_holder']); ?></td>
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($account['balance'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
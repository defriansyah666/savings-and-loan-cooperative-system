<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

$stmt = $pdo->query("SELECT i.*, l.amount as loan_amount, l.duration_months, u.name as nasabah_name, a.account_number, a.bank_name 
                     FROM installments i 
                     JOIN loans l ON i.loan_id = l.id 
                     JOIN users u ON l.user_id = u.id 
                     JOIN accounts a ON l.account_id = a.id 
                     WHERE l.status = 'approved' 
                     ORDER BY i.due_date ASC");
$installments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Angsuran Nasabah</title>
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
        .status-paid {
            color: #16a34a;
            font-weight: 500;
        }
        .status-unpaid {
            color: #dc2626;
            font-weight: 500;
        }
        .btn-link {
            color: #2563eb;
            transition: all 0.2s ease;
        }
        .btn-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
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
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-calendar-check mr-2"></i> Angsuran</a>
                <a href="users.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-users mr-2"></i> Semua User</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <h1 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-calendar-check mr-2 text-blue-600"></i> Angsuran Nasabah
            </h1>
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold">Nasabah</th>
                                <th class="p-3 font-semibold">Rekening</th>
                                <th class="p-3 font-semibold">Pinjaman</th>
                                <th class="p-3 font-semibold">Angsuran Ke</th>
                                <th class="p-3 font-semibold">Jumlah</th>
                                <th class="p-3 font-semibold">Jatuh Tempo</th>
                                <th class="p-3 font-semibold">Status</th>
                                <th class="p-3 font-semibold">Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($installments)): ?>
                            <tr>
                                <td colspan="8" class="p-4 text-center text-gray-500">Belum ada data angsuran.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($installments as $i): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3"><?php echo htmlspecialchars($i['nasabah_name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($i['account_number']) . ' (' . htmlspecialchars($i['bank_name']) . ')'; ?></td>
                                <td class="p-3">Rp <?php echo number_format($i['loan_amount'], 2, ',', '.'); ?></td>
                                <td class="p-3"><?php echo $i['installment_number']; ?>/<?php echo $i['duration_months']; ?></td>
                                <td class="p-3">Rp <?php echo number_format($i['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3"><?php echo $i['due_date']; ?></td>
                                <td class="p-3">
                                    <span class="<?php echo $i['status'] === 'paid' ? 'status-paid' : 'status-unpaid'; ?>">
                                        <?php echo ucfirst($i['status']); ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php if ($i['payment_proof']): ?>
                                    <a href="../../uploads/<?php echo $i['payment_proof']; ?>" target="_blank" class="btn-link">Lihat</a>
                                    <?php else: ?>
                                    <span class="text-gray-400">Belum Dibayar</span>
                                    <?php endif; ?>
                                </td>
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
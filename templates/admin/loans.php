<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = (int)$_POST['loan_id'];
    $action = $_POST['action'];
    $interest_rate = floatval($_POST['interest_rate'] ?? 5.00);

    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND status = 'pending'");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch();

    if ($loan) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'approved', approved_at = NOW(), approved_by = ?, interest_rate = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id'], $interest_rate, $loan_id]);

            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$loan['amount'], $loan['account_id']]);

            $total_amount = $loan['amount'] * (1 + ($interest_rate / 100));
            $monthly_installment = $total_amount / $loan['duration_months'];
            $due_date = date('Y-m-d', strtotime('+1 month'));

            for ($i = 1; $i <= $loan['duration_months']; $i++) {
                $stmt = $pdo->prepare("INSERT INTO installments (loan_id, installment_number, amount, due_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$loan_id, $i, $monthly_installment, $due_date]);
                $due_date = date('Y-m-d', strtotime($due_date . ' +1 month'));
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'rejected', approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id'], $loan_id]);
        }
    }
    header('Location: loans.php');
    exit();
}

$stmt = $pdo->query("SELECT l.*, u.name, a.account_number, a.bank_name 
                     FROM loans l 
                     JOIN users u ON l.user_id = u.id 
                     JOIN accounts a ON l.account_id = a.id 
                     ORDER BY l.created_at DESC");
$loans = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Approval Pinjaman</title>
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
        .btn-link {
            color: #2563eb;
            transition: all 0.2s ease;
        }
        .btn-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .btn-approve {
            background-color: #16a34a;
        }
        .btn-approve:hover {
            background-color: #15803d;
        }
        .btn-reject {
            background-color: #dc2626;
        }
        .btn-reject:hover {
            background-color: #b91c1c;
        }
        .status-pending { color: #d97706; font-weight: 500; }
        .status-approved { color: #16a34a; font-weight: 500; }
        .status-rejected { color: #dc2626; font-weight: 500; }
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
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
                <a href="installments.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-calendar-check mr-2"></i> Angsuran</a>
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
                <i class="fas fa-hand-holding-usd mr-2 text-blue-600"></i> Daftar Pengajuan Pinjaman
            </h1>
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold">Tanggal</th>
                                <th class="p-3 font-semibold">Nasabah</th>
                                <th class="p-3 font-semibold">Rekening</th>
                                <th class="p-3 font-semibold">Jumlah</th>
                                <th class="p-3 font-semibold">Durasi</th>
                                <th class="p-3 font-semibold">Status</th>
                                <th class="p-3 font-semibold">Dokumen</th>
                                <th class="p-3 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="8" class="p-4 text-center text-gray-500">Belum ada pengajuan pinjaman.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3"><?php echo htmlspecialchars($loan['created_at']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($loan['name']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($loan['account_number'] . ' (' . $loan['bank_name'] . ')'); ?></td>
                                <td class="p-3">Rp <?php echo number_format($loan['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($loan['duration_months']); ?> bulan</td>
                                <td class="p-3">
                                    <span class="status-<?php echo htmlspecialchars($loan['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($loan['status'])); ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="space-y-1 text-sm">
                                        <div><span class="text-gray-500">KTP:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['ktp_copy']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                        <div><span class="text-gray-500">KK:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['family_card']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                        <div><span class="text-gray-500">Foto:</span> 
                                            <?php
                                            $photos = explode(',', $loan['photos']);
                                            foreach ($photos as $index => $photo) {
                                                echo '<a href="../../uploads/' . htmlspecialchars($photo) . '" target="_blank" class="btn-link">F' . ($index + 1) . '</a>';
                                                if ($index < count($photos) - 1) echo ' | ';
                                            }
                                            ?>
                                        </div>
                                        <div><span class="text-gray-500">Rencana Usaha:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['business_plan']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                        <div><span class="text-gray-500">Agunan:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['collateral_submission']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                        <div><span class="text-gray-500">Persetujuan Agunan:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['collateral_sale_approval']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                        <div><span class="text-gray-500">Kuasa Agunan:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['collateral_sale_power']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                        <div><span class="text-gray-500">Rencana Bayar:</span> <?php echo htmlspecialchars(substr($loan['repayment_plan'], 0, 20)) . (strlen($loan['repayment_plan']) > 20 ? '...' : ''); ?></div>
                                        <div><span class="text-gray-500">Kesediaan:</span> <a href="../../uploads/<?php echo htmlspecialchars($loan['installment_agreement']); ?>" target="_blank" class="btn-link">Lihat</a></div>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <?php if ($loan['status'] === 'pending'): ?>
                                    <form method="POST" class="space-y-2">
                                        <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                        <input type="number" name="interest_rate" step="0.01" min="0" max="20" value="5.00" class="w-full p-2 border border-gray-200 rounded-lg text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Bunga (%)" required>
                                        <div class="flex space-x-2">
                                            <button type="submit" name="action" value="approve" class="btn-approve text-white p-2 rounded-lg w-full text-sm font-medium">Setujui</button>
                                            <button type="submit" name="action" value="reject" class="btn-reject text-white p-2 rounded-lg w-full text-sm font-medium">Tolak</button>
                                        </div>
                                    </form>
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
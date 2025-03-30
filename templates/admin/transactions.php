<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $action = $_POST['action'];
    
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    if ($transaction && $transaction['status'] === 'pending') {
        if ($action === 'approve') {
            if ($transaction['type'] === 'withdrawal' && (!isset($_FILES['withdrawal_proof']) || $_FILES['withdrawal_proof']['error'] !== UPLOAD_ERR_OK)) {
                $error = "Bukti penarikan wajib diunggah untuk transaksi Withdrawal.";
            } else {
                $proof = null;
                if (isset($_FILES['withdrawal_proof']) && $_FILES['withdrawal_proof']['error'] === UPLOAD_ERR_OK) {
                    $fileName = time() . '_' . basename($_FILES['withdrawal_proof']['name']);
                    $target = '../../uploads/' . $fileName;
                    move_uploaded_file($_FILES['withdrawal_proof']['tmp_name'], $target);
                    $proof = $fileName;
                }
                
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved', approved_at = NOW(), approved_by = ?, withdrawal_proof = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user']['id'], $proof, $transaction_id]);
                
                $amount = $transaction['type'] === 'deposit' ? $transaction['amount'] : -$transaction['amount'];
                $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $transaction['account_id']]);
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'rejected', approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id'], $transaction_id]);
        }
    }
    header('Location: transactions.php' . (isset($error) ? '?error=' . urlencode($error) : ''));
    exit();
}

$stmt = $pdo->query("SELECT t.*, u.name, a.account_number, a.bank_name 
                     FROM transactions t 
                     JOIN users u ON t.user_id = u.id 
                     JOIN accounts a ON t.account_id = a.id 
                     ORDER BY t.created_at DESC");
$transactions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transaksi</title>
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
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-exchange-alt mr-2"></i> Transaksi</a>
                <a href="loans.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
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
                <i class="fas fa-exchange-alt mr-2 text-blue-600"></i> Daftar Transaksi
            </h1>
            
            <!-- Pesan Error -->
            <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>
            
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">Tanggal</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Nasabah</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Rekening</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Tipe</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Jumlah</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Status</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Bukti Deposit</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Bukti Penarikan</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="p-4 text-center text-gray-500">Belum ada transaksi.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($t['created_at']); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($t['name']); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($t['account_number']) . ' (' . htmlspecialchars($t['bank_name']) . ')'; ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo $t['type'] === 'deposit' ? 'Simpan' : 'Tarik'; ?></td>
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($t['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3 text-sm md:text-base">
                                    <span class="status-<?php echo htmlspecialchars($t['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($t['status'])); ?>
                                    </span>
                                </td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($t['transfer_proof']): ?>
                                    <a href="../../uploads/<?php echo htmlspecialchars($t['transfer_proof']); ?>" target="_blank" class="btn-link">Lihat</a>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($t['withdrawal_proof']): ?>
                                    <a href="../../uploads/<?php echo htmlspecialchars($t['withdrawal_proof']); ?>" target="_blank" class="btn-link">Lihat</a>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($t['status'] === 'pending'): ?>
                                    <form method="POST" enctype="multipart/form-data" class="space-y-2">
                                        <input type="hidden" name="transaction_id" value="<?php echo $t['id']; ?>">
                                        <?php if ($t['type'] === 'withdrawal'): ?>
                                        <input type="file" name="withdrawal_proof" accept="image/*" class="w-full p-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <?php endif; ?>
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
<?php
session_start();
require_once '../../config/database.php';
require_once '../../vendor/fpdf/fpdf.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

if (!isset($_GET['nasabah_id'])) {
    header('Location: dashboard.php');
    exit();
}

$nasabah_id = (int)$_GET['nasabah_id'];
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'nasabah'");
$stmt->execute([$nasabah_id]);
$nasabah = $stmt->fetch();
if (!$nasabah) {
    header('Location: dashboard.php');
    exit();
}

// Filter parameters
$transaction_filter_status = $_GET['t_status'] ?? 'all';
$loan_filter_status = $_GET['l_status'] ?? 'all';
$installment_filter_status = $_GET['i_status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$error = '';

// Aksi Approve/Reject Transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_action'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    $action = $_POST['transaction_action'];
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    if ($transaction) {
        if ($action === 'approve' && $transaction['type'] === 'withdrawal') {
            if (!isset($_FILES['withdrawal_proof']) || $_FILES['withdrawal_proof']['error'] !== UPLOAD_ERR_OK) {
                $error = "Bukti penarikan wajib diunggah untuk transaksi withdrawal.";
            } else {
                $fileName = time() . '_' . basename($_FILES['withdrawal_proof']['name']);
                $target = '../../uploads/' . $fileName;
                if (move_uploaded_file($_FILES['withdrawal_proof']['tmp_name'], $target)) {
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved', approved_at = NOW(), approved_by = ?, withdrawal_proof = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user']['id'], $fileName, $transaction_id]);
                    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
                    $stmt->execute([$transaction['amount'], $transaction['account_id']]);
                } else {
                    $error = "Gagal mengunggah bukti penarikan.";
                }
            }
        } else {
            $new_status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE transactions SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$new_status, $_SESSION['user']['id'], $transaction_id]);
            if ($action === 'approve' && $transaction['type'] === 'deposit') {
                $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['account_id']]);
            }
        }
    }
    $redirect_url = "nasabah_detail.php?nasabah_id=$nasabah_id&t_status=$transaction_filter_status&l_status=$loan_filter_status&i_status=$installment_filter_status";
    if ($date_from) $redirect_url .= "&date_from=$date_from";
    if ($date_to) $redirect_url .= "&date_to=$date_to";
    if ($error) $redirect_url .= "&error=" . urlencode($error);
    header("Location: $redirect_url");
    exit();
}

// Aksi Approve/Reject Pinjaman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_action'])) {
    $loan_id = (int)$_POST['loan_id'];
    $action = $_POST['loan_action'];
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
        } else {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'rejected', approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id'], $loan_id]);
        }
    }
    $redirect_url = "nasabah_detail.php?nasabah_id=$nasabah_id&t_status=$transaction_filter_status&l_status=$loan_filter_status&i_status=$installment_filter_status";
    if ($date_from) $redirect_url .= "&date_from=$date_from";
    if ($date_to) $redirect_url .= "&date_to=$date_to";
    header("Location: $redirect_url");
    exit();
}

// Ambil data rekening
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ?");
$stmt->execute([$nasabah_id]);
$accounts = $stmt->fetchAll();

// Ambil data transaksi dengan filter
$transaction_query = "SELECT t.*, a.account_number, a.bank_name FROM transactions t JOIN accounts a ON t.account_id = a.id WHERE t.user_id = ?";
if ($transaction_filter_status !== 'all') $transaction_query .= " AND t.status = ?";
if ($date_from) $transaction_query .= " AND t.created_at >= ?";
if ($date_to) $transaction_query .= " AND t.created_at <= ?";
$transaction_query .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($transaction_query);
$params = [$nasabah_id];
if ($transaction_filter_status !== 'all') $params[] = $transaction_filter_status;
if ($date_from) $params[] = $date_from;
if ($date_to) $params[] = $date_to;
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Ambil data pinjaman dengan filter
$loan_query = "SELECT l.*, a.account_number, a.bank_name FROM loans l JOIN accounts a ON l.account_id = a.id WHERE l.user_id = ?";
if ($loan_filter_status !== 'all') $loan_query .= " AND l.status = ?";
if ($date_from) $loan_query .= " AND l.created_at >= ?";
if ($date_to) $loan_query .= " AND l.created_at <= ?";
$loan_query .= " ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($loan_query);
$params = [$nasabah_id];
if ($loan_filter_status !== 'all') $params[] = $loan_filter_status;
if ($date_from) $params[] = $date_from;
if ($date_to) $params[] = $date_to;
$stmt->execute($params);
$loans = $stmt->fetchAll();

// Ambil data angsuran dengan filter
$installment_query = "SELECT i.*, l.amount as loan_amount, l.duration_months, a.account_number, a.bank_name FROM installments i JOIN loans l ON i.loan_id = l.id JOIN accounts a ON l.account_id = a.id WHERE l.user_id = ?";
if ($installment_filter_status !== 'all') $installment_query .= " AND i.status = ?";
if ($date_from) $installment_query .= " AND i.due_date >= ?";
if ($date_to) $installment_query .= " AND i.due_date <= ?";
$installment_query .= " ORDER BY i.due_date ASC";
$stmt = $pdo->prepare($installment_query);
$params = [$nasabah_id];
if ($installment_filter_status !== 'all') $params[] = $installment_filter_status;
if ($date_from) $params[] = $date_from;
if ($date_to) $params[] = $date_to;
$stmt->execute($params);
$installments = $stmt->fetchAll();

// Export ke PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Detail Nasabah: ' . $nasabah['name'], 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Email: ' . $nasabah['email'], 0, 1);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Daftar Rekening', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($accounts as $account) {
        $pdf->Cell(0, 10, $account['account_number'] . ' (' . $account['bank_name'] . ') - Saldo: Rp ' . number_format($account['balance'], 2, ',', '.'), 0, 1);
    }
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Riwayat Transaksi', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($transactions as $t) {
        $pdf->Cell(0, 10, $t['created_at'] . ' - ' . $t['type'] . ' - Rp ' . number_format($t['amount'], 2, ',', '.') . ' - ' . $t['status'], 0, 1);
    }
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Riwayat Pinjaman', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($loans as $loan) {
        $pdf->Cell(0, 10, $loan['created_at'] . ' - Rp ' . number_format($loan['amount'], 2, ',', '.') . ' - ' . $loan['duration_months'] . ' bulan - ' . $loan['status'], 0, 1);
    }
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Angsuran', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    foreach ($installments as $i) {
        $pdf->Cell(0, 10, $i['due_date'] . ' - Angsuran ke-' . $i['installment_number'] . ' - Rp ' . number_format($i['amount'], 2, ',', '.') . ' - ' . $i['status'], 0, 1);
    }
    
    $pdf->Output('D', 'Detail_Nasabah_' . $nasabah['name'] . '.pdf');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Detail Nasabah: <?php echo htmlspecialchars($nasabah['name']); ?></title>
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
        .btn-export {
            background-color: #059669;
        }
        .btn-export:hover {
            background-color: #047857;
        }
        .status-pending { color: #d97706; font-weight: 500; }
        .status-approved, .status-paid { color: #16a34a; font-weight: 500; }
        .status-rejected, .status-unpaid { color: #dc2626; font-weight: 500; }
        .status-completed { color: #2563eb; font-weight: 500; }
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
                <a href="../../public/logout.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <div class="flex justify-between items-center mb-6">
                <h1 class="text-xl md:text-2xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-user mr-2 text-blue-600"></i> Detail Nasabah: <?php echo htmlspecialchars($nasabah['name']); ?>
                </h1>
                <a href="?nasabah_id=<?php echo $nasabah_id; ?>&export=pdf" class="btn-export text-white p-2 rounded-lg flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Export ke PDF
                </a>
            </div>
            <p class="text-gray-600 mb-6"><i class="fas fa-envelope mr-2"></i> Email: <?php echo htmlspecialchars($nasabah['email']); ?></p>
            
            <!-- Pesan Error -->
            <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Filter -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center"><i class="fas fa-filter mr-2"></i> Filter Data</h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <input type="hidden" name="nasabah_id" value="<?php echo $nasabah_id; ?>">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Tanggal Mulai</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Tanggal Akhir</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Status Transaksi</label>
                        <select name="t_status" class="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all" <?php echo $transaction_filter_status === 'all' ? 'selected' : ''; ?>>Semua</option>
                            <option value="pending" <?php echo $transaction_filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $transaction_filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $transaction_filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Status Pinjaman</label>
                        <select name="l_status" class="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all" <?php echo $loan_filter_status === 'all' ? 'selected' : ''; ?>>Semua</option>
                            <option value="pending" <?php echo $loan_filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $loan_filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $loan_filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="completed" <?php echo $loan_filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Status Angsuran</label>
                        <select name="i_status" class="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all" <?php echo $installment_filter_status === 'all' ? 'selected' : ''; ?>>Semua</option>
                            <option value="pending" <?php echo $installment_filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $installment_filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    <div class="md:col-span-5">
                        <button type="submit" class="bg-blue-600 text-white p-2 rounded-lg w-full md:w-auto hover:bg-blue-700">Terapkan Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- Rekening -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center"><i class="fas fa-university mr-2"></i> Daftar Rekening</h2>
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
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">Belum ada rekening.</td></tr>
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
            
            <!-- Transaksi -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center"><i class="fas fa-exchange-alt mr-2"></i> Riwayat Transaksi</h2>
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">Tanggal</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Rekening</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Tipe</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Jumlah</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Status</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Bukti</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                            <tr><td colspan="7" class="p-4 text-center text-gray-500">Belum ada transaksi.</td></tr>
                            <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base"><?php echo $t['created_at']; ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($t['account_number']) . ' (' . htmlspecialchars($t['bank_name']) . ')'; ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo $t['type'] === 'deposit' ? 'Simpan' : 'Tarik'; ?></td>
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($t['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3 text-sm md:text-base"><span class="status-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($t['transfer_proof']): ?>
                                    <a href="../../uploads/<?php echo $t['transfer_proof']; ?>" target="_blank" class="btn-link">Deposit</a>
                                    <?php endif; ?>
                                    <?php if ($t['withdrawal_proof']): ?>
                                    | <a href="../../uploads/<?php echo $t['withdrawal_proof']; ?>" target="_blank" class="btn-link">Penarikan</a>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($t['status'] === 'pending'): ?>
                                    <form method="POST" enctype="multipart/form-data" class="space-y-2">
                                        <input type="hidden" name="transaction_id" value="<?php echo $t['id']; ?>">
                                        <?php if ($t['type'] === 'withdrawal'): ?>
                                        <input type="file" name="withdrawal_proof" accept="image/*" class="w-full p-2 border border-gray-200 rounded-lg text-sm" required>
                                        <?php endif; ?>
                                        <div class="flex space-x-2">
                                            <button type="submit" name="transaction_action" value="approve" class="btn-approve text-white p-2 rounded-lg w-full text-sm font-medium">Setujui</button>
                                            <button type="submit" name="transaction_action" value="reject" class="btn-reject text-white p-2 rounded-lg w-full text-sm font-medium">Tolak</button>
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
            
            <!-- Pinjaman -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center"><i class="fas fa-hand-holding-usd mr-2"></i> Riwayat Pinjaman</h2>
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">Tanggal</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Rekening</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Jumlah</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Durasi</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Bunga</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Status</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loans)): ?>
                            <tr><td colspan="7" class="p-4 text-center text-gray-500">Belum ada pinjaman.</td></tr>
                            <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base"><?php echo $loan['created_at']; ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($loan['account_number']) . ' (' . htmlspecialchars($loan['bank_name']) . ')'; ?></td>
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($loan['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo $loan['duration_months']; ?> bulan</td>
                                <td class="p-3 text-sm md:text-base"><?php echo $loan['interest_rate'] ?: '-'; ?>%</td>
                                <td class="p-3 text-sm md:text-base"><span class="status-<?php echo $loan['status']; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($loan['status'] === 'pending'): ?>
                                    <form method="POST" class="space-y-2">
                                        <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                        <input type="number" name="interest_rate" step="0.01" min="0" max="20" value="5.00" class="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <div class="flex space-x-2">
                                            <button type="submit" name="loan_action" value="approve" class="btn-approve text-white p-2 rounded-lg w-full text-sm font-medium">Setujui</button>
                                            <button type="submit" name="loan_action" value="reject" class="btn-reject text-white p-2 rounded-lg w-full text-sm font-medium">Tolak</button>
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
            
            <!-- Angsuran -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center"><i class="fas fa-calendar-check mr-2"></i> Angsuran</h2>
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">Pinjaman</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Rekening</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Angsuran Ke</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Jumlah</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Jatuh Tempo</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Status</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($installments)): ?>
                            <tr><td colspan="7" class="p-4 text-center text-gray-500">Belum ada angsuran.</td></tr>
                            <?php else: ?>
                            <?php foreach ($installments as $i): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($i['loan_amount'], 2, ',', '.'); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($i['account_number']) . ' (' . htmlspecialchars($i['bank_name']) . ')'; ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo $i['installment_number']; ?>/<?php echo $i['duration_months']; ?></td>
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($i['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo $i['due_date']; ?></td>
                                <td class="p-3 text-sm md:text-base"><span class="status-<?php echo $i['status']; ?>"><?php echo ucfirst($i['status']); ?></span></td>
                                <td class="p-3 text-sm md:text-base">
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
<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'nasabah') {
    header('Location: ../../public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = (int)$_POST['account_id'];
    $amount = (float)$_POST['amount'];
    $duration_months = (int)$_POST['duration_months'];
    $repayment_plan = $_POST['repayment_plan'];

    // Daftar semua dokumen syarat
    $files = [
        'ktp_copy' => 'Fotokopi KTP',
        'family_card' => 'Fotokopi Kartu Keluarga',
        'business_plan' => 'Rencana Usaha',
        'collateral_submission' => 'Pernyataan Penyerahan Agunan',
        'collateral_sale_approval' => 'Pernyataan Persetujuan Penjualan Agunan',
        'collateral_sale_power' => 'Surat Kuasa Penjualan Agunan',
        'installment_agreement' => 'Pernyataan Kesediaan Membayar Angsuran'
    ];

    $filePaths = [];
    $error = '';

    // Proses file tunggal
    foreach ($files as $key => $label) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES[$key]['name']);
            $target = '../../uploads/' . $fileName;
            if (move_uploaded_file($_FILES[$key]['tmp_name'], $target)) {
                $filePaths[$key] = $fileName;
            } else {
                $error = "Gagal mengunggah $label.";
                break;
            }
        } else {
            $error = "$label wajib diunggah.";
            break;
        }
    }

    // Proses foto (multiple files, maksimal 5)
    if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
        $photoCount = count($_FILES['photos']['name']);
        if ($photoCount > 5) {
            $error = "Pas Photo maksimal 5 lembar.";
        } else {
            $photoPaths = [];
            for ($i = 0; $i < $photoCount; $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = time() . '_photo_' . $i . '_' . basename($_FILES['photos']['name'][$i]);
                    $target = '../../uploads/' . $fileName;
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $target)) {
                        $photoPaths[] = $fileName;
                    } else {
                        $error = "Gagal mengunggah Pas Photo ke-$i.";
                        break;
                    }
                }
            }
            if (!empty($photoPaths)) {
                $filePaths['photos'] = implode(',', $photoPaths);
            }
        }
    } else {
        $error = "Pas Photo wajib diunggah (minimal 1, maksimal 5).";
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("INSERT INTO loans (user_id, account_id, amount, duration_months, ktp_copy, family_card, photos, business_plan, collateral_submission, collateral_sale_approval, collateral_sale_power, repayment_plan, installment_agreement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user']['id'],
            $account_id,
            $amount,
            $duration_months,
            $filePaths['ktp_copy'],
            $filePaths['family_card'],
            $filePaths['photos'],
            $filePaths['business_plan'],
            $filePaths['collateral_submission'],
            $filePaths['collateral_sale_approval'],
            $filePaths['collateral_sale_power'],
            $repayment_plan,
            $filePaths['installment_agreement']
        ]);
        header('Location: loans.php');
        exit();
    }
}

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$accounts = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT l.*, a.account_number, a.bank_name FROM loans l JOIN accounts a ON l.account_id = a.id WHERE l.user_id = ? ORDER BY l.created_at DESC");
$stmt->execute([$_SESSION['user']['id']]);
$loans = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pengajuan Pinjaman</title>
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
        .status-pending { color: #d97706; font-weight: 500; }
        .status-approved { color: #16a34a; font-weight: 500; }
        .status-rejected { color: #dc2626; font-weight: 500; }
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
                    <i class="fas fa-user-circle mr-2 text-blue-600"></i> Nasabah Portal
                </h2>
                <button id="close-sidebar" class="md:hidden text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="space-y-1">
                <a href="dashboard.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="accounts.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-university mr-2"></i> Rekening</a>
                <a href="deposit.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-arrow-down mr-2"></i> Simpan Uang</a>
                <a href="withdrawal.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-arrow-up mr-2"></i> Tarik Uang</a>
                <a href="transactions.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-exchange-alt mr-2"></i> Riwayat Transaksi</a>
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-hand-holding-usd mr-2"></i> Pinjaman</a>
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
                <i class="fas fa-hand-holding-usd mr-2 text-blue-600"></i> Pengajuan Pinjaman
            </h1>
            
            <!-- Pesan Error -->
            <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Form Pengajuan Pinjaman -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm mb-6">
                <h2 class="text-base md:text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i> Ajukan Pinjaman Baru
                </h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4 max-w-2xl">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Pilih Rekening</label>
                        <select name="account_id" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Pilih rekening tujuan</option>
                            <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['account_number'] . ' - ' . $account['bank_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Jumlah Pinjaman (Rp)</label>
                        <input type="number" name="amount" placeholder="Masukkan jumlah pinjaman" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1000000" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Durasi (bulan)</label>
                        <input type="number" name="duration_months" placeholder="Masukkan durasi" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1" max="60" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Fotokopi KTP</label>
                        <input type="file" name="ktp_copy" accept="image/*,application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Fotokopi Kartu Keluarga</label>
                        <input type="file" name="family_card" accept="image/*,application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Pas Photo Warna 3x4 (maksimal 5 lembar)</label>
                        <input type="file" name="photos[]" accept="image/*" multiple class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Rencana Usaha (PDF)</label>
                        <input type="file" name="business_plan" accept="application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Pernyataan Penyerahan Agunan (PDF)</label>
                        <input type="file" name="collateral_submission" accept="application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Pernyataan Persetujuan Penjualan Agunan (PDF)</label>
                        <input type="file" name="collateral_sale_approval" accept="application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Surat Kuasa Penjualan Agunan (PDF)</label>
                        <input type="file" name="collateral_sale_power" accept="application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Rencana Pengembalian Kredit</label>
                        <textarea name="repayment_plan" placeholder="Masukkan rencana pengembalian" class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4" required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Pernyataan Kesediaan Membayar Angsuran (PDF)</label>
                        <input type="file" name="installment_agreement" accept="application/pdf" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600" required>
                    </div>
                    <button type="submit" class="btn-primary text-white p-3 rounded-lg w-full font-medium">Ajukan Pinjaman</button>
                </form>
            </div>
            
            <!-- Riwayat Pinjaman -->
            <div class="bg-white p-4 md:p-6 rounded-lg border border-gray-100 shadow-sm">
                <h2 class="text-base md:text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-list mr-2"></i> Riwayat Pinjaman
                </h2>
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">Tanggal</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Rekening</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Jumlah</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Durasi</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="5" class="p-4 text-center text-gray-500">Belum ada riwayat pinjaman.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($loan['created_at']); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($loan['account_number'] . ' (' . $loan['bank_name'] . ')'); ?></td>
                                <td class="p-3 text-sm md:text-base">Rp <?php echo number_format($loan['amount'], 2, ',', '.'); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($loan['duration_months']); ?> bulan</td>
                                <td class="p-3 text-sm md:text-base">
                                    <span class="status-<?php echo $loan['status']; ?>">
                                        <?php echo ucfirst(htmlspecialchars($loan['status'])); ?>
                                    </span>
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
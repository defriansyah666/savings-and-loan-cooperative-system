<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../../public/login.php');
    exit();
}

// Direktori penyimpanan gambar
$uploadDir = '../uploads/notifications/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $image = null;

    // Proses upload gambar
    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . '-' . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $imageName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowedTypes) && $_FILES['image']['size'] <= 5000000) { // Maks 5MB
            move_uploaded_file($_FILES['image']['tmp_name'], $targetFile);
            $image = $imageName;
        } else {
            echo "File gambar tidak valid atau terlalu besar.";
            exit;
        }
    }

    if (isset($_POST['create'])) {
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, image) VALUES (?, ?, ?)");
        $stmt->execute([$title, $message, $image]);
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        if ($image) {
            // Hapus gambar lama jika ada
            $stmt = $pdo->prepare("SELECT image FROM notifications WHERE id = ?");
            $stmt->execute([$id]);
            $oldImage = $stmt->fetchColumn();
            if ($oldImage && file_exists($uploadDir . $oldImage)) {
                unlink($uploadDir . $oldImage);
            }
            $stmt = $pdo->prepare("UPDATE notifications SET title = ?, message = ?, image = ? WHERE id = ?");
            $stmt->execute([$title, $message, $image, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET title = ?, message = ? WHERE id = ?");
            $stmt->execute([$title, $message, $id]);
        }
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT image FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if ($image && file_exists($uploadDir . $image)) {
            unlink($uploadDir . $image);
        }
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: notifications.php'); // Redirect setelah proses CRUD
    exit();
}

// Ambil semua pemberitahuan
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Kelola Pemberitahuan</title>
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
                <a href="users.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-blue-600"><i class="fas fa-users mr-2"></i> Semua User</a>
                <a href="#" class="block py-2 px-4 text-gray-600 bg-gray-100 rounded-lg font-medium"><i class="fas fa-bell mr-2"></i> Pemberitahuan</a>
                <a href="../../public/logout.php" class="block py-2 px-4 text-gray-600 rounded-lg hover:text-red-600"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto main-content md:ml-0 md:pl-2">
            <!-- Mobile Menu Button -->
            <button id="toggle-sidebar" class="md:hidden mb-4 p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <h1 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-bell mr-2 text-blue-600"></i> Kelola Pemberitahuan
            </h1>

            <!-- Form Tambah Pemberitahuan -->
            <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100 mb-6">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-plus mr-2"></i> Tambah Pemberitahuan
                </h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="text" name="title" placeholder="Judul" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <textarea name="message" placeholder="Pesan" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4" required></textarea>
                    <input type="file" name="image" accept="image/*" class="w-full p-3 border border-gray-200 rounded-lg text-gray-600">
                    <button type="submit" name="create" class⁠="btn-primary text-white p-3 rounded-lg w-full font-medium">Tambah</button>
                </form>
            </div>

            <!-- Daftar Pemberitahuan -->
            <div class="card bg-white p-4 md:p-6 rounded-lg border border-gray-100">
                <h2 class="text-lg font-medium text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-list mr-2"></i> Daftar Pemberitahuan
                </h2>
                <div class="table-container">
                    <table class="w-full text-left text-gray-600 min-w-[768px]">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 font-semibold text-sm md:text-base">ID</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Judul</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Pesan</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Gambar</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Dibuat</th>
                                <th class="p-3 font-semibold text-sm md:text-base">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500">Belum ada pemberitahuan.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                            <tr class="table-row border-b border-gray-100">
                                <td class="p-3 text-sm md:text-base"><?php echo $notification['id']; ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($notification['title']); ?></td>
                                <td class="p-3 text-sm md:text-base"><?php echo htmlspecialchars($notification['message']); ?></td>
                                <td class="p-3 text-sm md:text-base">
                                    <?php if ($notification['image']): ?>
                                        <a href="../uploads/notifications/<?php echo htmlspecialchars($notification['image']); ?>" target="_blank">
                                            <img src="../uploads/notifications/<?php echo htmlspecialchars($notification['image']); ?>" alt="Gambar" class="h-12 w-auto">
                                        </a>
                                    <?php else: ?>
                                        Tidak ada
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-sm md:text-base"><?php echo $notification['created_at']; ?></td>
                                <td class="p-3 text-sm md:text-base">
                                    <button onclick="editNotification(<?php echo $notification['id']; ?>, '<?php echo htmlspecialchars($notification['title']); ?>', '<?php echo htmlspecialchars($notification['message']); ?>')" class="btn-link mr-2">Edit</button>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" name="delete" class="text-red-600 hover:underline" onclick="return confirm('Hapus pemberitahuan ini?')">Hapus</button>
                                    </form>
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

        function editNotification(id, title, message) {
            const newTitle = prompt("Masukkan judul baru:", title);
            const newMessage = prompt("Masukkan pesan baru:", message);
            if (newTitle && newMessage) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.enctype = 'multipart/form-data';
                form.innerHTML = `
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="title" value="${newTitle}">
                    <input type="hidden" name="message" value="${newMessage}">
                    <input type="hidden" name="update" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
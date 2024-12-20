<?php
// Include database connection and functions
require_once 'db.php';

// Check if user is admin, redirect if not
requireAdmin();

// Get user data
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];
$today = date('Y-m-d');

// Get all classes for dropdown
$stmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $stmt->fetchAll();

// Initialize message variables
$message = '';
$error = '';

// Handle student deletion
if (isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        setFlashMessage('success', "Data siswa berhasil dihapus.");
        header('Location: student_management.php');
        exit();
    } catch (PDOException $e) {
        logError("Error deleting student: " . $e->getMessage(), ['student_id' => $_POST['delete_id']]);
        setFlashMessage('error', "Gagal menghapus data siswa.");
        header('Location: student_management.php');
        exit();
    }
}

// Handle student add/edit
if (isset($_POST['save_student'])) {
    try {
        $data = [
            'nis' => sanitize($_POST['nis']),
            'name' => sanitize($_POST['name']),
            'gender' => sanitize($_POST['gender']),
            'birthdate' => sanitize($_POST['birthdate']),
            'address' => sanitize($_POST['address']),
            'parent_name' => sanitize($_POST['parent_name']),
            'parent_phone' => sanitize($_POST['parent_phone']),
            'class_id' => sanitize($_POST['class_id'])
        ];

        if (empty($_POST['student_id'])) {
            // Add new student
            $sql = "INSERT INTO students (nis, name, gender, birthdate, address, parent_name, parent_phone, class_id, status) 
                   VALUES (:nis, :name, :gender, :birthdate, :address, :parent_name, :parent_phone, :class_id, 'active')";
            setFlashMessage('success', "Data siswa berhasil ditambahkan.");
        } else {
            // Update existing student
            $sql = "UPDATE students SET 
                   nis = :nis, name = :name, gender = :gender, birthdate = :birthdate,
                   address = :address, parent_name = :parent_name, parent_phone = :parent_phone,
                   class_id = :class_id 
                   WHERE id = :id";
            $data['id'] = $_POST['student_id'];
            setFlashMessage('success', "Data siswa berhasil diperbarui.");
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        header('Location: student_management.php');
        exit();
    } catch (PDOException $e) {
        logError("Error saving student: " . $e->getMessage(), ['data' => $data]);
        setFlashMessage('error', "Terjadi kesalahan saat menyimpan data siswa.");
        header('Location: student_management.php');
        exit();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$class_filter = $_GET['class_filter'] ?? '';

// Prepare base query
$sql = "SELECT s.*, c.class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.status = 'active'";
$params = [];

// Add search conditions
if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.nis LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($class_filter)) {
    $sql .= " AND s.class_id = ?";
    $params[] = $class_filter;
}

$sql .= " ORDER BY s.name";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get flash messages
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - Sistem Absensi Sekolah</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f5f6fa;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --text-color: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--secondary-color);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 30px;
            padding: 10px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--text-color);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background-color: #eef2ff;
            color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .header-left p {
            color: #666;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .user-details h3 {
            font-size: 1rem;
            color: var(--text-color);
        }

        .user-details p {
            font-size: 0.85rem;
            color: #666;
        }

        /* Panel Styles */
        .panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        /* Search and Filters */
        .search-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .form-control {
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--text-color);
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #357abd;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-secondary {
            background-color: #eef2ff;
            color: var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: #dce4ff;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            animation: slideDown 0.3s ease-out;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }

            .search-filters {
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-school"></i>
            <span>Sistem Absensi</span>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Absensi</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="report.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Laporan</span>
                </a>
            </li>
            <?php if (isAdmin()): ?>
            <li class="nav-item">
                <a href="student_management.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    <span>Data Siswa</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user_settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Data Siswa</h1>
                <p><?php echo formatDate($today); ?></p>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($userName); ?></h3>
                    <p><?php echo $userRole === 'admin' ? 'Administrator' : 'Guru'; ?></p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Student Management Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-users"></i> Daftar Siswa</h2>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Tambah Siswa
                </button>
            </div>

            <div class="search-filters">
                <input type="text" class="form-control" id="search" placeholder="Cari nama atau NIS..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <select class="form-control" id="class_filter">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" 
                                <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" onclick="applyFilters()">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Jenis Kelamin</th>
                            <th>Tanggal Lahir</th>
                            <th>Nama Orang Tua</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['nis']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['class_name'] ?? '-'); ?></td>
                                <td><?php echo $student['gender'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($student['birthdate'])); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_name']); ?></td>
                                <td class="actions">
                                    <button class="btn btn-primary" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for Add/Edit Student -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle" class="text-xl font-bold mb-4">Tambah Siswa Baru</h2>
            <form id="studentForm" method="POST" action="">
                <input type="hidden" name="student_id" id="student_id">
                <input type="hidden" name="save_student" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nis">NIS</label>
                        <input type="text" id="nis" name="nis" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="gender">Jenis Kelamin</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="birthdate">Tanggal Lahir</label>
                        <input type="date" id="birthdate" name="birthdate" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Alamat</label>
                        <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="parent_name">Nama Orang Tua</label>
                        <input type="text" id="parent_name" name="parent_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="parent_phone">No. Telp Orang Tua</label>
                        <input type="tel" id="parent_phone" name="parent_phone" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="class_id">Kelas</label>
                        <select id="class_id" name="class_id" class="form-control" required>
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_id" id="delete_id">
    </form>

    <script>
        // Show modal for adding new student
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Siswa Baru';
            document.getElementById('studentForm').reset();
            document.getElementById('student_id').value = '';
            document.getElementById('studentModal').style.display = 'block';
        }

        // Show modal for editing student
        function editStudent(student) {
            document.getElementById('modalTitle').textContent = 'Edit Data Siswa';
            document.getElementById('student_id').value = student.id;
            document.getElementById('nis').value = student.nis;
            document.getElementById('name').value = student.name;
            document.getElementById('gender').value = student.gender;
            document.getElementById('birthdate').value = student.birthdate;
            document.getElementById('address').value = student.address;
            document.getElementById('parent_name').value = student.parent_name;
            document.getElementById('parent_phone').value = student.parent_phone;
            document.getElementById('class_id').value = student.class_id;
            document.getElementById('studentModal').style.display = 'block';
        }

        // Close modal
        function closeModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        // Delete student confirmation
        function deleteStudent(id) {
            if (confirm('Apakah Anda yakin ingin menghapus data siswa ini?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Apply search and filter
        function applyFilters() {
            const search = document.getElementById('search').value;
            const classFilter = document.getElementById('class_filter').value;
            window.location.href = `student_management.php?search=${encodeURIComponent(search)}&class_filter=${classFilter}`;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('studentForm').onsubmit = function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = 'red';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!valid) {
                e.preventDefault();
                alert('Mohon lengkapi semua field yang wajib diisi');
            }
        };
    </script>
</body>
</html>
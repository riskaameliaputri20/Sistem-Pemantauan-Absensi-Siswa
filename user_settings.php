<?php
require_once 'db.php';

// Check if user is admin
requireAdmin();

// Get user data
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];
$today = date('Y-m-d');

$message = '';
$error = '';

// Handle add/edit class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_class'])) {
    $id = $_POST['class_id'] ?? null;
    $className = sanitize($_POST['class_name']);
    $gradeLevel = sanitize($_POST['grade_level']);
    $academicYear = sanitize($_POST['academic_year']);

    try {
        if ($id) {
            // Update existing class
            $stmt = $pdo->prepare("UPDATE classes SET class_name=?, grade_level=?, academic_year=? WHERE id=?");
            $stmt->execute([$className, $gradeLevel, $academicYear, $id]);
            setFlashMessage('success', "Kelas berhasil diperbarui");
        } else {
            // Add new class
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, grade_level, academic_year) VALUES (?, ?, ?)");
            $stmt->execute([$className, $gradeLevel, $academicYear]);
            setFlashMessage('success', "Kelas baru berhasil ditambahkan");
        }
        header('Location: user_settings.php');
        exit();
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Handle delete class
if (isset($_POST['delete_class_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$_POST['delete_class_id']]);
        setFlashMessage('success', "Kelas berhasil dihapus");
        header('Location: user_settings.php');
        exit();
    } catch (PDOException $e) {
        $error = "Gagal menghapus kelas: " . $e->getMessage();
    }
}

// Handle add/edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id = $_POST['user_id'] ?? null;
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    $password = $_POST['password'] ?? '';

    try {
        if ($id) {
            // Update existing user
            if ($password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
                $stmt->execute([$name, $email, $role, $hashed_password, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                $stmt->execute([$name, $email, $role, $id]);
            }
            setFlashMessage('success', "User berhasil diperbarui");
        } else {
            // Add new user
            if (!$password) {
                throw new Exception("Password wajib diisi untuk user baru");
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password, $role]);
            setFlashMessage('success', "User baru berhasil ditambahkan");
        }
        header('Location: user_settings.php');
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Email sudah digunakan";
        } else {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle delete user
if (isset($_POST['delete_user_id'])) {
    try {
        if ($_POST['delete_user_id'] == $_SESSION['user_id']) {
            setFlashMessage('error', "Anda tidak dapat menghapus akun Anda sendiri");
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['delete_user_id']]);
            setFlashMessage('success', "Pengguna berhasil dihapus");
        }
        header('Location: user_settings.php');
        exit();
    } catch (PDOException $e) {
        $error = "Gagal menghapus pengguna: " . $e->getMessage();
    }
}

// Get all users except current admin
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id != ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Get flash messages
$flashMessage = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Pengguna - Sistem Absensi Sekolah</title>
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

        /* Role Badge */
        .role-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .role-admin {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .role-teacher {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
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
            max-width: 500px;
            margin: 50px auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            color: var(--text-color);
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

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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

        .alert-error {
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
            <li class="nav-item">
                <a href="student_management.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Data Siswa</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user_settings.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
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
                <h1>Pengaturan Pengguna</h1>
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

        <!-- Alert Messages -->
        <?php if (isset($flashMessage['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $flashMessage['success']; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Class Management Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-school"></i> Daftar Kelas</h2>
                <button class="btn btn-primary" onclick="showAddClassModal()">
                    <i class="fas fa-plus"></i> Tambah Kelas
                </button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Kelas</th>
                            <th>Tingkat</th>
                            <th>Tahun Ajaran</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->query("SELECT * FROM classes ORDER BY grade_level, class_name");
                        $classes = $stmt->fetchAll();
                        foreach ($classes as $class): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['grade_level']); ?></td>
                            <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                            <td>
                                <button class="btn btn-primary" onclick='editClass(<?php echo json_encode($class); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger" onclick="deleteClass(<?php echo $class['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fas fa-users-cog"></i> Daftar Pengguna</h2>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Tambah User
                </button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna <?php echo htmlspecialchars($user['name']); ?>?');">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for Add/Edit User -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah User Baru</h2>
                <button type="button" class="btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="userForm" method="POST" action="">
                <input type="hidden" name="user_id" id="user_id">
                <input type="hidden" name="save_user" value="1">

                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control">
                    <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="teacher">Guru</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group teacher-classes" style="display: none;">
                    <label for="classes">Kelas yang Diampu</label>
                    <select id="classes" name="classes[]" class="form-control" multiple>
                        <?php 
                        $classStmt = $pdo->query("SELECT id, class_name, grade_level FROM classes ORDER BY grade_level, class_name");
                        while ($class = $classStmt->fetch()) {
                            echo "<option value='{$class['id']}'>" . 
                                 htmlspecialchars($class['grade_level'] . ' ' . $class['class_name']) . 
                                 "</option>";
                        }
                        ?>
                    </select>
                    <small>Tahan Ctrl/Cmd untuk memilih beberapa kelas</small>
                </div>
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

    <!-- Class Modal -->
    <div id="classModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="classModalTitle">Tambah Kelas Baru</h2>
                <button type="button" class="btn" onclick="closeClassModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="classForm" method="POST" action="">
                <input type="hidden" name="class_id" id="class_id">
                <input type="hidden" name="save_class" value="1">

                <div class="form-group">
                    <label for="class_name">Nama Kelas</label>
                    <input type="text" id="class_name" name="class_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="grade_level">Tingkat</label>
                    <select id="grade_level" name="grade_level" class="form-control" required>
                        <option value="X">X (Sepuluh)</option>
                        <option value="XI">XI (Sebelas)</option>
                        <option value="XII">XII (Dua Belas)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="academic_year">Tahun Ajaran</label>
                    <select id="academic_year" name="academic_year" class="form-control" required>
                        <?php 
                        $currentYear = date('Y');
                        for($i = 0; $i < 3; $i++) {
                            $year = $currentYear + $i;
                            $academicYear = $year . '/' . ($year + 1);
                            echo "<option value='{$academicYear}'>{$academicYear}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeClassModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Forms -->
    <form id="deleteUserForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_id" id="delete_user_id">
    </form>

    <form id="deleteClassForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_class_id" id="delete_class_id">
    </form>

    <script>
        // Show modal for adding new user
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah User Baru';
            document.getElementById('userForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('password').required = true;
            document.getElementById('userModal').style.display = 'block';
        }

        // Show modal for editing user
        // Toggle teacher classes selection
        document.getElementById('role').addEventListener('change', function() {
            const teacherClasses = document.querySelector('.teacher-classes');
            if (this.value === 'teacher') {
                teacherClasses.style.display = 'block';
            } else {
                teacherClasses.style.display = 'none';
            }
        });

        // Show teacher classes when editing teacher
        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('user_id').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('role').value = user.role;
            document.getElementById('password').required = false;
            
            // Toggle teacher classes visibility
            const teacherClasses = document.querySelector('.teacher-classes');
            teacherClasses.style.display = user.role === 'teacher' ? 'block' : 'none';
            
            // Load assigned classes for teacher
            if (user.role === 'teacher') {
                fetch(`get_teacher_classes.php?teacher_id=${user.id}`)
                    .then(response => response.json())
                    .then(classes => {
                        const classSelect = document.getElementById('classes');
                        Array.from(classSelect.options).forEach(option => {
                            option.selected = classes.includes(parseInt(option.value));
                        });
                    });
            }
            
            document.getElementById('userModal').style.display = 'block';
        }

        // Close modal
        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        // Delete user confirmation
        function deleteUser(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus pengguna ' + name + '?')) {
                document.getElementById('delete_user_id').value = id;
                document.getElementById('deleteUserForm').submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('userForm').onsubmit = function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const isNewUser = !document.getElementById('user_id').value;

            let errors = [];

            if (!name) errors.push('Nama tidak boleh kosong');
            if (!email) errors.push('Email tidak boleh kosong');
            if (!email.match(/^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/)) {
                errors.push('Format email tidak valid');
            }
            if (isNewUser && !password) errors.push('Password wajib diisi untuk user baru');

            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
                return false;
            }
        };

        // Class Modal Functions
        function showAddClassModal() {
            document.getElementById('classModalTitle').textContent = 'Tambah Kelas Baru';
            document.getElementById('classForm').reset();
            document.getElementById('class_id').value = '';
            document.getElementById('classModal').style.display = 'block';
        }

        function editClass(classData) {
            document.getElementById('classModalTitle').textContent = 'Edit Kelas';
            document.getElementById('class_id').value = classData.id;
            document.getElementById('class_name').value = classData.class_name;
            document.getElementById('grade_level').value = classData.grade_level;
            document.getElementById('academic_year').value = classData.academic_year;
            document.getElementById('classModal').style.display = 'block';
        }

        function closeClassModal() {
            document.getElementById('classModal').style.display = 'none';
        }

        function deleteClass(id) {
            if (confirm('Apakah Anda yakin ingin menghapus kelas ini?')) {
                document.getElementById('delete_class_id').value = id;
                document.getElementById('deleteClassForm').submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Form validations
        document.getElementById('classForm').onsubmit = function(e) {
            const className = document.getElementById('class_name').value.trim();
            
            if (!className) {
                e.preventDefault();
                alert('Nama kelas tidak boleh kosong');
                return false;
            }
        };

        // Auto-hide success message
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }, 3000);
        }

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            const colors = ['#e74c3c', '#f1c40f', '#2ecc71'];
            const strengthLevel = strength < 3 ? 0 : strength < 4 ? 1 : 2;
            
            this.style.borderColor = colors[strengthLevel];
        });
    </script>
</body>
</html>
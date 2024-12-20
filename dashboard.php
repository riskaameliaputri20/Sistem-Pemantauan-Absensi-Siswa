<?php
require_once 'db.php';

// Check if user is logged in
requireLogin();

// Get user data
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];

// Get classes and their attendance data
$classes = getTeacherClasses($pdo, $userId);
$today = date('Y-m-d');

// Get attendance summary for all classes
$totalPresent = 0;
$totalSick = 0;
$totalPermitted = 0;
$totalAbsent = 0;

$classStats = [];
foreach ($classes as $class) {
    $stats = getAttendanceSummary($pdo, $class['id'], $today);
    $classStats[$class['id']] = $stats;
    
    $totalPresent += $stats['present'];
    $totalSick += $stats['sick'];
    $totalPermitted += $stats['permitted'];
    $totalAbsent += $stats['absent'];

    // Get total students in class
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND status = 'active'");
    $stmt->execute([$class['id']]);
    $classStats[$class['id']]['total_students'] = $stmt->fetchColumn();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Absensi Sekolah</title>
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

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
        }

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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .present .stat-icon {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .sick .stat-icon {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .permitted .stat-icon {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--info-color);
        }

        .absent .stat-icon {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--text-color);
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Class Cards Grid */
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .class-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .class-card:hover {
            transform: translateY(-5px);
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .class-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .student-count {
            background: #eef2ff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            color: var(--primary-color);
        }

        .class-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .attendance-stat {
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-type {
            font-size: 0.85rem;
            color: #666;
        }

        .class-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
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

        .btn-secondary {
            background-color: #eef2ff;
            color: var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: #dce4ff;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .class-grid {
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
                <a href="dashboard.php" class="nav-link active">
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
                <a href="student_management.php" class="nav-link">
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
                <h1>Dashboard</h1>
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

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card present">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number"><?php echo $totalPresent; ?></div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card sick">
                <div class="stat-icon">
                    <i class="fas fa-procedures"></i>
                </div>
                <div class="stat-number"><?php echo $totalSick; ?></div>
                <div class="stat-label">Sakit</div>
            </div>
            <div class="stat-card permitted">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-number"><?php echo $totalPermitted; ?></div>
                <div class="stat-label">Izin</div>
            </div>
            <div class="stat-card absent">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-number"><?php echo $totalAbsent; ?></div>
                <div class="stat-label">Alpa</div>
            </div>
        </div>

        <!-- Class Cards -->
        <div class="class-grid">
            <?php foreach ($classes as $class): ?>
                <div class="class-card">
                    <div class="class-header">
                        <div class="class-name">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </div>
                        <div class="student-count">
                            <?php echo $classStats[$class['id']]['total_students']; ?> Siswa
                        </div>
                    </div>
                    <div class="class-stats">
                        <div class="attendance-stat" style="background: rgba(46, 204, 113, 0.1);">
                        <div class="stat-value" style="color: var(--success-color);">
                                <?php echo $classStats[$class['id']]['present'] ?? 0; ?>
                            </div>
                            <div class="stat-type">Hadir</div>
                        </div>
                        <div class="attendance-stat" style="background: rgba(241, 196, 15, 0.1);">
                            <div class="stat-value" style="color: var(--warning-color);">
                                <?php echo $classStats[$class['id']]['sick'] ?? 0; ?>
                            </div>
                            <div class="stat-type">Sakit</div>
                        </div>
                        <div class="attendance-stat" style="background: rgba(52, 152, 219, 0.1);">
                            <div class="stat-value" style="color: var(--info-color);">
                                <?php echo $classStats[$class['id']]['permitted'] ?? 0; ?>
                            </div>
                            <div class="stat-type">Izin</div>
                        </div>
                        <div class="attendance-stat" style="background: rgba(231, 76, 60, 0.1);">
                            <div class="stat-value" style="color: var(--danger-color);">
                                <?php echo $classStats[$class['id']]['absent'] ?? 0; ?>
                            </div>
                            <div class="stat-type">Alpa</div>
                        </div>
                    </div>
                    <div class="class-actions">
                        <a href="attendance.php?class=<?php echo $class['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-clipboard-check"></i> Isi Absensi
                        </a>
                        <a href="report.php?class=<?php echo $class['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-chart-bar"></i> Lihat Laporan
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Animate numbers
        document.querySelectorAll('.stat-number, .stat-value').forEach(el => {
            const finalValue = parseInt(el.innerText);
            let currentValue = 0;
            const increment = finalValue / 30;
            const updateNumber = () => {
                if (currentValue < finalValue) {
                    currentValue += increment;
                    el.textContent = Math.round(currentValue);
                    requestAnimationFrame(updateNumber);
                } else {
                    el.textContent = finalValue;
                }
            };
            updateNumber();
        });
    </script>
</body>
</html>
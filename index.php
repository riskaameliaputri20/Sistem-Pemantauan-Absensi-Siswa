<?php
require_once 'db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$stats = getDashboardStats($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Sekolah</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f5f6fa;
            --accent-color: #2ecc71;
            --danger-color: #e74c3c;
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
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .navbar {
            background-color: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-color);
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .nav-links a:hover {
            background-color: var(--secondary-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }

        .present .stat-icon { color: var(--accent-color); }
        .absent .stat-icon { color: var(--danger-color); }
        .classes .stat-icon { color: var(--primary-color); }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu {
                display: block;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                <i class="fas fa-school"></i> SistemAbsensi
            </div>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="attendance.php"><i class="fas fa-clipboard-check"></i> Absensi</a>
                <a href="report.php"><i class="fas fa-chart-bar"></i> Laporan</a>
                <?php if (isAdmin()): ?>
                    <a href="student_management.php"><i class="fas fa-users"></i> Siswa</a>
                    <a href="user_settings.php"><i class="fas fa-cog"></i> Pengaturan</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card present">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo $stats['present']; ?></div>
                <div class="stat-label">Hadir Hari Ini</div>
            </div>
            
            <div class="stat-card absent">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-number"><?php echo $stats['absent']; ?></div>
                <div class="stat-label">Tidak Hadir Hari Ini</div>
            </div>
            
            <div class="stat-card classes">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number"><?php echo $stats['classes']; ?></div>
                <div class="stat-label">Kelas Minggu Ini</div>
            </div>
        </div>
    </div>

    <script>
        // Add any necessary JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Animation for stats numbers
            const numbers = document.querySelectorAll('.stat-number');
            numbers.forEach(num => {
                const value = parseInt(num.innerText);
                let current = 0;
                const increment = value / 20;
                const updateNumber = () => {
                    if (current < value) {
                        current += increment;
                        num.innerText = Math.round(current);
                        requestAnimationFrame(updateNumber);
                    } else {
                        num.innerText = value;
                    }
                };
                updateNumber();
            });
        });
    </script>
</body>
</html>
<?php
require_once 'db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get selected filters
$month = $_GET['month'] ?? date('Y-m');
$class_id = $_GET['class_id'] ?? '';

// Get classes based on user role
if (isAdmin()) {
    $stmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
} else {
    $stmt = $pdo->prepare("
        SELECT c.id, c.class_name 
        FROM classes c 
        INNER JOIN teacher_classes tc ON c.id = tc.class_id 
        WHERE tc.teacher_id = ?
        ORDER BY c.class_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
}
$classes = $stmt->fetchAll();

// Get attendance statistics
if ($class_id) {
    // Monthly attendance summary
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(attendance_date, '%d') as day,
            COUNT(CASE WHEN attendance_status = 'hadir' THEN 1 END) as present,
            COUNT(CASE WHEN attendance_status = 'sakit' THEN 1 END) as sick,
            COUNT(CASE WHEN attendance_status = 'izin' THEN 1 END) as permitted,
            COUNT(CASE WHEN attendance_status = 'alpa' THEN 1 END) as absent
        FROM attendance
        WHERE class_id = ? 
        AND attendance_date BETWEEN ? AND ?
        GROUP BY attendance_date
        ORDER BY attendance_date
    ");
    $stmt->execute([$class_id, $start_date, $end_date]);
    $daily_stats = $stmt->fetchAll();

    // Get student-wise statistics
    $stmt = $pdo->prepare("
        SELECT 
            s.name,
            COUNT(CASE WHEN a.attendance_status = 'hadir' THEN 1 END) as present,
            COUNT(CASE WHEN a.attendance_status = 'sakit' THEN 1 END) as sick,
            COUNT(CASE WHEN a.attendance_status = 'izin' THEN 1 END) as permitted,
            COUNT(CASE WHEN a.attendance_status = 'alpa' THEN 1 END) as absent
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id 
            AND a.attendance_date BETWEEN ? AND ?
        WHERE s.class_id = ?
        GROUP BY s.id, s.name
        ORDER BY s.name
    ");
    $stmt->execute([$start_date, $end_date, $class_id]);
    $student_stats = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik Absensi - Sistem Absensi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
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
            color: var(--text-color);
            line-height: 1.6;
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
            margin-bottom: 2rem;
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

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-color);
        }

        .stats-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-table th,
        .stats-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .stats-table th {
            background-color: var(--secondary-color);
            font-weight: 600;
        }

        .stats-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .stats-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-success { background-color: rgba(46, 204, 113, 0.1); color: var(--success-color); }
        .badge-warning { background-color: rgba(241, 196, 15, 0.1); color: var(--warning-color); }
        .badge-info { background-color: rgba(52, 152, 219, 0.1); color: var(--info-color); }
        .badge-danger { background-color: rgba(231, 76, 60, 0.1); color: var(--danger-color); }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .nav-links {
                display: none;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
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
                <a href="index.php"><i class="fas fa-home"></i> Beranda</a>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="attendance.php"><i class="fas fa-clipboard-check"></i> Absensi</a>
                <a href="report.php"><i class="fas fa-chart-bar"></i> Laporan</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <form method="GET" class="filters">
            <div class="form-group">
                <label for="class">Kelas</label>
                <select name="class_id" id="class" class="form-control" onchange="this.form.submit()">
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" 
                                <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="month">Bulan</label>
                <input type="month" name="month" id="month" class="form-control" 
                       value="<?php echo $month; ?>" onchange="this.form.submit()">
            </div>
        </form>

        <?php if ($class_id && !empty($daily_stats)): ?>
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-title">Tren Kehadiran Harian</div>
                    <canvas id="dailyChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Distribusi Status Kehadiran</div>
                    <canvas id="pieChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title">Statistik Kehadiran per Siswa</div>
                <div style="overflow-x: auto;">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Nama Siswa</th>
                                <th>Hadir</th>
                                <th>Sakit</th>
                                <th>Izin</th>
                                <th>Alpa</th>
                                <th>Persentase Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_stats as $stat): ?>
                                <?php
                                $total = $stat['present'] + $stat['sick'] + 
                                        $stat['permitted'] + $stat['absent'];
                                $percentage = $total > 0 ? 
                                    round(($stat['present'] / $total) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                    <td><span class="stats-badge badge-success"><?php echo $stat['present']; ?></span></td>
                                    <td><span class="stats-badge badge-warning"><?php echo $stat['sick']; ?></span></td>
                                    <td><span class="stats-badge badge-info"><?php echo $stat['permitted']; ?></span></td>
                                    <td><span class="stats-badge badge-danger"><?php echo $stat['absent']; ?></span></td>
                                    <td>
                                        <span class="stats-badge <?php echo $percentage >= 75 ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                // Daily attendance trend chart
                const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_column($daily_stats, 'day')); ?>,
                        datasets: [{
                            label: 'Hadir',
                            data: <?php echo json_encode(array_column($daily_stats, 'present')); ?>,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Sakit',
                            data: <?php echo json_encode(array_column($daily_stats, 'sick')); ?>,
                            borderColor: '#f1c40f',
                            backgroundColor: 'rgba(241, 196, 15, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Izin',
                            data: <?php echo json_encode(array_column($daily_stats, 'permitted')); ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Alpa',
                            data: <?php echo json_encode(array_column($daily_stats, 'absent')); ?>,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

                // Pie chart for attendance distribution
                const pieCtx = document.getElementById('pieChart').getContext('2d');
                new Chart(pieCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
                        datasets: [{
                            data: [
                                <?php 
                                $totals = array_reduce($daily_stats, function($carry, $item) {
                                    $carry['present'] += $item['present'];
                                    $carry['sick'] += $item['sick'];
                                    $carry['permitted'] += $item['permitted'];
                                    $carry['absent'] += $item['absent'];
                                    return $carry;
                                }, ['present' => 0, 'sick' => 0, 'permitted' => 0, 'absent' => 0]);
                                
                                echo implode(',', array_values($totals));
                                ?>
                            ],
                            backgroundColor: [
                                '#2ecc71',  // Hadir
                                '#f1c40f',  // Sakit
                                '#3498db',  // Izin
                                '#e74c3c'   // Alpa
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.raw / total) * 100).toFixed(1);
                                        return `${context.label}: ${context.raw} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });

                // Add animation to stats badges
                document.querySelectorAll('.stats-badge').forEach(badge => {
                    badge.style.transition = 'transform 0.3s ease';
                    badge.addEventListener('mouseover', () => {
                        badge.style.transform = 'scale(1.1)';
                    });
                    badge.addEventListener('mouseout', () => {
                        badge.style.transform = 'scale(1)';
                    });
                });

                // Export data functionality
                function exportToExcel() {
                    const table = document.querySelector('.stats-table');
                    const wb = XLSX.utils.table_to_book(table);
                    XLSX.writeFile(wb, 'statistik_absensi.xlsx');
                }
            </script>
        <?php else: ?>
            <div class="chart-card" style="text-align: center; padding: 40px;">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--primary-color);"></i>
                <p style="margin-top: 20px;">
                    Silakan pilih kelas dan bulan untuk melihat statistik absensi.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Handle filter changes
        document.getElementById('class').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('month').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>
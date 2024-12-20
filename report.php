<?php
require_once 'db.php';

// Check if user is logged in
requireLogin();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];

// Get classes based on user role
$classes = getTeacherClasses($pdo, $userId);

// Get filter parameters
$selected_class = $_GET['class'] ?? ($classes[0]['id'] ?? null);
$month = $_GET['month'] ?? date('Y-m');
$start_date = date('Y-m-01', strtotime($month));
$end_date = date('Y-m-t', strtotime($month));

// Get attendance data for selected class and period
if ($selected_class) {
    // Get student attendance summary
    $stmt = $pdo->prepare("CALL GetAttendanceSummary(?, ?, ?)");
    $stmt->execute([$selected_class, $start_date, $end_date]);
    $attendance_summary = $stmt->fetchAll();
    $stmt->closeCursor(); // Required for stored procedure

    // Get daily attendance for selected class
    $stmt = $pdo->prepare("
        SELECT 
            attendance_date,
            COUNT(CASE WHEN attendance_status = 'hadir' THEN 1 END) as present,
            COUNT(CASE WHEN attendance_status = 'sakit' THEN 1 END) as sick,
            COUNT(CASE WHEN attendance_status = 'izin' THEN 1 END) as permitted,
            COUNT(CASE WHEN attendance_status = 'alpa' THEN 1 END) as absent
        FROM attendance
        WHERE class_id = ? AND attendance_date BETWEEN ? AND ?
        GROUP BY attendance_date
        ORDER BY attendance_date
    ");
    $stmt->execute([$selected_class, $start_date, $end_date]);
    $daily_attendance = $stmt->fetchAll();

    // Get class info
    $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $stmt->execute([$selected_class]);
    $class_info = $stmt->fetch();
}

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_absensi_' . $class_info['class_name'] . '_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, ['Laporan Absensi - ' . $class_info['class_name']]);
    fputcsv($output, ['Periode: ' . formatDate($start_date) . ' - ' . formatDate($end_date)]);
    fputcsv($output, []); // Empty row
    fputcsv($output, ['NIS', 'Nama Siswa', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Persentase Kehadiran']);
    
    // Add data
    foreach ($attendance_summary as $row) {
        $total = $row['total_present'] + $row['total_sick'] + $row['total_permitted'] + $row['total_absent'];
        $percentage = $total > 0 ? round(($row['total_present'] / $total) * 100, 1) : 0;
        
        fputcsv($output, [
            $row['nis'],
            $row['student_name'],
            $row['total_present'],
            $row['total_sick'],
            $row['total_permitted'],
            $row['total_absent'],
            $percentage . '%'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi - Sistem Absensi Sekolah</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        /* Base Styles */
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
        /* Filters Section */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        /* Attendance Table */
        .attendance-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .attendance-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }

        .attendance-table tr:hover {
            background-color: #f8f9fa;
        }

        /* Radio Buttons */
        .status-radio {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #ddd;
            user-select: none;
        }

        .radio-label:hover {
            background-color: #f8f9fa;
        }

        .radio-input {
            display: none;
        }

        .radio-input:checked + .radio-label {
            border-color: var(--primary-color);
            background-color: #eef2ff;
            font-weight: 500;
        }

        .radio-input:checked + .radio-label.status-hadir {
            border-color: var(--success-color);
            background-color: rgba(46, 204, 113, 0.1);
        }

        .radio-input:checked + .radio-label.status-sakit {
            border-color: var(--warning-color);
            background-color: rgba(241, 196, 15, 0.1);
        }

        .radio-input:checked + .radio-label.status-izin {
            border-color: var(--info-color);
            background-color: rgba(52, 152, 219, 0.1);
        }

        .radio-input:checked + .radio-label.status-alpa {
            border-color: var(--danger-color);
            background-color: rgba(231, 76, 60, 0.1);
        }

        /* Notes Input */
        .notes-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .notes-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
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
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #f8f9fa;
            color: var(--text-color);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
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

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .status-radio {
                flex-direction: column;
            }
        }
        /* Report Specific Styles */
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            position: relative;
            height: 400px;
        }

        .chart-title {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 15px;
        }

        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .summary-label {
            color: #666;
            font-size: 0.9rem;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .report-table th,
        .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }

        .report-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .percentage-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .percentage-high {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .percentage-medium {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .percentage-low {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .export-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
/* Responsive Styles */
@media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .summary-cards {
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
                <a href="report.php" class="nav-link active">
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
                <h1>Laporan Absensi</h1>
                <?php if ($selected_class): ?>
                    <p>Kelas: <?php echo htmlspecialchars($class_info['class_name']); ?></p>
                <?php endif; ?>
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

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label for="class">Kelas</label>
                    <select name="class" id="class" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
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
        </div>

        <?php if ($selected_class && !empty($attendance_summary)): ?>
            <!-- Export Buttons -->
            <div class="export-buttons">
                <a href="?class=<?php echo $selected_class; ?>&month=<?php echo $month; ?>&export=excel" 
                   class="btn btn-primary">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>

            <!-- Charts -->
            <div class="chart-container">
                <h3 class="chart-title">Tren Kehadiran Harian</h3>
                <canvas id="dailyChart"></canvas>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <?php
                $totalAttendance = array_sum(array_column($attendance_summary, 'total_present')) +
                                array_sum(array_column($attendance_summary, 'total_sick')) +
                                array_sum(array_column($attendance_summary, 'total_permitted')) +
                                array_sum(array_column($attendance_summary, 'total_absent'));
                
                $presentPercentage = $totalAttendance > 0 ? 
                    round((array_sum(array_column($attendance_summary, 'total_present')) / $totalAttendance) * 100, 1) : 0;
                $sickPercentage = $totalAttendance > 0 ? 
                    round((array_sum(array_column($attendance_summary, 'total_sick')) / $totalAttendance) * 100, 1) : 0;
                $permittedPercentage = $totalAttendance > 0 ? 
                    round((array_sum(array_column($attendance_summary, 'total_permitted')) / $totalAttendance) * 100, 1) : 0;
                $absentPercentage = $totalAttendance > 0 ? 
                    round((array_sum(array_column($attendance_summary, 'total_absent')) / $totalAttendance) * 100, 1) : 0;
                ?>

                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(46, 204, 113, 0.1); color: var(--success-color);">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="summary-value"><?php echo $presentPercentage; ?>%</div>
                    <div class="summary-label">Kehadiran</div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(241, 196, 15, 0.1); color: var(--warning-color);">
                        <i class="fas fa-procedures"></i>
                    </div>
                    <div class="summary-value"><?php echo $sickPercentage; ?>%</div>
                    <div class="summary-label">Sakit</div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(52, 152, 219, 0.1); color: var(--info-color);">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="summary-value"><?php echo $permittedPercentage; ?>%</div>
                    <div class="summary-label">Izin</div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(231, 76, 60, 0.1); color: var(--danger-color);">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="summary-value"><?php echo $absentPercentage; ?>%</div>
                    <div class="summary-label">Alpa</div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="report-table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Hadir</th>
                            <th>Sakit</th>
                            <th>Izin</th>
                            <th>Alpa</th>
                            <th>Persentase Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_summary as $row): ?>
                            <?php
                            $total = $row['total_present'] + $row['total_sick'] + 
                                    $row['total_permitted'] + $row['total_absent'];
                            $percentage = $total > 0 ? 
                                round(($row['total_present'] / $total) * 100, 1) : 0;
                            
                            $percentageClass = $percentage >= 75 ? 'percentage-high' : 
                                            ($percentage >= 50 ? 'percentage-medium' : 'percentage-low');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nis']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo $row['total_present']; ?></td>
                                <td><?php echo $row['total_sick']; ?></td>
                                <td><?php echo $row['total_permitted']; ?></td>
                                <td><?php echo $row['total_absent']; ?></td>
                                <td>
                                    <span class="percentage-badge <?php echo $percentageClass; ?>">
                                        <?php echo $percentage; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                // Daily attendance trend chart
                const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) {
                            return date('d/m', strtotime($item['attendance_date']));
                        }, $daily_attendance)); ?>,
                        datasets: [{
                            label: 'Hadir',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['present'];
                            }, $daily_attendance)); ?>,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Sakit',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['sick'];
                            }, $daily_attendance)); ?>,
                            borderColor: '#f1c40f',
                            backgroundColor: 'rgba(241, 196, 15, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Izin',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['permitted'];
                            }, $daily_attendance)); ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Alpa',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['absent'];
                            }, $daily_attendance)); ?>,
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

                // Animate numbers
                document.querySelectorAll('.summary-value').forEach(el => {
                    const finalValue = parseFloat(el.innerText);
                    let currentValue = 0;
                    const increment = finalValue / 30;
                    const updateNumber = () => {
                        if (currentValue < finalValue) {
                            currentValue += increment;
                            el.textContent = currentValue.toFixed(1) + '%';
                            requestAnimationFrame(updateNumber);
                        } else {
                            el.textContent = finalValue.toFixed(1) + '%';
                        }
                    };
                    updateNumber();
                });
            </script>
        <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-info-circle" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 20px;"></i>
                <p>Silakan pilih kelas dan bulan untuk melihat laporan absensi.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
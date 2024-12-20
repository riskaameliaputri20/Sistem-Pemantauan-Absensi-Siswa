<?php
session_start();

// Database connection settings
$host = 'localhost';
$dbname = 'school_attendance';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fungsi untuk mengecek status login 
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk mengecek apakah user adalah admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Fungsi untuk mengecek apakah user adalah guru
function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

// Fungsi untuk mengecek login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Fungsi untuk mengecek admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

// Fungsi untuk mendapatkan kelas
function getTeacherClasses($pdo, $userId) {
    try {
        $stmt = $pdo->query("
            SELECT 
                c.*,
                COALESCE(s.total_students, 0) as total_students 
            FROM classes c
            LEFT JOIN (
                SELECT class_id, COUNT(*) as total_students 
                FROM students 
                WHERE status = 'active'
                GROUP BY class_id
            ) s ON c.id = s.class_id
            ORDER BY c.grade_level, c.class_name
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logError("Error getting teacher classes", ['error' => $e->getMessage()]);
        return [];
    }
}

// Fungsi untuk mendapatkan ringkasan absensi
function getAttendanceSummary($pdo, $classId, $date) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN attendance_status = 'hadir' THEN 1 END) as present,
                COUNT(CASE WHEN attendance_status = 'sakit' THEN 1 END) as sick,
                COUNT(CASE WHEN attendance_status = 'izin' THEN 1 END) as permitted,
                COUNT(CASE WHEN attendance_status = 'alpa' THEN 1 END) as absent
            FROM attendance 
            WHERE class_id = ? AND attendance_date = ?
        ");
        $stmt->execute([$classId, $date]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        logError("Error getting attendance summary", ['error' => $e->getMessage()]);
        return [
            'present' => 0,
            'sick' => 0,
            'permitted' => 0,
            'absent' => 0
        ];
    }
}

// Fungsi untuk menyimpan absensi
function saveAttendance($pdo, $studentId, $classId, $teacherId, $date, $status, $notes = '') {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND class_id = ? AND attendance_date = ?
        ");
        $stmt->execute([$studentId, $classId, $date]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET attendance_status = ?, notes = ?, teacher_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $teacherId, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance 
                (student_id, class_id, teacher_id, attendance_date, attendance_status, notes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$studentId, $classId, $teacherId, $date, $status, $notes]);
        }
        return true;
    } catch (PDOException $e) {
        logError("Error saving attendance", [
            'error' => $e->getMessage(),
            'student_id' => $studentId,
            'class_id' => $classId,
            'date' => $date
        ]);
        return false;
    }
}

// Fungsi untuk format tanggal ke format Indonesia
function formatDate($date) {
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

// Fungsi untuk sanitasi input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Fungsi untuk logging error
function logError($message, $context = []) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . " - " . json_encode($context) . "\n", 3, "error.log");
}
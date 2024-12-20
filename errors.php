<?php
// errors.php
function show404() {
    header("HTTP/1.0 404 Not Found");
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>404 - Halaman Tidak Ditemukan</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding-top: 50px;
                background: #f5f6fa;
            }
            .error-container {
                max-width: 500px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 { color: #e74c3c; }
            .back-link {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background: #4a90e2;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>404</h1>
            <h2>Halaman Tidak Ditemukan</h2>
            <p>Maaf, halaman yang Anda cari tidak ditemukan.</p>
            <a href="index.php" class="back-link">Kembali ke Beranda</a>
        </div>
    </body>
    </html>';
    exit();
}

function show403() {
    header("HTTP/1.0 403 Forbidden");
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>403 - Akses Ditolak</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding-top: 50px;
                background: #f5f6fa;
            }
            .error-container {
                max-width: 500px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 { color: #e74c3c; }
            .back-link {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background: #4a90e2;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>403</h1>
            <h2>Akses Ditolak</h2>
            <p>Maaf, Anda tidak memiliki akses ke halaman ini.</p>
            <a href="index.php" class="back-link">Kembali ke Beranda</a>
        </div>
    </body>
    </html>';
    exit();
}

// Fungsi untuk mencatat log aktivitas
function logActivity($user_id, $activity) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, activity, ip_address)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $activity, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Silent fail for logging
    }
}
?>

<?php
// security.php
class Security {
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            show403();
        }
    }

    public static function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    public static function validatePassword($password) {
        // Minimal 8 karakter
        if (strlen($password) < 8) return false;
        
        // Harus mengandung huruf besar, huruf kecil, dan angka
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        
        return true;
    }

    public static function preventBruteForce($email) {
        $attempts = $_SESSION['login_attempts'][$email] ?? 0;
        if ($attempts >= 5) {
            $last_attempt = $_SESSION['last_attempt'][$email] ?? 0;
            if (time() - $last_attempt < 900) { // 15 menit cooldown
                return false;
            }
            // Reset setelah cooldown
            unset($_SESSION['login_attempts'][$email]);
            unset($_SESSION['last_attempt'][$email]);
        }
        return true;
    }

    public static function updateLoginAttempts($email) {
        if (!isset($_SESSION['login_attempts'][$email])) {
            $_SESSION['login_attempts'][$email] = 1;
        } else {
            $_SESSION['login_attempts'][$email]++;
        }
        $_SESSION['last_attempt'][$email] = time();
    }
}
?>

<?php
// maintenance.php
function checkMaintenance() {
    $maintenance_file = __DIR__ . '/maintenance.flag';
    if (file_exists($maintenance_file)) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Maintenance Mode</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding-top: 50px;
                    background: #f5f6fa;
                }
                .maintenance-container {
                    max-width: 500px;
                    margin: 0 auto;
                    padding: 20px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                h1 { color: #f1c40f; }
                .icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="icon">🛠️</div>
                <h1>Mode Pemeliharaan</h1>
                <p>Sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.</p>
                <p>Terima kasih atas pengertian Anda.</p>
            </div>
        </body>
        </html>';
        exit();
    }
}
?>
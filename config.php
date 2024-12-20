<?php
// config.php
return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'school_attendance',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    'app' => [
        'name' => 'Sistem Absensi Sekolah',
        'url' => 'http://localhost/absensi',
        'version' => '1.0.0',
        'timezone' => 'Asia/Jakarta',
        'debug' => true
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',
        'password' => 'your-password',
        'encryption' => 'tls'
    ]
];
?>
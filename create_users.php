<?php
// create_users.php
require_once 'db.php';

try {
    // Create admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Administrator', 'admin@gmail.com', $admin_password, 'admin']);

    // Create teacher user
    $teacher_password = password_hash('guru123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Guru', 'guru@gmail.com', $teacher_password, 'teacher']);

    echo "Users created successfully!<br>";
    echo "Admin login: admin@gmail.com / admin123<br>";
    echo "Guru login: guru@gmail.com / guru123";

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "Users already exist!";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
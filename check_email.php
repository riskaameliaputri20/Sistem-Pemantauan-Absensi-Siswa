<?php
require_once 'db.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$email = $_GET['email'] ?? '';
$user_id = $_GET['user_id'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(['available' => false]));
}

try {
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    
    if ($user_id) {
        $sql .= " AND id != ?";
        $params[] = $user_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $available = $stmt->rowCount() === 0;
    
    echo json_encode(['available' => $available]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
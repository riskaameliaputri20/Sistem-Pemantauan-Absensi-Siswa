<?php
require_once 'db.php';

if (isset($_GET['teacher_id'])) {
    $stmt = $pdo->prepare("
        SELECT class_id 
        FROM teacher_classes 
        WHERE teacher_id = ?
    ");
    $stmt->execute([$_GET['teacher_id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($classes);
}
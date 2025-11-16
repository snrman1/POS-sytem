<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Staff ID not provided']);
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo json_encode(['error' => 'Staff member not found']);
    exit;
}

echo json_encode($staff);
?>
<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Staff ID is required']);
    exit;
}

$id = $_GET['id'];

try {
    // Get staff member by ID
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        http_response_code(404);
        echo json_encode(['error' => 'Staff member not found']);
        exit;
    }
    
    // Return staff data as JSON
    header('Content-Type: application/json');
    echo json_encode($staff);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
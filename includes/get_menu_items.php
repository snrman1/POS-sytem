<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT id, name, price, image FROM menu_items WHERE active = 1");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($items);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Failed to fetch menu items']);
}
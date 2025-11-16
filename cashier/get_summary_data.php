<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only allow cashier users to access this endpoint
if ($_SESSION['user']['role'] !== 'cashier') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$lastUpdate = $_GET['last_update'] ?? '';

try {
    // Check if there are any new orders since last update
    $hasUpdates = false;
    if ($lastUpdate) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM new_orders WHERE DATE(created_at) = ? AND created_at > ?");
        $stmt->execute([$selectedDate, $lastUpdate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $hasUpdates = $result['count'] > 0;
    } else {
        $hasUpdates = true; // If no last update time, assume there might be updates
    }
    
    if (!$hasUpdates) {
        echo json_encode(['hasUpdates' => false]);
        exit;
    }
    
    // Fetch updated summary data
    $totalSales = 0;
    $completedOrders = 0;
    $cancelledOrders = 0;
    $amendedOrders = 0;
    $orderTypeCounts = ['dine-in' => 0, 'take-away' => 0];
    
    // Fetch order summary
    $stmt = $pdo->prepare("SELECT status, order_type, SUM(total) as total, COUNT(*) as count FROM new_orders WHERE DATE(created_at) = ? GROUP BY status, order_type");
    $stmt->execute([$selectedDate]);
    $summaryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summaryRows as $row) {
        $totalSales += (float)$row['total'];
        if ($row['status'] === 'completed') $completedOrders += $row['count'];
        if ($row['status'] === 'cancelled') $cancelledOrders += $row['count'];
        if ($row['status'] === 'amended') $amendedOrders += $row['count'];
        if (isset($orderTypeCounts[$row['order_type']])) $orderTypeCounts[$row['order_type']] += $row['count'];
    }
    
    // Fetch top selling items
    $stmt = $pdo->prepare("SELECT item_name, SUM(quantity) as qty FROM order_items WHERE order_id IN (SELECT id FROM new_orders WHERE DATE(created_at) = ?) GROUP BY item_name ORDER BY qty DESC LIMIT 4");
    $stmt->execute([$selectedDate]);
    $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch order details
    $stmt = $pdo->prepare("SELECT o.*, u.username as cashier_name, s.name as waiter_name FROM new_orders o LEFT JOIN users u ON o.cashier_id = u.id LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter' WHERE DATE(o.created_at) = ? ORDER BY o.created_at DESC");
    $stmt->execute([$selectedDate]);
    $orderDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch items for each order
    foreach ($orderDetails as &$order) {
        $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$order['id']]);
        $order['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order); // break reference
    
    // Return updated data
    header('Content-Type: application/json');
    echo json_encode([
        'hasUpdates' => true,
        'totalSales' => $totalSales,
        'completedOrders' => $completedOrders,
        'cancelledOrders' => $cancelledOrders,
        'amendedOrders' => $amendedOrders,
        'orderTypeCounts' => $orderTypeCounts,
        'topItems' => $topItems,
        'orderDetails' => $orderDetails
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();
    
    // Insert order header
    $stmt = $pdo->prepare("
        INSERT INTO new_orders (
            order_number, 
            order_type, 
            waiter_id, 
            table_number, 
            subtotal, 
            gt_levy, 
            nhil, 
            gf_levy, 
            vat, 
            total, 
            payment_method, 
            status, 
            created_at
        ) VALUES (
            :order_number, 
            :order_type, 
            :waiter_id, 
            :table_number, 
            :subtotal, 
            :gt_levy, 
            :nhil, 
            :gf_levy, 
            :vat, 
            :total, 
            :payment_method, 
            'active', 
            NOW()
        )
    ");
    
    // Generate order number (you might want to use your existing logic)
    $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $stmt->execute([
        ':order_number' => $orderNumber,
        ':order_type' => $data['order_type'],
        ':waiter_id' => $data['waiter_id'],
        ':table_number' => $data['table_number'],
        ':subtotal' => $data['subtotal'],
        ':gt_levy' => $data['gt_levy'],
        ':nhil' => $data['nhil'],
        ':gf_levy' => $data['gf_levy'],
        ':vat' => $data['vat'],
        ':total' => $data['total'],
        ':payment_method' => $data['payment_method']
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Insert order items
    foreach ($data['items'] as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, 
                menu_item_id, 
                item_name, 
                price, 
                quantity, 
                total
            ) VALUES (
                :order_id, 
                :menu_item_id, 
                :item_name, 
                :price, 
                :quantity, 
                :total
            )
        ");
        
        $stmt->execute([
            ':order_id' => $orderId,
            ':menu_item_id' => $item['id'],
            ':item_name' => $item['name'],
            ':price' => $item['price'],
            ':quantity' => $item['quantity'],
            ':total' => $item['total']
        ]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'order_id' => $orderId]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only allow cashier users to access POS
if ($_SESSION['user']['role'] !== 'cashier') {
    header('Location: /admin/');
    exit;
}

// Fetch active orders
$activeOrders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, s.name as waiter_name 
        FROM new_orders o
        LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
        WHERE o.status = 'active'
        AND DATE(o.created_at) = CURDATE()
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
}

// Handle order selection
$selectedOrder = null;
if (isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    
    try {
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.*, s.name as waiter_name 
            FROM new_orders o
            LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get order items if order exists
        if ($selectedOrder) {
            $stmt = $pdo->prepare("
                SELECT * FROM order_items 
                WHERE order_id = ?
            ");
            $stmt->execute([$orderId]);
            $selectedOrder['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching order details: " . $e->getMessage();
    }
}

// Handle complete payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_payment'])) {
    $orderId = (int)$_POST['order_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE new_orders 
            SET status = 'completed', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$orderId]);
        
        $_SESSION['success_message'] = "Order #$orderId marked as completed!";
        header("Location: billing.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error completing order: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Billing System</title>
    <link rel="stylesheet" href="../assets/css/billing.css">
    <link rel="stylesheet" href="../assets/css/all.css">
    <style>
        .order-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .order-card.active {
            border-left: 4px solid #4CAF50;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include '../includes/cashier_header.php'; ?>

    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-money-bill-trend-up"></i> Billing Orders</h1>
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['success_message'] ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>
            </div>
            <div class="main-container">
                <section class="billing-order">
                    <?php if (empty($activeOrders)): ?>
                        <div class="empty-state">
                        <div class="no-orders"><i class="fas  fa-clipboard-list"></i><h3> No active orders found</h3></div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeOrders as $order): ?>
                            <div class="order-card <?= ($selectedOrder && $selectedOrder['id'] == $order['id']) ? 'active' : '' ?>"
                                 onclick="window.location.href='billing.php?order_id=<?= $order['id'] ?>'">
                                <div class="order-header">
                                    <span class="order-number"><?= htmlspecialchars($order['order_number']) ?></span>
                                    <span class="order-amount">$<?= number_format($order['total'], 2) ?></span>
                                </div>
                                <div class="order-details">
                                    <span>
                                        <?= $order['table_number'] ? 'Table: ' . htmlspecialchars($order['table_number']) : 'Takeaway' ?>
                                        | <?= ucfirst(str_replace('-', ' ', $order['order_type'])) ?>
                                    </span>
                                    <span><?= date('Y-m-d | H:i', strtotime($order['created_at'])) ?></span>
                                </div>
                                <button class="bill-btn">Bill Now</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <section class="billing-details">
                    <?php if ($selectedOrder): ?>
                        <h2>Billing Details</h2>
                        <hr>
                        <div class="billing-details">
                            <div class="detail-row">
                                <span class="detail-label">Order Number</span>
                                <span class="detail-value"><?= htmlspecialchars($selectedOrder['order_number']) ?></span>

                                <span class="detail-label">Date & Time</span>
                                <span class="detail-value"><?= date('Y-m-d H:i', strtotime($selectedOrder['created_at'])) ?></span>
                            </div>
                            <div class="divider"></div>

                            <div class="detail-row">
                                <span class="detail-label">Table Number</span>
                                <span class="detail-value">
                                    <?= $selectedOrder['table_number'] ? htmlspecialchars($selectedOrder['table_number']) : 'N/A' ?>
                                </span>

                                <span class="detail-label">Order Type</span>
                                <span class="detail-value">
                                    <?= ucfirst(str_replace('-', ' ', $selectedOrder['order_type'])) ?>
                                </span>
                            </div>
                            <div class="divider"></div>

                            <div class="detail-row">
                                <span class="detail-label">Waiter</span>
                                <span class="detail-value">
                                    <?= $selectedOrder['waiter_name'] ? htmlspecialchars($selectedOrder['waiter_name']) : 'N/A' ?>
                                </span>

                                <span class="detail-label">Payment Method</span>
                                <span class="detail-value">
                                    <?= ucfirst($selectedOrder['payment_method']) ?>
                                </span>
                            </div>
                        </div>

                        <hr>
                        <h3 class="section-title">Order Items</h3>
                        <table class="order-items">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedOrder['items'] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>₵<?= number_format($item['price'], 2) ?></td>
                                        <td>₵<?= number_format($item['total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <tr class="total-row" >
                                    <td colspan="3">Subtotal:</td>
                                    <td>₵<?= number_format($selectedOrder['subtotal'], 2) ?></td>
                                </tr>
                                <tr class="total-row">
                                    <td colspan="3">GT Levy (1%):</td>
                                    <td>₵<?= number_format($selectedOrder['gt_levy'], 2) ?></td>
                                </tr>
                                <tr class="total-row">
                                    <td colspan="3">NHiL (2.5%):</td>
                                    <td>₵<?= number_format($selectedOrder['nhil'], 2) ?></td>
                                </tr>
                                <tr class="total-row">
                                    <td colspan="3">GF Levy (2.5%):</td>
                                    <td>$<?= number_format($selectedOrder['gf_levy'], 2) ?></td>
                                </tr>
                                <tr class="total-row">
                                    <td colspan="3">VAT (12.5%):</td>
                                    <td>$<?= number_format($selectedOrder['vat'], 2) ?></td>
                                </tr>
                                <tr class="total-row">
                                    <td colspan="3">Total:</td>
                                    <td>$<?= number_format($selectedOrder['total'], 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="action-buttons">
                            <button class="print-btn" onclick="printOrderReceipt()"><i class="fas fa-print"></i> Print Bill</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?= $selectedOrder['id'] ?>">
                                <button type="submit" name="complete_payment" class="payment-btn">
                                    Complete Payment
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                        <div class="no-orders"><i class="fas  fa-clipboard-list"></i><h3> Select an order to view details</h3></div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <script>
        // Print function
        function printOrderReceipt() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Order Receipt #<?= $selectedOrder['order_number'] ?? '' ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                        .receipt { width: 80mm; margin: 0 auto; }
                        .header { text-align: center; margin-bottom: 10px; }
                        .title { font-size: 18px; font-weight: bold; }
                        .order-info { margin-bottom: 15px; }
                        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                        .items-table th { text-align: left; border-bottom: 1px dashed #000; padding: 5px 0; }
                        .items-table td { padding: 3px 0; }
                        .total-row { font-weight: bold; border-top: 1px dashed #000; }
                        .footer { text-align: center; margin-top: 15px; font-size: 12px; }
                        @media print {
                            body { width: 80mm; }
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt">
                        <div class="header">
                            <div class="title">YOUR RESTAURANT NAME</div>
                            <div>123 Main Street, City</div>
                            <div>Tel: (123) 456-7890</div>
                        </div>
                        
                        <div class="order-info">
                            <div><strong>Order #:</strong> <?= $selectedOrder['order_number'] ?? '' ?></div>
                            <div><strong>Date:</strong> <?= date('Y-m-d H:i', strtotime($selectedOrder['created_at'] ?? 'now')) ?></div>
                            <div><strong>Type:</strong> <?= isset($selectedOrder['order_type']) ? ucfirst(str_replace('-', ' ', $selectedOrder['order_type'])) : '' ?></div>
                            <?php if (isset($selectedOrder['table_number']) && $selectedOrder['table_number']): ?>
                                <div><strong>Table:</strong> <?= htmlspecialchars($selectedOrder['table_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($selectedOrder['items'])): ?>
                                    <?php foreach ($selectedOrder['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                                                                    <td>₵<?= number_format($item['price'], 2) ?></td>
                                        <td>₵<?= number_format($item['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <div class="totals">
                            <div>Subtotal: $<?= isset($selectedOrder['subtotal']) ? number_format($selectedOrder['subtotal'], 2) : '0.00' ?></div>
                            <div>GT Levy (1%): $<?= isset($selectedOrder['gt_levy']) ? number_format($selectedOrder['gt_levy'], 2) : '0.00' ?></div>
                            <div>NHIL (2.5%): $<?= isset($selectedOrder['nhil']) ? number_format($selectedOrder['nhil'], 2) : '0.00' ?></div>
                            <div>GF Levy (2.5%): $<?= isset($selectedOrder['gf_levy']) ? number_format($selectedOrder['gf_levy'], 2) : '0.00' ?></div>
                            <div>VAT (12.5%): $<?= isset($selectedOrder['vat']) ? number_format($selectedOrder['vat'], 2) : '0.00' ?></div>
                            <div><strong>Total: $<?= isset($selectedOrder['total']) ? number_format($selectedOrder['total'], 2) : '0.00' ?></strong></div>
                        </div>
                        
                        <div class="payment-method">
                            <div><strong>Payment Method:</strong> <?= isset($selectedOrder['payment_method']) ? ucfirst($selectedOrder['payment_method']) : '' ?></div>
                        </div>
                        
                        <div class="footer">
                            Thank you for dining with us!<br>
                            Please come again
                        </div>
                    </div>
                    
                    <button onclick="window.print()" style="margin: 20px auto; display: block; padding: 10px 20px;">
                        Print Receipt
                    </button>
                </body>
                </html>
            `);
            printWindow.document.close();
            
            // Auto-print after a short delay
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 300);
        }
    </script>
</body>
</html>
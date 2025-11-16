<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only allow cashier users to access POS
if ($_SESSION['user']['role'] !== 'cashier') {
    header('Location: /admin/');
    exit;
}

$activeOrders = [];
$completedOrders = [];
$selectedOrder = null;
$menuItems = [];

try {
    // Fetch active orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as cashier_name, s.name as waiter_name
        FROM new_orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
        WHERE o.status = 'active'
        AND DATE(o.created_at) = CURDATE()
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch completed orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as cashier_name, s.name as waiter_name
        FROM new_orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
        WHERE o.status = 'completed'
        AND DATE(o.created_at) = CURDATE()
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $completedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch menu items
    $menuItems = $pdo->query("SELECT * FROM menu_items WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch selected order details if order_id is set
    if (isset($_GET['order_id'])) {
        $orderId = (int)$_GET['order_id'];
        $stmt = $pdo->prepare("
            SELECT o.*, u.username as cashier_name, s.name as waiter_name
            FROM new_orders o
            LEFT JOIN users u ON o.cashier_id = u.id
            LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedOrder) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $selectedOrder['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE new_orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    header("Location: modify.php");
    exit;
}

// Handle save changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    $orderId = (int)$_POST['order_id'];
    $items = json_decode($_POST['items'], true);

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, price, quantity, total) VALUES (?, ?, ?, ?, ?, ?)");

    $subtotal = 0;
    foreach ($items as $item) {
        $total = $item['price'] * $item['quantity'];
        $stmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $total]);
        $subtotal += $total;
    }
    // Calculate taxes
    $gtLevy = $subtotal * 0.01;
    $nhil = $subtotal * 0.025;
    $gfLevy = $subtotal * 0.025;
    $vat = $subtotal * 0.125;
    $total = $subtotal + $gtLevy + $nhil + $gfLevy + $vat;

    $pdo->prepare("UPDATE new_orders SET subtotal=?, gt_levy=?, nhil=?, gf_levy=?, vat=?, total=?, status='amended', updated_at=NOW() WHERE id=?")
        ->execute([$subtotal, $gtLevy, $nhil, $gfLevy, $vat, $total, $orderId]);
    $pdo->commit();

    header("Location: modify.php?order_id=$orderId");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Amendment System</title>
    <link rel="stylesheet" href="../assets/css/modify.css">
    <link rel="stylesheet" href="../assets/css/all.css">
    <script src="../assets/js/modify.js?<?php echo time(); ?>"></script>

</head>

<body>
    <?php include '../includes/cashier_header.php'; ?>
    <script>
    window.selectedOrderId = <?= isset($selectedOrder['id']) ? (int)$selectedOrder['id'] : 'null' ?>;
</script>

    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-pencil"></i> Modify Orders</h1>
            </div>

            <div class="main-container">
                <div class="sidebar">

                    <h2>Order Amendment</h2>
                    <!-- Tabs -->
                    <div class="tabs">
                        <div class="tab active" id="new-orders-tab">New Orders</div>
                        <div class="tab" id="billed-orders-tab">Billed Orders</div>
                    </div>

                    <div class="newOrder-list" id="new-orders-list">
                        <?php foreach ($activeOrders as $order): ?>
                            <div class="order-card" data-id="<?= $order['id'] ?>">
                                <div class="order-header">
                                    <span class="order-number"><?= htmlspecialchars($order['order_number']) ?></span>
                                    <span class="order-total">₵<?= number_format($order['total'], 2) ?></span>
                                </div>
                                <div class="order-details">
                                    <?= $order['table_number'] ? 'Table ' . htmlspecialchars($order['table_number']) : 'Takeaway' ?> -
                                    <?= ucfirst(str_replace('-', ' ', $order['order_type'])) ?> •
                                    <?= date('g:i A', strtotime($order['created_at'])) ?>
                                </div>
                                <div class="order-waiter">
                                    <span><?= htmlspecialchars($order['waiter_name'] ?? 'N/A') ?></span>
                                    <span class="status active"><?= ucfirst($order['status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($activeOrders)): ?>
                            <div class="no-orders"><i class="fas  fa-clipboard-list"></i><h3> No active orders found</h3></div>
                        <?php endif; ?>
                    </div>

                    <div class="billedOrder-list" id="billed-orders-list" style="display:none;">
                        <?php foreach ($completedOrders as $order): ?>
                            <div class="order-card" data-id="<?= $order['id'] ?>">
                                <div class="order-header">
                                    <span class="order-number"><?= htmlspecialchars($order['order_number']) ?></span>
                                    <span class="order-total">₵<?= number_format($order['total'], 2) ?></span>
                                </div>
                                <div class="order-details">
                                    <?= $order['table_number'] ? 'Table ' . htmlspecialchars($order['table_number']) : 'Takeaway' ?> -
                                    <?= ucfirst(str_replace('-', ' ', $order['order_type'])) ?> •
                                    <?= date('g:i A', strtotime($order['created_at'])) ?>
                                </div>
                                <div class="order-waiter">
                                    <span><?= htmlspecialchars($order['waiter_name'] ?? 'N/A') ?></span>
                                    <span class="status completed"><?= ucfirst($order['status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($completedOrders)): ?>
                            <div class="no-orders"><i class="fas  fa-clipboard-list"></i><h3> No billed orders found</h3></div>
                        <?php endif; ?>
                    </div>

                    <!-- Order Details and Modify Section (show if $selectedOrder) -->
                    <?php if ($selectedOrder): ?>
                        <!-- Order details and modify form go here -->
                    <?php endif; ?>


                </div>
                <div class="main-content">
                    <?php if ($selectedOrder): ?>
                        <div class="order-details">
                            <h2>Order Details</h2>
                            <div class="info-group">
                                <span class="info-label">Order Number:</span>
                                <span class="info-value"><?= htmlspecialchars($selectedOrder['order_number']) ?></span>
                                <span class="info-label">Date & Time:</span>
                                <span class="info-value"><?= date('F j, Y - g:i A', strtotime($selectedOrder['created_at'])) ?></span>
                            </div>
                            <div class="divider"></div>
                            <div class="info-group">
                                <span class="info-label">Table Number:</span>
                                <span class="info-value"><?= $selectedOrder['table_number'] ? htmlspecialchars($selectedOrder['table_number']) : 'N/A' ?></span>
                                <span class="info-label">Order Type:</span>
                                <span class="info-value"><?= ucfirst(str_replace('-', ' ', $selectedOrder['order_type'])) ?></span>
                            </div>
                            <div class="divider"></div>
                            <div class="info-group">
                                <span class="info-label">Waiter:</span>
                                <span class="info-value"><?= htmlspecialchars($selectedOrder['waiter_name'] ?? 'N/A') ?></span>
                                <span class="info-label">Status:</span>
                                <span class="info-value"><span class="status <?= $selectedOrder['status'] ?>"><?= ucfirst($selectedOrder['status']) ?></span></span>
                            </div>
                            <div class="divider"></div>
                            <div class="info-group">
                                <span class="info-label">Payment Method:</span>
                                <span class="info-value"><?= ucfirst($selectedOrder['payment_method']) ?></span>
                            </div>
                            <hr>

                            <div class="order-items-list">
                                <h3>Order Items</h3>
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
                                        <?php foreach ($selectedOrder['items'] as $item): ?>
                                            <tr data-id="<?= $item['id'] ?>">
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td>
                                                    <div class="quantity-control">
                                                        <button class="quantity-btn">-</button>
                                                        <span class="quantity"><?= $item['quantity'] ?></span>
                                                        <button class="quantity-btn">+</button>
                                                    </div>
                                                </td>
                                                <td>₵<?= number_format($item['price'], 2) ?></td>
                                                <td>₵<?= number_format($item['total'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>₵<?= number_format($selectedOrder['subtotal'] ?? 0, 2) ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Total Tax (18.25%)</span>
                                <span>₵<?= number_format(
                                            ($selectedOrder['gt_levy'] ?? 0) +
                                                ($selectedOrder['nhil'] ?? 0) +
                                                ($selectedOrder['gf_levy'] ?? 0) +
                                                ($selectedOrder['vat'] ?? 0),
                                            2
                                        ) ?>
                                </span>
                            </div>
                            <div class="summary-row">
                                <span>Total</span>
                                <span>₵<?= number_format($selectedOrder['total'] ?? 0, 2) ?></span>
                            </div>

                            <div class="action-buttons">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $selectedOrder['id'] ?>">
                                    <button type="submit" name="cancel_order" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Cancel Order
                                    </button>
                                </form>
                                <button class="btn btn-outline">
                                    <i class="fas fa-print"></i> Print Order
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-orders"><i class="fas  fa-clipboard-list"></i><h3> No order selected or order not found </h3></div>
                        <?php endif; ?>

                </div>

                <div class="modify-order">
                    <h2>Modify Order</h2>

                    <h4>Add Items</h4>
                    <div class="menu-items-grid">
                        <?php foreach ($menuItems as $item): ?>
                            <div class="menu-item">
                                <h5><?= htmlspecialchars($item['name']) ?></h5>
                                <p>₵<?= number_format($item['price'], 2) ?></p>
                                <button class="btn btn-outline add-item-btn" data-id="<?= $item['id'] ?>">Add Item</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-outline">
                        <i class="fas fa-plus"></i> View Full Menu
                    </button>

                    <div class="footer-actions">
                        <button id="discardChangesBtn" class="btn btn-outline">Discard Changes</button>
                        <button id="saveChangesBtn" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>




                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add Menu Items</h2>
            <input type="text" id="menuSearch" placeholder="Search menu items..." class="search-bar">

            <div class="menu-items-container" id="menuItemsContainer">
                <!-- Menu items will be loaded here via AJAX -->
            </div>
        </div>
    </div>




</body>

</html>
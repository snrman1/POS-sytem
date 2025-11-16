<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only allow cashier users to access POS
if ($_SESSION['user']['role'] !== 'cashier') {
    header('Location: /admin/');
    exit;
}

// Fetch waiters for dropdown
try {
    $waitersQuery = $pdo->query("SELECT id, name FROM staff WHERE role = 'waiter' ");
    $waiters = $waitersQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $waiters = [];
    // Log error if needed
}

// Generate order number
$orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Posment</title>

    <link rel="stylesheet" href="../assets/css/new_order.css">
    <link rel="stylesheet" href="../assets/css/all.css">
</head>

<body>
    <?php include '../includes/cashier_header.php'; ?>
    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-kitchen-set"></i> New Order</h1>
            </div>
            <div class="main-container">
                <!-- New Order -->
                <div class="order-form">

                    <div class="order-details">
                        <table width="100%">
                            <tr>
                                <td>
                                    <div class="form-group">
                                        <label for="orderType">Order Type</label>
                                        <select id="orderType">
                                            <option value="dine-in">Dine-in</option>
                                            <option value="take-away">Take Away</option>
                                        </select>
                                    </div>
                                </td>

                                <td>
                                    <div class="form-group">
                                        <label for="waiter">Waiter</label>
                                        <select id="waiter">
                                            <option value="">Select Waiter</option>
                                            <?php foreach ($waiters as $waiter): ?>
                                                <option value="<?= $waiter['id'] ?>"><?= htmlspecialchars($waiter['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group">
                                        <label>Table #</label>
                                        <input type="text" id="tableNumber">
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group">
                                        <label>Order #</label>
                                        <input type="text" id="orderNumber" value="<?= $orderNumber ?>" readonly>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <hr>
                    <div class="menu-items">
                        <div class="right">
                            <h3>Menu Items</h3>
                        </div>
                        <div class="left">
                            <div class="dropdown">
                                <button class="dropbtn"><i class="fas fa-plus"></i> Add Item</button>

                                <div id="myDropdown" class="dropdown-content">
                                    <input type="text" id="search" placeholder="Search for an item...">
                                    <a href="#">Item 1</a>
                                    <a href="#">Item 2</a>

                                    <!-- Menu items will be loaded here via AJAX -->
                                </div>

                            </div>
                        </div>


                    </div>


                    <div class="order-items">
                        <table class="order-items-table">
                            <thead>
                                <tr>
                                    <th>ITEM</th>
                                    <th>PRICE</th>
                                    <th>QUANTITY</th>
                                    <th>TOTAL</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="orderItems">
                                <!-- Order items will be added here -->
                                <tr>
                                    <td colspan="5">
                                        <div style=" display: flex; justify-content: center; align-items: center; flex-direction: column; gap: 10px; color:#ddd">
    <i class="fas  fa-clipboard-list style" style=" font-size: 2rem;"></i>  </div>
                                       <h3 style="font-size: 1.2rem; color:#afb3af; text-align: center;"> No items added</h3>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="footer">
                        <button type="submit" class="submitBtn">Submit Order</button>
                    </div>

                </div>

                <!-- Current Order -->
                <div class="current-order">
                    <div class="summary">
                        <h2>Order Summary</h2>
                        <hr>
                    </div>
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>GT Levy (1%):</span>
                            <span id="tax">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>NHiL (2.5%):</span>
                            <span id="tax">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>GF Levy(2.5%):</span>
                            <span id="tax">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>VAT (12.5%):</span>
                            <span id="tax">₵0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="total">₵0.00</span>
                        </div>
                    </div>

                    <div class="payment-method">
                        <h3>Payment Method</h3>
                        <input type="radio" name="payment-method" id="cash" checked>
                        <label for="cash">Cash</label>
                        <input type="radio" name="payment-method" id="Momo">
                        <label for="MOMO">Momo</label>
                        <input type="radio" name="payment-method" id="bank">
                        <label for="bank">Bank</label>

                    </div>

                    <div class="footer">
                        <button type="button" class="PrintOrder"><i class="fas fa-print"></i> Print Order</button>
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
    <script src="../assets/js/new_order.js"></script>
    <script>
        const orderNumber = "<?= $orderNumber ?>";
    </script>
</body>

</html>
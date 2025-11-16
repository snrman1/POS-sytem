<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: /');
    exit;
}

// Date picker logic
$selectedDate = date('Y-m-d');
if (isset($_GET['date'])) {
    $selectedDate = $_GET['date'];
}

// Status filter logic
$statusFilter = 'all';
if (isset($_GET['status'])) {
    $statusFilter = $_GET['status'];
}

// Search logic
$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
}

// Fetch orders from database
$orders = [];
try {
    $whereConditions = ["DATE(o.created_at) = ?"];
    $params = [$selectedDate];
    
    if ($statusFilter !== 'all') {
        $whereConditions[] = "o.status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(o.order_number LIKE ? OR o.table_number LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as cashier_name, s.name as waiter_name
        FROM new_orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
        WHERE $whereClause
        ORDER BY o.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch items for each order
    foreach ($orders as &$order) {
        $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$order['id']]);
        $order['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order); // break reference
    
} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <link rel="stylesheet" href="../../assets/css/order.css">
</head>
<body>
<?php include '../../includes/header.php'; ?>
<div class="container">
    <div class="main">
    <div class="header">
    <h1><i class="fas fa-clipboard-list"></i> Orders for <span id="current-date"><?= date('F j, Y', strtotime($selectedDate)) ?></span></h1>
    </div>
    <div class="main-container">
        
        <div class="header">
            <p></p>
            <div>
                <button class="btn btn-primary" id="print-btn">
                    <i class="fas fa-print"></i> Print List
                </button>
                <button class="btn btn-success" id="export-btn">
                    <i class="fas fa-file-pdf"></i> Export to PDF
                </button>
            </div>
        </div>
        
        <form method="get" class="filters">
            <div class="filter-group">
                <label for="date-picker">Select Date</label>
                <input type="date" id="date-picker" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
            </div>
            
            <div class="filter-group">
                <label for="search-input">Search Orders</label>
                <input type="text" id="search-input" name="search" placeholder="Search by ID, table, or items" value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            
            <div class="filter-group">
                <label for="status-filter">Filter by Status</label>
                <select id="status-filter" name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="amended" <?= $statusFilter === 'amended' ? 'selected' : '' ?>>Amended</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-outline" style="width: 100%;">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ORDER ID</th>
                        <th>CASHIER</th>
                        <th>ITEMS</th>
                        <th>SERVICE TYPE</th>
                        <th>WAITER</th>
                        <th>SUBTOTAL</th>
                        <th>TAX</th>
                        <th>TOTAL</th>
                        <th>DATE & TIME</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="11" class="no-orders">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No orders found</h3>
                                <p>There are no orders for the selected date or filter criteria.</p>
                                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline">
                                    <i class="fas fa-sync-alt"></i> Reset Filters
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr data-order-id="<?= $order['id'] ?>">
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['cashier_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php 
                                        $itemsToShow = array_slice($order['items'], 0, 2);
                                        $remaining = count($order['items']) - 2;
                                        echo htmlspecialchars(implode(', ', array_column($itemsToShow, 'item_name')));
                                        if ($remaining > 0) echo ", +$remaining more";
                                    ?>
                                </td>
                                <td><?= ucfirst(str_replace('-', ' ', $order['order_type'])) ?></td>
                                <td><?= htmlspecialchars($order['waiter_name'] ?? 'N/A') ?></td>
                                <td>₵<?= number_format($order['subtotal'], 2) ?></td>
                                <td>₵<?= number_format($order['gt_levy'] + $order['nhil'] + $order['gf_levy'] + $order['vat'], 2) ?></td>
                                <td>₵<?= number_format($order['total'], 2) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= ucfirst($order['status']) ?></span></td>
                                <td>
                                    <button class="action-btn view-btn" data-order-id="<?= $order['id'] ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="pagination">
            <div class="pagination-info">
                Showing <?= count($orders) ?> orders
            </div>
        </div>
    </div>
    </div>
</div>

    
    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Details - <span id="modal-order-id"></span></h3>
                <button class="close-btn" id="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="order-details-grid">
                    <div class="detail-group">
                        <div class="detail-label">Cashier</div>
                        <div id="modal-cashier">-</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Service Type</div>
                        <div id="modal-service-type">-</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Waiter</div>
                        <div id="modal-waiter">-</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Date & Time</div>
                        <div id="modal-date-time">-</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Table Number</div>
                        <div id="modal-table">-</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div id="modal-status">-</div>
                    </div>
                </div>
                
                <h4>Order Items</h4>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="modal-items-body">
                        <!-- Items will be added here -->
                    </tbody>
                </table>
                
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="modal-subtotal">₵0.00</span>
                </div>
                <div class="summary-row">
                    <span>Tax:</span>
                    <span id="modal-tax">₵0.00</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total:</span>
                    <span id="modal-total">₵0.00</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="close-modal-btn">
                    Close
                </button>
                <button class="btn btn-primary" id="print-order-btn">
                    <i class="fas fa-print"></i> Print Order
                </button>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP orders data to JavaScript
        const orders = <?= json_encode($orders) ?>;
        
        // DOM elements
        const ordersTableBody = document.getElementById('orders-table-body');
        const currentDateElement = document.getElementById('current-date');
        const datePicker = document.getElementById('date-picker');
        const searchInput = document.getElementById('search-input');
        const statusFilter = document.getElementById('status-filter');
        const printBtn = document.getElementById('print-btn');
        const exportBtn = document.getElementById('export-btn');
        const modal = document.getElementById('orderDetailsModal');
        const closeModalBtn = document.getElementById('close-modal');
        const closeModalBtn2 = document.getElementById('close-modal-btn');
        const printOrderBtn = document.getElementById('print-order-btn');

        // Show order details in modal
        function showOrderDetails(orderId) {
            const order = orders.find(o => o.id == orderId);
            if (!order) return;
            
            document.getElementById('modal-order-id').textContent = order.order_number;
            document.getElementById('modal-cashier').textContent = order.cashier_name || 'N/A';
            document.getElementById('modal-service-type').textContent = order.order_type.replace('-', ' ');
            document.getElementById('modal-waiter').textContent = order.waiter_name || 'N/A';
            document.getElementById('modal-date-time').textContent = new Date(order.created_at).toLocaleString();
            document.getElementById('modal-table').textContent = order.table_number ? 'Table ' + order.table_number : 'N/A';
            
            // Set status with appropriate class
            const statusElement = document.getElementById('modal-status');
            statusElement.textContent = order.status.charAt(0).toUpperCase() + order.status.slice(1);
            statusElement.className = '';
            statusElement.classList.add('status-' + order.status);
            
            // Render items
            const itemsBody = document.getElementById('modal-items-body');
            itemsBody.innerHTML = '';
            
            if (order.items && order.items.length > 0) {
                order.items.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.item_name}</td>
                        <td>${item.quantity}</td>
                        <td>₵${parseFloat(item.price).toFixed(2)}</td>
                        <td>₵${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
                    `;
                    itemsBody.appendChild(row);
                });
            }
            
            // Set totals
            document.getElementById('modal-subtotal').textContent = `₵${parseFloat(order.subtotal).toFixed(2)}`;
            const totalTax = parseFloat(order.gt_levy) + parseFloat(order.nhil) + parseFloat(order.gf_levy) + parseFloat(order.vat);
            document.getElementById('modal-tax').textContent = `₵${totalTax.toFixed(2)}`;
            document.getElementById('modal-total').textContent = `₵${parseFloat(order.total).toFixed(2)}`;
            
            // Show modal
            modal.style.display = 'flex';
        }

        // Print order list
        function printOrderList() {
            const printWindow = window.open('', '_blank');
            let printContent = `
                <html>
                <head>
                    <title>Order Report - ${currentDateElement.textContent}</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        h1 { color: #2c3e50; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #2c3e50; color: white; padding: 8px; text-align: left; }
                        td { padding: 8px; border-bottom: 1px solid #ddd; }
                        .status-completed { background-color: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px; }
                        .status-cancelled { background-color: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 3px; }
                        .status-amended { background-color: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 3px; }
                        .status-active { background-color: #d1ecf1; color: #0c5460; padding: 3px 8px; border-radius: 3px; }
                        .footer { margin-top: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>Order Report - ${currentDateElement.textContent}</h1>
                    <div class="footer">Generated on ${new Date().toLocaleString()}</div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Cashier</th>
                                <th>Service Type</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            orders.forEach(order => {
                printContent += `
                    <tr>
                        <td>${order.order_number}</td>
                        <td>${order.cashier_name || 'N/A'}</td>
                        <td>${order.order_type.replace('-', ' ')}</td>
                        <td>₵${parseFloat(order.total).toFixed(2)}</td>
                        <td>
                            <span class="status-${order.status}">
                                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            printContent += `
                        </tbody>
                    </table>
                    <div class="footer">Total orders: ${orders.length}</div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        // Export to PDF (simplified version)
        function exportToPDF() {
            alert("PDF export would be implemented here. For now, printing instead.");
            printOrderList();
        }

        // Close modal
        function closeModal() {
            modal.style.display = 'none';
        }

        // Initialize
        function init() {
            // Event listeners
            printBtn.addEventListener('click', printOrderList);
            exportBtn.addEventListener('click', exportToPDF);
            
            closeModalBtn.addEventListener('click', closeModal);
            closeModalBtn2.addEventListener('click', closeModal);
            
            printOrderBtn.addEventListener('click', function() {
                alert("Printing this specific order would be implemented here");
            });
            
            // Add event listeners to view buttons
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    showOrderDetails(orderId);
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }

        // Start the application
        init();
    </script>
</body>
</html>
</html>
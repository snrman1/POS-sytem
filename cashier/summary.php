<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only allow cashier users to access POS
if ($_SESSION['user']['role'] !== 'cashier') {
    header('Location: /admin/');
    exit;
}

// Date picker logic - always start with current date
$selectedDate = date('Y-m-d');
// Only use GET date if it's explicitly provided and valid
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $inputDate = $_GET['date'];
    // Validate the date format
    if (DateTime::createFromFormat('Y-m-d', $inputDate) !== false) {
        $selectedDate = $inputDate;
    }
}

// Fetch summary data
$totalSales = 0;
$completedOrders = 0;
$cancelledOrders = 0;
$amendedOrders = 0;
$orderTypeCounts = ['dine-in' => 0, 'take-away' => 0];
$topItems = [];
$orderDetails = [];

try {
    // Fetch order summary
    $stmt = $pdo->prepare("SELECT status, order_type, SUM(total) as total, COUNT(*) as count FROM new_orders WHERE DATE(created_at) = ? GROUP BY status, order_type");
    $stmt->execute([$selectedDate]);
    $summaryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the raw data
    error_log("Summary data for date $selectedDate: " . json_encode($summaryRows));
    
    // Debug: Check what order types exist in the database
    $stmtTypes = $pdo->prepare("SELECT DISTINCT order_type FROM new_orders WHERE order_type IS NOT NULL");
    $stmtTypes->execute();
    $existingTypes = $stmtTypes->fetchAll(PDO::FETCH_COLUMN);
    error_log("Existing order types in database: " . json_encode($existingTypes));
    
    foreach ($summaryRows as $row) {
        $totalSales += (float)$row['total'];
        if ($row['status'] === 'completed') $completedOrders += $row['count'];
        if ($row['status'] === 'cancelled') $cancelledOrders += $row['count'];
        if ($row['status'] === 'amended') $amendedOrders += $row['count'];
        if (isset($orderTypeCounts[$row['order_type']])) $orderTypeCounts[$row['order_type']] += $row['count'];
    }
    $totalOrders = $completedOrders + $cancelledOrders + $amendedOrders;

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
} catch (PDOException $e) {
    $error = "Error fetching summary: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant POS - Daily Summary</title>
    <link rel="stylesheet" href="../assets/css/summary.css">
    <link rel="stylesheet" href="../assets/css/all.css">
    <script src="../assets/js/chart.umd.js"></script> <!-- Local Chart.js -->
    <script>
        // Fallback if Chart.js fails to load
        window.addEventListener('error', function(e) {
            if (e.target.src && e.target.src.includes('chart.umd.js')) {
                console.error('Chart.js failed to load');
                document.getElementById('orderTypeChart').style.display = 'none';
                const errorMsg = document.createElement('div');
                errorMsg.textContent = 'Chart could not be loaded. Please check your connection.';
                errorMsg.style.textAlign = 'center';
                errorMsg.style.padding = '20px';
                errorMsg.style.color = '#e74c3c';
                document.getElementById('orderTypeChart').parentNode.appendChild(errorMsg);
            }
        });
    </script>
</head>

<body>
    <?php include '../includes/cashier_header.php'; ?>

    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-chart-simple"></i> Daily Report</h1>
                <form method="get" style="display:inline-block; margin-left:20px;">
                    <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="main-container">
                <div>
                    <div class="summary-card">
                        <div class="summary-header">
                            <h2 class="summary-title">Daily Sales Summary</h2>
                            <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Summary</button>
                        </div>
                        <p style="margin-bottom: 15px;"><?= date('m/d/Y', strtotime($selectedDate)) ?></p>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value">₵<?= number_format($totalSales, 2) ?></div>
                                <div class="stat-label">Total Sales</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $completedOrders ?></div>
                                <div class="stat-label">Completed Orders</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $cancelledOrders ?></div>
                                <div class="stat-label">Cancelled Orders</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $amendedOrders ?></div>
                                <div class="stat-label">Amended Orders</div>
                            </div>
                        </div>
                        <div class="progress-container">
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span><?= $totalOrders ?> orders</span>
                                    <span><?= $totalOrders > 0 ? '100%' : '0%' ?> of total</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:100%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Completed</span>
                                    <span><?= $totalOrders > 0 ? round($completedOrders/$totalOrders*100,1) : 0 ?>% of total</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:<?= $totalOrders > 0 ? round($completedOrders/$totalOrders*100,1) : 0 ?>%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Cancelled</span>
                                    <span><?= $totalOrders > 0 ? round($cancelledOrders/$totalOrders*100,1) : 0 ?>% of total</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:<?= $totalOrders > 0 ? round($cancelledOrders/$totalOrders*100,1) : 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <h2 class="summary-title">Orders Breakdown</h2>
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <h3 style="margin-bottom: 15px; font-size: 16px;">Order Type Distribution</h3>
                                <div style="position: relative; height: 200px; width: 200px;">
                                    <canvas id="orderTypeChart" style="max-width: 100%; max-height: 100%;"></canvas>
                                </div>
                                <div class="chart-labels">
                                    <?php 
                                    $totalOrderTypes = $orderTypeCounts['dine-in'] + $orderTypeCounts['take-away'];
                                    $dineInRatio = $totalOrderTypes > 0 ? round($orderTypeCounts['dine-in'] / $totalOrderTypes * 100, 1) : 0;
                                    $takeawayRatio = $totalOrderTypes > 0 ? round($orderTypeCounts['take-away'] / $totalOrderTypes * 100, 1) : 0;
                                    ?>
                                    <div class="chart-label">
                                        <div class="chart-color" style="background: #3498db;"></div>
                                        <span>Dine-In (<?= $dineInRatio ?>%)</span>
                                    </div>
                                    <div class="chart-label">
                                        <div class="chart-color" style="background: #2ecc71;"></div>
                                        <span>Takeaway (<?= $takeawayRatio ?>%)</span>
                                    </div>
                                </div>
                                <!-- Debug info (remove in production) -->
                                
                            </div>
                            <div style="flex: 1; margin-left: 30px;">
                                <h3 style="margin-bottom: 15px; font-size: 16px;">Top Selling Items</h3>
                                <div class="top-items">
                                    <?php foreach ($topItems as $item): ?>
                                        <div class="top-item">
                                            <span><?= htmlspecialchars($item['item_name']) ?></span>
                                            <span><?= $item['qty'] ?> orders</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="summary-card">
                        <h2 class="summary-title">Order Details</h2>
                        <div class="filters">
                            <div>
                                <button class="filter-btn active">All Orders</button>
                            </div>
                        </div>
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>ORDER #</th>
                                    <th>TIME</th>
                                    <th>TABLE</th>
                                    <th>TYPE</th>
                                    <th>WAITER</th>
                                    <th>AMOUNT</th>
                                    <th>STATUS</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderDetails as $order): ?>
                                <tr data-order-id="<?= $order['id'] ?>">
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td><?= date('g:i A', strtotime($order['created_at'])) ?></td>
                                    <td><?= $order['table_number'] ? 'Table ' . htmlspecialchars($order['table_number']) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($order['order_type']) ?></td>
                                    <td><?= htmlspecialchars($order['waiter_name'] ?? 'N/A') ?></td>
                                    <td>₵<?= number_format($order['total'], 2) ?></td>
                                    <td class="status-<?= htmlspecialchars($order['status']) ?>"><?= ucfirst($order['status']) ?></td>
                                    <td><button class="view-btn" data-order-id="<?= $order['id'] ?>">View Details</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeOrderModal">&times;</span>
            <h2>Order Details</h2>
            <div id="modalOrderInfo"></div>
        </div>
    </div>
    <script>
    // Real-time update functionality
    let updateInterval;
    let lastUpdateTime = new Date();
    
    // Function to fetch updated summary data
    async function fetchUpdatedData() {
        try {
            // Always use current date for real-time updates unless a specific date is selected
            const currentDate = new Date().toISOString().split('T')[0];
            const selectedDate = '<?= $selectedDate ?>' === '<?= date('Y-m-d') ?>' ? currentDate : '<?= $selectedDate ?>';
            
            const response = await fetch(`get_summary_data.php?date=${encodeURIComponent(selectedDate)}&last_update=${lastUpdateTime.toISOString()}`);
            if (response.ok) {
                const data = await response.json();
                if (data.hasUpdates) {
                    updateSummaryDisplay(data);
                    lastUpdateTime = new Date();
                }
            }
        } catch (error) {
            console.error('Error fetching updated data:', error);
        }
    }
    
    // Function to update the summary display
    function updateSummaryDisplay(data) {
        // Update summary statistics
        document.querySelector('.stat-value:nth-child(1)').textContent = '₵' + parseFloat(data.totalSales).toFixed(2);
        document.querySelector('.stat-value:nth-child(2)').textContent = data.completedOrders;
        document.querySelector('.stat-value:nth-child(3)').textContent = data.cancelledOrders;
        document.querySelector('.stat-value:nth-child(4)').textContent = data.amendedOrders;
        
        // Update progress bars
        const totalOrders = data.completedOrders + data.cancelledOrders + data.amendedOrders;
        const completedPercent = totalOrders > 0 ? (data.completedOrders / totalOrders * 100).toFixed(1) : 0;
        const cancelledPercent = totalOrders > 0 ? (data.cancelledOrders / totalOrders * 100).toFixed(1) : 0;
        
        document.querySelectorAll('.progress-fill')[1].style.width = completedPercent + '%';
        document.querySelectorAll('.progress-fill')[2].style.width = cancelledPercent + '%';
        document.querySelectorAll('.progress-label span:last-child')[1].textContent = completedPercent + '% of total';
        document.querySelectorAll('.progress-label span:last-child')[2].textContent = cancelledPercent + '% of total';
        
        // Update order type chart if data changed
        if (data.orderTypeCounts) {
            updateOrderTypeChart(data.orderTypeCounts);
        }
        
        // Update order details table
        if (data.orderDetails) {
            updateOrderDetailsTable(data.orderDetails);
        }
        
        // Show update notification
        showUpdateNotification();
    }
    
    // Function to update the order type chart
    function updateOrderTypeChart(orderTypeCounts) {
        const dineInCount = orderTypeCounts['dine-in'] || 0;
        const takeawayCount = orderTypeCounts['take-away'] || 0;
        const totalOrders = dineInCount + takeawayCount;
        
        // Update chart labels
        const dineInRatio = totalOrders > 0 ? (dineInCount / totalOrders * 100).toFixed(1) : 0;
        const takeawayRatio = totalOrders > 0 ? (takeawayCount / totalOrders * 100).toFixed(1) : 0;
        
        document.querySelector('.chart-label:nth-child(1) span').textContent = `Dine-In (${dineInRatio}%)`;
        document.querySelector('.chart-label:nth-child(2) span').textContent = `Takeaway (${takeawayRatio}%)`;
        
        // Update chart data if chart exists
        if (window.orderTypeChart) {
            window.orderTypeChart.data.datasets[0].data = [dineInCount, takeawayCount];
            window.orderTypeChart.data.labels = [`Dine-In (${dineInRatio}%)`, `Takeaway (${takeawayRatio}%)`];
            window.orderTypeChart.update();
        }
    }
    
    // Function to update order details table
    function updateOrderDetailsTable(orderDetails) {
        const tbody = document.querySelector('.orders-table tbody');
        tbody.innerHTML = '';
        
        orderDetails.forEach(order => {
            const row = document.createElement('tr');
            row.setAttribute('data-order-id', order.id);
            row.innerHTML = `
                <td>${order.order_number}</td>
                <td>${formatTime(order.created_at)}</td>
                <td>${order.table_number ? 'Table ' + order.table_number : 'N/A'}</td>
                <td>${order.order_type}</td>
                <td>${order.waiter_name || 'N/A'}</td>
                <td>₵${parseFloat(order.total).toFixed(2)}</td>
                <td class="status-${order.status}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</td>
                <td><button class="view-btn" data-order-id="${order.id}">View Details</button></td>
            `;
            tbody.appendChild(row);
        });
        
        // Reattach event listeners to new view buttons
        attachViewButtonListeners();
    }
    
    // Function to format time
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
    }
    
    // Function to show update notification
    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'update-notification';
        notification.textContent = 'Data updated';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 2000);
    }
    
    // Function to attach event listeners to view buttons
    function attachViewButtonListeners() {
        document.querySelectorAll('.view-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                // Reuse existing order details logic
                const order = window.orderDetailsData.find(o => o.id == orderId);
                if (order) {
                    showOrderDetails(order);
                }
            });
        });
    }
    
    // Function to show order details (reuse existing logic)
    function showOrderDetails(order) {
        // This will reuse the existing modal logic
        const modal = document.getElementById('orderDetailsModal');
        const modalOrderInfo = document.getElementById('modalOrderInfo');
        
        // Format currency
        function formatCurrency(amount) {
            return '₵' + parseFloat(amount).toFixed(2);
        }
        
        // Format date
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
        
        // Get status badge class
        function getStatusBadgeClass(status) {
            switch(status.toLowerCase()) {
                case 'completed': return 'completed';
                case 'cancelled': return 'cancelled';
                case 'amended': return 'amended';
                default: return '';
            }
        }
        
        let html = `
            <div style="margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <strong>Order #:</strong> ${order.order_number}<br>
                        <strong>Date & Time:</strong> ${formatDateTime(order.created_at)}<br>
                        <strong>Order Type:</strong> ${order.order_type}<br>
                        <strong>Status:</strong> <span class="status-badge ${getStatusBadgeClass(order.status)}">${order.status}</span>
                    </div>
                    <div>
                        <strong>Table:</strong> ${order.table_number ? 'Table ' + order.table_number : 'N/A'}<br>
                        <strong>Waiter:</strong> ${order.waiter_name || 'N/A'}<br>
                        <strong>Cashier:</strong> ${order.cashier_name || 'N/A'}<br>
                        <strong>Total Amount:</strong> ${formatCurrency(order.total)}
                    </div>
                </div>
            </div>
        `;
        
        if (order.items && order.items.length > 0) {
            html += `
                <div style="margin-top: 20px;">
                    <strong>Order Items:</strong>
                    <table class="order-details-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let totalItems = 0;
            order.items.forEach(function(item) {
                const subtotal = parseFloat(item.price) * parseInt(item.quantity);
                totalItems += parseInt(item.quantity);
                html += `
                    <tr>
                        <td>${item.item_name}</td>
                        <td>${item.quantity}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(subtotal)}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                    <div style="margin-top: 10px; text-align: right; font-weight: bold;">
                        Total Items: ${totalItems} | Total Amount: ${formatCurrency(order.total)}
                    </div>
                </div>
            `;
        } else {
            html += '<div style="margin-top: 20px; color: #666; font-style: italic;">No items found for this order.</div>';
        }
        
        modalOrderInfo.innerHTML = html;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    // Pie chart for order type distribution
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing chart...');
        console.log('Chart object available:', typeof Chart !== 'undefined');
        
        // Store order details data globally for reuse
        window.orderDetailsData = <?= json_encode($orderDetails) ?>;
        
        // Wait a bit to ensure Chart.js is fully loaded
        setTimeout(function() {
            // Check if Chart is available
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }
            
            const ctx = document.getElementById('orderTypeChart');
            if (!ctx) {
                console.error('Canvas element not found');
                return;
            }
            
            const chartContext = ctx.getContext('2d');
            if (!chartContext) {
                console.error('Could not get 2D context');
                return;
            }
            
            // Chart data from PHP
            const dineInCount = <?= $orderTypeCounts['dine-in'] ?>;
            const takeawayCount = <?= $orderTypeCounts['take-away'] ?>;
            const totalOrders = dineInCount + takeawayCount;
            
            console.log('Chart data:', { dineInCount, takeawayCount, totalOrders });
            
            // Calculate ratios/percentages
            const dineInRatio = totalOrders > 0 ? (dineInCount / totalOrders * 100).toFixed(1) : 0;
            const takeawayRatio = totalOrders > 0 ? (takeawayCount / totalOrders * 100).toFixed(1) : 0;
            
            // Only create chart if there's data
            if (dineInCount > 0 || takeawayCount > 0) {
                window.orderTypeChart = new Chart(chartContext, {
                    type: 'pie',
                    data: {
                        labels: [`Dine-In (${dineInRatio}%)`, `Takeaway (${takeawayRatio}%)`],
                        datasets: [{
                            data: [dineInCount, takeawayCount],
                            backgroundColor: ['#3498db', '#2ecc71'],
                            borderColor: ['#2980b9', '#27ae60'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const percentage = totalOrders > 0 ? ((value / totalOrders) * 100).toFixed(1) : 0;
                                        return `${label.split(' (')[0]}: ${value} orders (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
                
                console.log('Chart created successfully');
            } else {
                // Show message when no data but still create chart with zero values
                window.orderTypeChart = new Chart(chartContext, {
                    type: 'pie',
                    data: {
                        labels: ['Dine-In (0%)', 'Takeaway (0%)'],
                        datasets: [{
                            data: [0, 0],
                            backgroundColor: ['#3498db', '#2ecc71'],
                            borderColor: ['#2980b9', '#27ae60'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.label.split(' (')[0]}: 0 orders (0%)`;
                                    }
                                }
                            }
                        }
                    }
                });
                
                // Add no data message
                const noDataMsg = document.createElement('div');
                noDataMsg.textContent = 'No order data available for this date';
                noDataMsg.style.textAlign = 'center';
                noDataMsg.style.padding = '20px';
                noDataMsg.style.color = '#666';
                ctx.parentNode.appendChild(noDataMsg);
            }
        }, 100); // Wait 100ms for Chart.js to load

        // Order details modal logic
        const modal = document.getElementById('orderDetailsModal');
        const modalOrderInfo = document.getElementById('modalOrderInfo');
        
        // Function to format currency
        function formatCurrency(amount) {
            return '₵' + parseFloat(amount).toFixed(2);
        }
        
        // Function to format date
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
        
        // Function to get status badge class
        function getStatusBadgeClass(status) {
            switch(status.toLowerCase()) {
                case 'completed': return 'completed';
                case 'cancelled': return 'cancelled';
                case 'amended': return 'amended';
                default: return '';
            }
        }
        
        // Function to show modal
        function showModal() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        // Function to hide modal
        function hideModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
        
        // Add click event listeners to view buttons
        document.querySelectorAll('.view-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const order = window.orderDetailsData.find(o => o.id == orderId);
                
                if (order) {
                    let html = `
                        <div style="margin-bottom: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <strong>Order #:</strong> ${order.order_number}<br>
                                    <strong>Date & Time:</strong> ${formatDateTime(order.created_at)}<br>
                                    <strong>Order Type:</strong> ${order.order_type}<br>
                                    <strong>Status:</strong> <span class="status-badge ${getStatusBadgeClass(order.status)}">${order.status}</span>
                                </div>
                                <div>
                                    <strong>Table:</strong> ${order.table_number ? 'Table ' + order.table_number : 'N/A'}<br>
                                    <strong>Waiter:</strong> ${order.waiter_name || 'N/A'}<br>
                                    <strong>Cashier:</strong> ${order.cashier_name || 'N/A'}<br>
                                    <strong>Total Amount:</strong> ${formatCurrency(order.total)}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Add items table if there are items
                    if (order.items && order.items.length > 0) {
                        html += `
                            <div style="margin-top: 20px;">
                                <strong>Order Items:</strong>
                                <table class="order-details-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        let totalItems = 0;
                        order.items.forEach(function(item) {
                            const subtotal = parseFloat(item.price) * parseInt(item.quantity);
                            totalItems += parseInt(item.quantity);
                            html += `
                                <tr>
                                    <td>${item.item_name}</td>
                                    <td>${item.quantity}</td>
                                    <td>${formatCurrency(item.price)}</td>
                                    <td>${formatCurrency(subtotal)}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                                <div style="margin-top: 10px; text-align: right; font-weight: bold;">
                                    Total Items: ${totalItems} | Total Amount: ${formatCurrency(order.total)}
                                </div>
                            </div>
                        `;
                    } else {
                        html += '<div style="margin-top: 20px; color: #666; font-style: italic;">No items found for this order.</div>';
                    }
                    
                    modalOrderInfo.innerHTML = html;
                    showModal();
                } else {
                    console.error('Order not found:', orderId);
                    alert('Order details not found. Please try again.');
                }
            });
        });
        
        // Close modal when clicking the X button
        document.getElementById('closeOrderModal').addEventListener('click', hideModal);
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                hideModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'block') {
                hideModal();
            }
        });
        
        // Start real-time updates
        updateInterval = setInterval(fetchUpdatedData, 30000); // Update every 30 seconds
        
        // Add CSS for update notification animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    });
    
    // Clean up interval when page is unloaded
    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
    </script>
</body>

</html>
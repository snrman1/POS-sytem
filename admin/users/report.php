<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: /');
    exit;
}

// Report filters
$reportType = $_GET['report_type'] ?? 'weekly';
$staffFilter = $_GET['staff_filter'] ?? 'all';
$categoryFilter = $_GET['category_filter'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch report data
try {
    // Summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total) as total_sales,
            AVG(total) as avg_order,
            SUM(total) * 0.35 as net_profit -- Assuming 35% profit margin
        FROM new_orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $summaryStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Previous period for comparison
    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    $prevStartDate = date('Y-m-d', strtotime($startDate) - ($daysDiff + 1) * 24 * 60 * 60);
    $prevEndDate = date('Y-m-d', strtotime($startDate) - 24 * 60 * 60);
    
    $stmt->execute([$prevStartDate, $prevEndDate]);
    $prevStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate percentage changes
    $salesChange = $prevStats['total_sales'] > 0 ? (($summaryStats['total_sales'] - $prevStats['total_sales']) / $prevStats['total_sales']) * 100 : 0;
    $ordersChange = $prevStats['total_orders'] > 0 ? (($summaryStats['total_orders'] - $prevStats['total_orders']) / $prevStats['total_orders']) * 100 : 0;
    $avgChange = $prevStats['avg_order'] > 0 ? (($summaryStats['avg_order'] - $prevStats['avg_order']) / $prevStats['avg_order']) * 100 : 0;
    $profitChange = $prevStats['total_sales'] > 0 ? (($summaryStats['net_profit'] - $prevStats['net_profit']) / $prevStats['net_profit']) * 100 : 0;
    
    // Top selling items
    $stmt = $pdo->prepare("
        SELECT 
            oi.item_name,
            mi.category,
            SUM(oi.quantity) as qty_sold,
            SUM(oi.total) as revenue
        FROM order_items oi
        JOIN new_orders o ON oi.order_id = o.id
        LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        " . ($categoryFilter !== 'all' ? "AND mi.category = ?" : "") . "
        GROUP BY oi.item_name, mi.category
        ORDER BY qty_sold DESC
        LIMIT 10
    ");
    
    if ($categoryFilter !== 'all') {
        $stmt->execute([$startDate, $endDate, $categoryFilter]);
    } else {
        $stmt->execute([$startDate, $endDate]);
    }
    $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Staff performance
    $stmt = $pdo->prepare("
        SELECT 
            u.username as staff_name,
            COUNT(o.id) as orders_handled,
            SUM(o.total) as total_sales,
            AVG(o.total) as avg_order
        FROM new_orders o
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        " . ($staffFilter !== 'all' ? "AND u.username = ?" : "") . "
        GROUP BY u.id, u.username
        ORDER BY total_sales DESC
    ");
    
    if ($staffFilter !== 'all') {
        $stmt->execute([$startDate, $endDate, $staffFilter]);
    } else {
        $stmt->execute([$startDate, $endDate]);
    }
    $staffPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sales by category
    $stmt = $pdo->prepare("
        SELECT 
            mi.category,
            SUM(oi.quantity) as qty_sold,
            SUM(oi.total) as revenue
        FROM order_items oi
        JOIN new_orders o ON oi.order_id = o.id
        LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND mi.category IS NOT NULL
        GROUP BY mi.category
        ORDER BY revenue DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily sales data for chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(total) as daily_sales,
            COUNT(*) as daily_orders
        FROM new_orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$startDate, $endDate]);
    $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Detailed sales
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            u.username as cashier_name,
            COUNT(oi.id) as item_count
        FROM new_orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$startDate, $endDate]);
    $detailedSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available staff for filter
    $stmt = $pdo->prepare("SELECT DISTINCT username FROM users WHERE role = 'user' ORDER BY username");
    $stmt->execute();
    $availableStaff = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get available categories for filter
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM menu_items WHERE category IS NOT NULL ORDER BY category");
    $stmt->execute();
    $availableCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Error fetching report data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant POS - Reports</title>
    <link rel="stylesheet" href="../../assets/css/report.css">
    <script src="../../assets/js/chart.umd.js"></script>
</head>

<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container">
        <div class="main">
        <div class="header">
                    <h1><i class="fa-solid fa-book"></i> Reports</h1>
                </div>
            <div class="main-container">
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <!-- Report Filters -->
                <form method="get" class="report-filters">
                    <div class="filter-group">
                        <select name="report_type" onchange="this.form.submit()">
                            <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="custom" <?= $reportType === 'custom' ? 'selected' : '' ?>>Custom</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="staff_filter" onchange="this.form.submit()">
                            <option value="all" <?= $staffFilter === 'all' ? 'selected' : '' ?>>All Staff</option>
                            <?php foreach ($availableStaff as $staff): ?>
                                <option value="<?= htmlspecialchars($staff) ?>" <?= $staffFilter === $staff ? 'selected' : '' ?>><?= htmlspecialchars($staff) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="category_filter" onchange="this.form.submit()">
                            <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                            <?php foreach ($availableCategories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <div class="date-range">
                            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                            <span>to</span>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                    </div>

                    <button type="submit" class="apply-btn">Apply</button>
                </form>

                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <h3>Total Sales</h3>
                        <div class="value">₵<?= number_format($summaryStats['total_sales'] ?? 0, 2) ?></div>
                        <div class="period"><?= $reportType ?> period</div>
                        <div class="change <?= $salesChange >= 0 ? 'up' : 'down' ?>">
                            <?= $salesChange >= 0 ? '↑' : '↓' ?> <?= abs(round($salesChange, 1)) ?>%
                        </div>
                    </div>

                    <div class="summary-card">
                        <h3>Total Orders</h3>
                        <div class="value"><?= $summaryStats['total_orders'] ?? 0 ?></div>
                        <div class="period"><?= $reportType ?> period</div>
                        <div class="change <?= $ordersChange >= 0 ? 'up' : 'down' ?>">
                            <?= $ordersChange >= 0 ? '↑' : '↓' ?> <?= abs(round($ordersChange, 1)) ?>%
                        </div>
                    </div>

                    <div class="summary-card">
                        <h3>Average Order Value</h3>
                        <div class="value">₵<?= number_format($summaryStats['avg_order'] ?? 0, 2) ?></div>
                        <div class="period">per order</div>
                        <div class="change <?= $avgChange >= 0 ? 'up' : 'down' ?>">
                            <?= $avgChange >= 0 ? '↑' : '↓' ?> <?= abs(round($avgChange, 1)) ?>%
                        </div>
                    </div>

                    <div class="summary-card">
                        <h3>Net Profit</h3>
                        <div class="value">₵<?= number_format($summaryStats['net_profit'] ?? 0, 2) ?></div>
                        <div class="period"><?= $reportType ?> period</div>
                        <div class="change <?= $profitChange >= 0 ? 'up' : 'down' ?>">
                            <?= $profitChange >= 0 ? '↑' : '↓' ?> <?= abs(round($profitChange, 1)) ?>%
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-section">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h2>Sales Overview</h2>
                            <div class="chart-tabs">
                                <div class="chart-tab active" data-chart="sales">Sales</div>
                                <div class="chart-tab" data-chart="orders">Orders</div>
                            </div>
                        </div>
                        <canvas id="salesChart" width="400" height="200"></canvas>
                    </div>

                    <div class="chart-container">
                        <div class="chart-header">
                            <h2>Sales by Category</h2>
                        </div>
                        <canvas id="categoryChart" width="300" height="300"></canvas>
                    </div>
                </div>

                <!-- Tables Section -->
                <div class="tables-section">
                    <div class="table-container">
                        <div class="table-header">
                            <h2>Top Selling Items</h2>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>ITEM</th>
                                    <th>CATEGORY</th>
                                    <th>QTY SOLD</th>
                                    <th>REVENUE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topItems)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No items sold in this period</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topItems as $item): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                            <td><?= $item['qty_sold'] ?></td>
                                            <td>₵<?= number_format($item['revenue'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-container">
                        <div class="table-header">
                            <h2>Staff Performance</h2>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>STAFF</th>
                                    <th>ORDERS</th>
                                    <th>SALES</th>
                                    <th>AVG. ORDER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staffPerformance)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No staff performance data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($staffPerformance as $staff): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($staff['staff_name']) ?></strong></td>
                                            <td><?= $staff['orders_handled'] ?></td>
                                            <td>₵<?= number_format($staff['total_sales'], 2) ?></td>
                                            <td>₵<?= number_format($staff['avg_order'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Detailed Sales Table -->
                <div class="detailed-sales">
                    <div class="table-header">
                        <h2>Detailed Sales</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ORDER ID</th>
                                <th>DATE & TIME</th>
                                <th>CASHIER</th>
                                <th>ITEMS</th>
                                <th>SERVICE TYPE</th>
                                <th>SUBTOTAL</th>
                                <th>TAX</th>
                                <th>TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($detailedSales)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No sales data for this period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($detailedSales as $sale): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sale['order_number']) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($sale['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></td>
                                        <td><?= $sale['item_count'] ?> items</td>
                                        <td><?= ucfirst(str_replace('-', ' ', $sale['order_type'])) ?></td>
                                        <td>₵<?= number_format($sale['subtotal'], 2) ?></td>
                                        <td>₵<?= number_format($sale['gt_levy'] + $sale['nhil'] + $sale['gf_levy'] + $sale['vat'], 2) ?></td>
                                        <td>₵<?= number_format($sale['total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Export Options -->
                <div class="export-options">
                    <div class="export-option">
                        <input type="checkbox" id="export-excel">
                        <label for="export-excel">Export as Excel</label>
                    </div>
                    <div class="export-option">
                        <input type="checkbox" id="export-pdf">
                        <label for="export-pdf">Export as PDF</label>
                    </div>
                    <div class="export-option">
                        <input type="checkbox" id="export-csv" checked>
                        <label for="export-csv">Export as CSV</label>
                    </div>
                    <div class="export-option">
                        <input type="checkbox" id="export-print">
                        <label for="export-print">Print Report</label>
                    </div>
                    <button class="export-btn" onclick="exportReport()">Export</button>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const dailySalesData = <?= json_encode($dailySales) ?>;
        const categorySalesData = <?= json_encode($categorySales) ?>;
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: dailySalesData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Daily Sales (₵)',
                        data: dailySalesData.map(item => parseFloat(item.daily_sales) || 0),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Daily Orders',
                        data: dailySalesData.map(item => parseInt(item.daily_orders) || 0),
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Sales (₵)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Orders'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
            
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categorySalesData.map(item => item.category),
                    datasets: [{
                        data: categorySalesData.map(item => parseFloat(item.revenue) || 0),
                        backgroundColor: [
                            '#3498db', '#2ecc71', '#e74c3c', '#f39c12', 
                            '#9b59b6', '#1abc9c', '#34495e', '#e67e22'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Chart tab functionality
            document.querySelectorAll('.chart-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    this.parentElement.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Handle chart switching
                    const chartType = this.getAttribute('data-chart');
                    if (chartType === 'orders') {
                        // Show only orders data
                        salesChart.data.datasets[0].hidden = true;
                        salesChart.data.datasets[1].hidden = false;
                    } else {
                        // Show both sales and orders
                        salesChart.data.datasets[0].hidden = false;
                        salesChart.data.datasets[1].hidden = false;
                    }
                    salesChart.update();
                });
            });
        });
        
        // Export functionality
        function exportReport() {
            const exportExcel = document.getElementById('export-excel').checked;
            const exportPdf = document.getElementById('export-pdf').checked;
            const exportCsv = document.getElementById('export-csv').checked;
            const exportPrint = document.getElementById('export-print').checked;
            
            if (exportPrint) {
                window.print();
            }
            
            if (exportExcel || exportPdf || exportCsv) {
                alert('Export functionality would be implemented here. For now, printing instead.');
                window.print();
            }
        }
        
        // Auto-refresh reports every 5 minutes
        setInterval(function() {
            console.log('Auto-refreshing reports...');
            // In a real implementation, you would refresh the data
        }, 300000);
    </script>
</body>

</html>
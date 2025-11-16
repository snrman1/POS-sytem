<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: /');
    exit;
}

// Fetch dashboard data
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

try {
    // Today's sales data
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total) as total_sales,
            AVG(total) as avg_order
        FROM new_orders 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $todayData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Yesterday's sales data for comparison
    $stmt->execute([$yesterday]);
    $yesterdayData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate changes
    $totalSales = $todayData['total_sales'] ?? 0;
    $totalOrders = $todayData['total_orders'] ?? 0;
    $avgOrder = $todayData['avg_order'] ?? 0;
    
    $yesterdaySales = $yesterdayData['total_sales'] ?? 0;
    $yesterdayOrders = $yesterdayData['total_orders'] ?? 0;
    $yesterdayAvg = $yesterdayData['avg_order'] ?? 0;
    
    $salesChange = $yesterdaySales > 0 ? (($totalSales - $yesterdaySales) / $yesterdaySales) * 100 : 0;
    $ordersChange = $yesterdayOrders > 0 ? (($totalOrders - $yesterdayOrders) / $yesterdayOrders) * 100 : 0;
    $avgChange = $yesterdayAvg > 0 ? (($avgOrder - $yesterdayAvg) / $yesterdayAvg) * 100 : 0;
    
    // Fetch recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as cashier_name, s.name as waiter_name
        FROM new_orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
        WHERE DATE(o.created_at) = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch active staff
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_staff,
        SUM(CASE WHEN role = 'waiter' THEN 1 ELSE 0 END) as waiters,
        SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashiers,
        SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as managers
        FROM staff 
        WHERE is_active = 1
    ");
    $stmt->execute();
    $staffData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch recent activity log
    $stmt = $pdo->prepare("
        SELECT al.*, u.username 
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch sales data for chart (last 7 days)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, SUM(total) as daily_sales, COUNT(*) as daily_orders
        FROM new_orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute();
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant POS - Admin Panel</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="../../assets/js/chart.umd.js"></script>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-gauge"></i> Dashboard</h1>
            </div>
            <div class="main-container">

                <!-- Dashboard Cards -->
                <div class="dashboard-cards">
                    <div class="card">
                        <h3>Total Sales Today</h3>
                        <div class="value">₵<?= number_format($totalSales, 2) ?></div>
                        <div class="change <?= $salesChange >= 0 ? 'up' : 'down' ?>">
                            <?= $salesChange >= 0 ? '↑' : '↓' ?> <?= abs(round($salesChange, 1)) ?>%
                        </div>
                        <div class="comparison">Compared to ₵<?= number_format($yesterdaySales, 2) ?> yesterday</div>
                    </div>

                    <div class="card">
                        <h3>Total Orders Today</h3>
                        <div class="value"><?= $totalOrders ?></div>
                        <div class="change <?= $ordersChange >= 0 ? 'up' : 'down' ?>">
                            <?= $ordersChange >= 0 ? '↑' : '↓' ?> <?= abs(round($ordersChange, 1)) ?>%
                        </div>
                        <div class="comparison">Compared to <?= $yesterdayOrders ?> yesterday</div>
                    </div>

                    <div class="card">
                        <h3>Average Order Today</h3>
                        <div class="value">₵<?= number_format($avgOrder, 2) ?></div>
                        <div class="change <?= $avgChange >= 0 ? 'up' : 'down' ?>">
                            <?= $avgChange >= 0 ? '↑' : '↓' ?> <?= abs(round($avgChange, 1)) ?>%
                        </div>
                        <div class="comparison">Compared to ₵<?= number_format($yesterdayAvg, 2) ?> yesterday</div>
                    </div>

                    <div class="card">
                        <h3>Active Staff Now</h3>
                        <div class="value"><?= $staffData['total_staff'] ?? 0 ?></div>
                        <div class="change up">↑ <?= $staffData['total_staff'] ?? 0 ?></div>
                        <div class="comparison">
                            <?= $staffData['waiters'] ?? 0 ?> waiters, 
                            <?= $staffData['cashiers'] ?? 0 ?> cashiers, 
                            <?= $staffData['managers'] ?? 0 ?> manager
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" data-period="today">Today</div>
                    <div class="tab" data-period="week">This Week</div>
                    <div class="tab" data-period="month">This Month</div>
                    <div class="tab" data-period="custom">Custom</div>
                </div>

                <!-- Main Sections -->
                <div class="main-sections">
                    <!-- Left Column -->
                    <div>
                        <!-- Sales Overview -->
                        <div class="section">
                            <div class="section-header">
                                <h2>Sales Overview</h2>
                                <div class="tabs">
                                    <div class="tab active" data-chart="daily">Daily</div>
                                    <div class="tab" data-chart="weekly">Weekly</div>
                                    <div class="tab" data-chart="monthly">Monthly</div>
                                </div>
                            </div>

                            <div class="sales-chart">
                                <canvas id="salesChart" width="400" height="200"></canvas>
                            </div>

                            <div class="tabs">
                                <div class="tab active" data-compare="today">Today</div>
                                <div class="tab" data-compare="yesterday">Yesterday</div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="section" style="margin-top: 20px;">
                            <div class="section-header">
                                <h2>Recent Orders</h2>
                                <a href="../users/order.php">View All</a>
                            </div>

                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>ORDER ID</th>
                                        <th>CUSTOMER</th>
                                        <th>AMOUNT</th>
                                        <th>STATUS</th>
                                        <th>TIME</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 20px;">
                                                No orders today
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                                <td><?= $order['table_number'] ? 'Table ' . htmlspecialchars($order['table_number']) : 'Takeaway' ?></td>
                                                <td>₵<?= number_format($order['total'], 2) ?></td>
                                                <td><span class="status-badge <?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                                                <td><?= date('g:i A', strtotime($order['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div>
                        <!-- Staff Overview -->
                        <div class="section">
                            <div class="section-header">
                                <h2>Staff Overview</h2>
                                <a href="../users/staff.php">View All</a>
                            </div>

                            <div class="staff-list">
                                <?php
                                // Fetch active staff members
                                $stmt = $pdo->prepare("SELECT name, role FROM staff WHERE is_active = 1 ORDER BY role, name LIMIT 4");
                                $stmt->execute();
                                $activeStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                
                                <?php foreach ($activeStaff as $staff): ?>
                                    <div class="staff-card">
                                        <div class="staff-avatar"><?= strtoupper(substr($staff['name'], 0, 2)) ?></div>
                                        <div class="staff-info">
                                            <h4><?= htmlspecialchars($staff['name']) ?></h4>
                                            <p><span class="status online"></span> <?= ucfirst($staff['role']) ?> - Active</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($activeStaff)): ?>
                                    <div class="staff-card">
                                        <div class="staff-avatar">--</div>
                                        <div class="staff-info">
                                            <h4>No Active Staff</h4>
                                            <p>No staff members currently active</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button onclick="window.location.href='../users/staff.php'" style="margin-top: 15px; width: 100%; padding: 10px; background-color: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Manage Staff
                            </button>
                        </div>

                        <!-- Activity Log -->
                        <div class="section" style="margin-top: 20px;">
                            <div class="section-header">
                                <h2>Activity Log</h2>
                                <a href="#">View All</a>
                            </div>

                            <ul class="activity-log">
                                <?php if (empty($activityLog)): ?>
                                    <li class="activity-item">
                                        No recent activity
                                        <div class="activity-time">--</div>
                                    </li>
                                <?php else: ?>
                                    <?php foreach ($activityLog as $activity): ?>
                                        <li class="activity-item">
                                            <?= htmlspecialchars($activity['action']) ?>
                                            <?= $activity['username'] ? ' by ' . htmlspecialchars($activity['username']) : '' ?>
                                            <div class="activity-time"><?= date('g:i A', strtotime($activity['created_at'])) ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart data from PHP
        const chartData = <?= json_encode($chartData) ?>;
        
        // Initialize sales chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Prepare chart data
            const labels = chartData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            
            const salesData = chartData.map(item => parseFloat(item.daily_sales) || 0);
            const ordersData = chartData.map(item => parseInt(item.daily_orders) || 0);
            
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Sales (₵)',
                        data: salesData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Daily Orders',
                        data: ordersData,
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
            
            // Tab functionality
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs in this group
                    const tabGroup = this.parentElement;
                    tabGroup.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Handle different tab types
                    if (this.hasAttribute('data-period')) {
                        handlePeriodTab(this.getAttribute('data-period'));
                    } else if (this.hasAttribute('data-chart')) {
                        handleChartTab(this.getAttribute('data-chart'));
                    } else if (this.hasAttribute('data-compare')) {
                        handleCompareTab(this.getAttribute('data-compare'));
                    }
                });
            });
            
            function handlePeriodTab(period) {
                // Handle period tabs (Today, This Week, etc.)
                console.log('Period changed to:', period);
                // In a real implementation, you would fetch new data based on the period
            }
            
            function handleChartTab(chartType) {
                // Handle chart type tabs (Daily, Weekly, Monthly)
                console.log('Chart type changed to:', chartType);
                // In a real implementation, you would update the chart data
            }
            
            function handleCompareTab(compareType) {
                // Handle comparison tabs (Today, Yesterday)
                console.log('Comparison changed to:', compareType);
                // In a real implementation, you would update the comparison data
            }
            
            // Auto-refresh dashboard every 5 minutes
            setInterval(function() {
                // In a real implementation, you would fetch updated data
                console.log('Dashboard auto-refresh');
            }, 300000); // 5 minutes
        });
    </script>
</body>

</html>
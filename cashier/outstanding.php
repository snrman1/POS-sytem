<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Only allow cashier users to access POS
if ($_SESSION['user']['role'] !== 'cashier') {
    header('Location: /admin/');
    exit;
}

// Set default date to today
$selectedDate = date('Y-m-d');
if (isset($_GET['date'])) {
    $selectedDate = $_GET['date'];
}

// Fetch outstanding orders for the selected date
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as cashier_name, s.name as waiter_name
        FROM new_orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        LEFT JOIN staff s ON o.waiter_id = s.id AND s.role = 'waiter'
        WHERE o.status = 'active'
        AND DATE(o.created_at) = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$selectedDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch items for each order
    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("
            SELECT * FROM order_items 
            WHERE order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
}

// Handle bill order action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bill_order'])) {
    $orderId = (int)$_POST['order_id'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE new_orders 
            SET status = 'completed', updated_at = NOW()
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$orderId]);
        
        $_SESSION['success_message'] = "Order #$orderId has been marked as completed!";
        header("Location: outstanding.php?date=$selectedDate");
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
    <title>Outstanding Orders</title>
    <link rel="stylesheet" href="../assets/css/outstanding.css">
    <link rel="stylesheet" href="../assets/css/all.css">
    <!-- Include jsPDF -->
</head>
<body>
    <?php include '../includes/cashier_header.php'; ?>
    <div class="container">
        <div class="main">
            <div class="headerr header">
                <h1><i class="fas fa-clock"></i> Outstanding Orders</h1>
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
                <div class="header">
                    <p></p>
                    <div>
                        <button class="btn btn-warning" id="print-btn">
                            <i class="fas fa-print"></i> Print List
                        </button>
                        <button class="btn btn-danger" id="export-btn">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </button>
                    </div>
                </div>

                <div class="filters">
                    <div class="filter-group">
                        <label for="date-picker">Select Date</label>
                        <input type="date" id="date-picker" value="<?= htmlspecialchars($selectedDate) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="search-input">Search Orders</label>
                        <input type="text" id="search-input" placeholder="Search by ID, time, or items">
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-outline" id="reset-filters" style="width: 100%;">
                            <i class="fas fa-sync-alt"></i> Reset Filters
                        </button>
                    </div>
                </div>

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
                                        <h3>No outstanding orders found</h3>
                                        <p>There are no unbilled orders for the selected date or filter criteria.</p>
                                        <button class="btn btn-outline" id="reset-filters-inline">
                                            <i class="fas fa-sync-alt"></i> Reset Filters
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr data-id="<?= $order['id'] ?>">
                                        <td><?= htmlspecialchars($order['order_number']) ?></td>
                                        <td><?= htmlspecialchars($order['cashier_name']) ?></td>
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
                                        <td><span class="status-badge status-outstanding">Outstanding</span></td>
                                        <td>
                                            <button class="action-btn view-btn" data-id="<?= $order['id'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="action-btn bill-btn" data-id="<?= $order['id'] ?>">
                                                <i class="fas fa-receipt"></i> Bill
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
                            <span>GT Levy (1%):</span>
                            <span id="modal-gt-levy">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>NHIL (2.5%):</span>
                            <span id="modal-nhil">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>GF Levy (2.5%):</span>
                            <span id="modal-gf-levy">₵0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>VAT (12.5%):</span>
                            <span id="modal-vat">₵0.00</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total:</span>
                            <span id="modal-total">₵0.00</span>
                        </div>

                        <div class="detail-group" style="margin-top: 20px;">
                            <div class="detail-label">Payment Method</div>
                            <div id="modal-payment-method">-</div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Notes</div>
                            <div id="modal-notes">No special notes</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline" id="close-modal-btn">
                            Close
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" id="modal-order-id-input">
                            <input type="hidden" name="bill_order" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-receipt"></i> Bill Order
                            </button>
                        </form>
                        <button class="btn btn-warning" id="print-order-btn">
                            <i class="fas fa-print"></i> Print Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // DOM elements
        const ordersTableBody = document.getElementById('orders-table-body');
        const datePicker = document.getElementById('date-picker');
        const searchInput = document.getElementById('search-input');
        const resetFiltersBtn = document.getElementById('reset-filters');
        const resetFiltersInlineBtn = document.getElementById('reset-filters-inline');
        const printBtn = document.getElementById('print-btn');
        const exportBtn = document.getElementById('export-btn');
        const modal = document.getElementById('orderDetailsModal');
        const closeModalBtn = document.getElementById('close-modal');
        const closeModalBtn2 = document.getElementById('close-modal-btn');
        const printOrderBtn = document.getElementById('print-order-btn');
        const billOrderBtn = document.querySelector('.modal-footer form button[type="submit"]');
        const modalOrderIdInput = document.getElementById('modal-order-id-input');

        // Change date filter
        datePicker.addEventListener('change', function() {
            window.location.href = `outstanding.php?date=${this.value}`;
        });

        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = ordersTableBody.querySelectorAll('tr[data-id]');
            
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(searchTerm) ? '' : 'none';
            });
        });

        // Reset filters
        function resetFilters() {
            const today = new Date().toISOString().split('T')[0];
            window.location.href = `outstanding.php?date=${today}`;
        }
        resetFiltersBtn.addEventListener('click', resetFilters);
        if (resetFiltersInlineBtn) {
            resetFiltersInlineBtn.addEventListener('click', resetFilters);
        }

        // View order details
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-id');
                const orderRow = document.querySelector(`tr[data-id="${orderId}"]`);
                
                if (orderRow) {
                    // Populate modal with order data
                    document.getElementById('modal-order-id').textContent = 
                        orderRow.cells[0].textContent;
                    document.getElementById('modal-cashier').textContent = 
                        orderRow.cells[1].textContent;
                    document.getElementById('modal-service-type').textContent = 
                        orderRow.cells[3].textContent;
                    document.getElementById('modal-waiter').textContent = 
                        orderRow.cells[4].textContent;
                    document.getElementById('modal-subtotal').textContent = 
                        orderRow.cells[5].textContent;
                    document.getElementById('modal-total').textContent = 
                        orderRow.cells[7].textContent;
                    document.getElementById('modal-date-time').textContent = 
                        orderRow.cells[8].textContent;
                    document.getElementById('modal-status').textContent = 
                        orderRow.querySelector('.status-badge').textContent;
                    
                    // Set the order ID in the form
                    modalOrderIdInput.value = orderId;
                    
                    // Show modal
                    modal.style.display = 'flex';
                }
            });
        });

        // Print order list
        printBtn.addEventListener('click', function() {
            const printWindow = window.open('', '_blank');
            let printContent = `
                <html>
                <head>
                    <title>Outstanding Orders Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        h1 { color: #2c3e50; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #2c3e50; color: white; padding: 8px; text-align: left; }
                        td { padding: 8px; border-bottom: 1px solid #ddd; }
                        .status-outstanding { background-color: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 3px; }
                        .footer { margin-top: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>Outstanding Orders Report</h1>
                    <div>Date: ${datePicker.value}</div>
                    <div class="footer">Generated on ${new Date().toLocaleString()}</div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Cashier</th>
                                <th>Service Type</th>
                                <th>Total</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            document.querySelectorAll('#orders-table-body tr[data-id]').forEach(row => {
                printContent += `
                    <tr>
                        <td>${row.cells[0].textContent}</td>
                        <td>${row.cells[1].textContent}</td>
                        <td>${row.cells[3].textContent}</td>
                        <td>${row.cells[7].textContent}</td>
                        <td>${row.cells[8].textContent}</td>
                        <td>
                            <span class="status-outstanding">
                                ${row.cells[9].textContent}
                            </span>
                        </td>
                    </tr>
                `;
            });

            printContent += `
                        </tbody>
                    </table>
                    <div class="footer">Total outstanding orders: ${document.querySelectorAll('#orders-table-body tr[data-id]').length}</div>
                </body>
                </html>
            `;

            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        });

        // Export to PDF using jsPDF
        exportBtn.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Title
            doc.setFontSize(18);
            doc.text('Outstanding Orders Report', 105, 15, { align: 'center' });
            
            // Date and generated info
            doc.setFontSize(10);
            doc.text(`Date: ${datePicker.value}`, 14, 25);
            doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, 30);
            
            // Table data
            const headers = [
                ['Order ID', 'Cashier', 'Service Type', 'Total', 'Date & Time', 'Status']
            ];
            
            const rows = [];
            document.querySelectorAll('#orders-table-body tr[data-id]').forEach(row => {
                rows.push([
                    row.cells[0].textContent,
                    row.cells[1].textContent,
                    row.cells[3].textContent,
                    row.cells[7].textContent,
                    row.cells[8].textContent,
                    row.cells[9].textContent.trim()
                ]);
            });
            
            // Add table
            doc.autoTable({
                head: headers,
                body: rows,
                startY: 35,
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    valign: 'middle'
                },
                headStyles: {
                    fillColor: [44, 62, 80],
                    textColor: 255,
                    fontStyle: 'bold'
                },
                columnStyles: {
                    0: { cellWidth: 25 },
                    1: { cellWidth: 25 },
                    2: { cellWidth: 25 },
                    3: { cellWidth: 20 },
                    4: { cellWidth: 30 },
                    5: { cellWidth: 20 }
                }
            });
            
            // Footer
            const finalY = doc.lastAutoTable.finalY;
            doc.setFontSize(10);
            doc.text(`Total outstanding orders: ${rows.length}`, 14, finalY + 10);
            
            // Save the PDF
            doc.save(`outstanding_orders_${datePicker.value}.pdf`);
        });

        // Print individual order
        printOrderBtn.addEventListener('click', function() {
            const printWindow = window.open('', '_blank');
            const orderId = document.getElementById('modal-order-id').textContent;
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Order Details - ${orderId}</title>
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
                            <div><strong>Order #:</strong> ${orderId}</div>
                            <div><strong>Date:</strong> ${document.getElementById('modal-date-time').textContent}</div>
                            <div><strong>Type:</strong> ${document.getElementById('modal-service-type').textContent}</div>
                            <div><strong>Table:</strong> ${document.getElementById('modal-table').textContent}</div>
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
                                ${document.getElementById('modal-items-body').innerHTML}
                            </tbody>
                        </table>
                        
                        <div class="totals">
                            <div>Subtotal: ${document.getElementById('modal-subtotal').textContent}</div>
                            <div>GT Levy (1%): ${document.getElementById('modal-gt-levy').textContent}</div>
                            <div>NHIL (2.5%): ${document.getElementById('modal-nhil').textContent}</div>
                            <div>GF Levy (2.5%): ${document.getElementById('modal-gf-levy').textContent}</div>
                            <div>VAT (12.5%): ${document.getElementById('modal-vat').textContent}</div>
                            <div><strong>Total: ${document.getElementById('modal-total').textContent}</strong></div>
                        </div>
                        
                        <div class="payment-method">
                            <div><strong>Payment Method:</strong> ${document.getElementById('modal-payment-method').textContent}</div>
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
            }, 500);
        });

        // Close modal
        function closeModal() {
            modal.style.display = 'none';
        }
        closeModalBtn.addEventListener('click', closeModal);
        closeModalBtn2.addEventListener('click', closeModal);
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>
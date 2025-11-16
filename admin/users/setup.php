<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: /');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_tax':
                $taxName = $_POST['tax_name'];
                $taxRate = $_POST['tax_rate'];
                $description = $_POST['description'];

                try {
                    $stmt = $pdo->prepare("INSERT INTO tax_rates (name, rate, description) VALUES (?, ?, ?)");
                    $stmt->execute([$taxName, $taxRate, $description]);
                    $_SESSION['success_message'] = "Tax rate added successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Error adding tax rate: " . $e->getMessage();
                }
                break;

            case 'update_tax':
                $taxId = $_POST['tax_id'];
                $taxName = $_POST['tax_name'];
                $taxRate = $_POST['tax_rate'];
                $description = $_POST['description'];

                try {
                    $stmt = $pdo->prepare("UPDATE tax_rates SET name = ?, rate = ?, description = ? WHERE id = ?");
                    $stmt->execute([$taxName, $taxRate, $description, $taxId]);
                    $_SESSION['success_message'] = "Tax rate updated successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Error updating tax rate: " . $e->getMessage();
                }
                break;

            case 'delete_tax':
                $taxId = $_POST['tax_id'];

                try {
                    $stmt = $pdo->prepare("DELETE FROM tax_rates WHERE id = ?");
                    $stmt->execute([$taxId]);
                    $_SESSION['success_message'] = "Tax rate deleted successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Error deleting tax rate: " . $e->getMessage();
                }
                break;

            case 'save_settings':
                $businessName = $_POST['business_name'];
                $phoneNumber = $_POST['phone_number'];
                $businessAddress = $_POST['business_address'];
                $footerMessage = $_POST['footer_message'];
                $receiptSize = $_POST['receipt_size'];
                $receiptTemplate = $_POST['receipt_template'];
                $autoPrint = $_POST['auto_print'];
                $defaultPrinter = $_POST['default_printer'];

                try {
                    // Save to settings table or update existing
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");

                    $settings = [
                        'business_name' => $businessName,
                        'phone_number' => $phoneNumber,
                        'business_address' => $businessAddress,
                        'footer_message' => $footerMessage,
                        'receipt_size' => $receiptSize,
                        'receipt_template' => $receiptTemplate,
                        'auto_print' => $autoPrint,
                        'default_printer' => $defaultPrinter
                    ];

                    foreach ($settings as $key => $value) {
                        $stmt->execute([$key, $value]);
                    }

                    $_SESSION['success_message'] = "Settings saved successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Error saving settings: " . $e->getMessage();
                }
                break;
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Create tables if they don't exist
try {
    // Create tax_rates table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tax_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            rate DECIMAL(5,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Create system_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Create printers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS printers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            size VARCHAR(20),
            status ENUM('online', 'offline') DEFAULT 'offline',
            is_default BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
} catch (PDOException $e) {
    $error = "Error creating tables: " . $e->getMessage();
}

// Fetch data
try {
    // Fetch tax rates
    $stmt = $pdo->prepare("SELECT * FROM tax_rates ORDER BY name");
    $stmt->execute();
    $taxRates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch system settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Fetch printers
    $stmt = $pdo->prepare("SELECT * FROM printers ORDER BY is_default DESC, name");
    $stmt->execute();
    $printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant POS - User Management</title>
    <link rel="stylesheet" href="../../assets/css/setup.css">
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="main">
            <div class="main-content">
                <div class="header">
                    <h1><i class="fa-solid fa-screwdriver-wrench"></i> Settings</h1>
                </div>

                <div class="main-container">

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <?= $_SESSION['success_message'] ?>
                            <?php unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-error">
                            <?= $_SESSION['error_message'] ?>
                            <?php unset($_SESSION['error_message']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Settings Tabs -->
                    <div class="settings-tabs">
                        <div class="settings-tab active" data-tab="tax">Tax Configuration</div>
                        <div class="settings-tab" data-tab="printer">Printer Setup</div>
                        <div class="settings-tab" data-tab="receipt">Receipt Configuration</div>
                        <div class="settings-tab" data-tab="system">System Settings</div>
                    </div>

                    <!-- Tax Configuration Section -->
                    <div class="settings-section active" id="tax-section">
                        <div class="section-header">
                            <h2>Tax Configuration</h2>
                            <button onclick="showAddTaxModal()">
                            <i class="fas fa-plus"></i>
                            Add Tax
                            </button>
                        </div>

                        <table class="tax-table">
                            <thead>
                                <tr>
                                    <th>Tax Name</th>
                                    <th>Tax Rate</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($taxRates)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No tax rates configured</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($taxRates as $tax): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tax['name']) ?></td>
                                            <td><?= $tax['rate'] ?>%</td>
                                            <td><?= htmlspecialchars($tax['description']) ?></td>
                                            <td>
                                                <div class="tax-actions">
                                                    <button class="edit-btn" onclick="editTax(<?= $tax['id'] ?>, '<?= htmlspecialchars($tax['name']) ?>', '<?= $tax['rate'] ?>', '<?= htmlspecialchars($tax['description']) ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="delete-btn" onclick="deleteTax(<?= $tax['id'] ?>)">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Printer Configuration Section -->
                    <div class="settings-section" id="printer-section">
                        <div class="section-header">
                            <h2>Printer Configuration</h2>
                            <button onclick="showAddPrinterModal()">
                            <i class="fas fa-plus"></i>
                            Add Printer
                            </button>
                        </div>

                        <div class="form-group">
                            <label>Default Receipt Printer</label>
                            <select name="default_printer" id="default-printer">
                                <option value="">Select Default Printer</option>
                                <?php foreach ($printers as $printer): ?>
                                    <option value="<?= $printer['id'] ?>" <?= $printer['is_default'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($printer['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Auto-Print After Order</label>
                            <select name="auto_print" id="auto-print">
                                <option value="enabled" <?= ($settings['auto_print'] ?? '') === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                                <option value="disabled" <?= ($settings['auto_print'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>

                        <h3 style="margin: 20px 0 15px; font-size: 1rem;">Connected Printers</h3>

                        <div class="printer-list">
                            <?php if (empty($printers)): ?>
                                <div class="printer-card">
                                    <div class="printer-info">
                                        <h4>No Printers Configured</h4>
                                        <p>Add printers to get started</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($printers as $printer): ?>
                                    <div class="printer-card">
                                        <div class="printer-info">
                                            <h4><?= htmlspecialchars($printer['name']) ?></h4>
                                            <p>
                                                <span class="printer-status <?= $printer['status'] ?>"></span>
                                                <?= htmlspecialchars($printer['type']) ?> - <?= htmlspecialchars($printer['size']) ?>
                                                <?= $printer['is_default'] ? ' (Default)' : '' ?>
                                            </p>
                                        </div>
                                        <button class="edit-btn" onclick="editPrinter(<?= $printer['id'] ?>)">
                                            <i>⚙️</i> Configure
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Receipt Configuration Section -->
                    <div class="settings-section" id="receipt-section">
                        <div class="section-header">
                            <h2>Receipt Configuration</h2>
                        </div>

                        <form id="receipt-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Receipt Size</label>
                                    <select name="receipt_size" id="receipt-size" onchange="updateReceiptPreview()">
                                        <option value="80mm" <?= ($settings['receipt_size'] ?? '') === '80mm' ? 'selected' : '' ?>>Thermal (80mm)</option>
                                        <option value="58mm" <?= ($settings['receipt_size'] ?? '') === '58mm' ? 'selected' : '' ?>>Thermal (58mm)</option>
                                        <option value="A4" <?= ($settings['receipt_size'] ?? '') === 'A4' ? 'selected' : '' ?>>A4 Paper</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Receipt Template</label>
                                    <select name="receipt_template" id="receipt-template" onchange="updateReceiptPreview()">
                                        <option value="standard" <?= ($settings['receipt_template'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard</option>
                                        <option value="minimal" <?= ($settings['receipt_template'] ?? '') === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                                        <option value="detailed" <?= ($settings['receipt_template'] ?? '') === 'detailed' ? 'selected' : '' ?>>Detailed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Business Information</label>
                                <div class="form-row">
                                    <div class="form-group">
                                        <input type="text" name="business_name" id="business-name" placeholder="Business Name"
                                            value="<?= htmlspecialchars($settings['business_name'] ?? 'Delicious Restaurant') ?>"
                                            onchange="updateReceiptPreview()">
                                    </div>
                                    <div class="form-group">
                                        <input type="text" name="phone_number" id="phone-number" placeholder="Phone Number"
                                            value="<?= htmlspecialchars($settings['phone_number'] ?? '(555) 123-4567') ?>"
                                            onchange="updateReceiptPreview()">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 15px;">
                                    <textarea name="business_address" id="business-address" placeholder="Business Address"
                                        onchange="updateReceiptPreview()"><?= htmlspecialchars($settings['business_address'] ?? '123 Main Street, Foodville, NY 10001') ?></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Receipt Footer Message</label>
                                <textarea name="footer_message" id="footer-message" placeholder="Thank you for dining with us!"
                                    onchange="updateReceiptPreview()"><?= htmlspecialchars($settings['footer_message'] ?? 'Thank you for your visit! Please come again.') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Receipt Preview</label>
                                <div class="receipt-preview" id="receipt-preview">
                                    <!-- Receipt preview will be updated by JavaScript -->
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- System Settings Section -->
                    <div class="settings-section" id="system-section">
                        <div class="section-header">
                            <h2>System Settings</h2>
                        </div>

                        <div class="form-group">
                            <label>System Language</label>
                            <select>
                                <option>English</option>
                                <option>Spanish</option>
                                <option>French</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Currency</label>
                            <select>
                                <option>GHS (₵)</option>
                                <option>USD ($)</option>
                                <option>EUR (€)</option>
                                <option>GBP (£)</option>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>Time Zone</label>
                            <select>
                                <option>UTC</option>
                                <option>EST</option>
                                <option>PST</option>
                                <option>GMT</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Backup Settings</label>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Auto Backup</label>
                                    <select>
                                        <option>Daily</option>
                                        <option>Weekly</option>
                                        <option>Monthly</option>
                                        <option>Disabled</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Backup Location</label>
                                    <input type="text" value="/backups" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="save-btn" onclick="saveAllSettings()">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tax Modal -->
    <div id="taxModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="taxModalTitle">Add Tax Rate</h3>
                <span class="close" onclick="closeTaxModal()">&times;</span>
            </div>
            <form id="taxForm" method="post">
                <input type="hidden" name="action" id="taxAction" value="save_tax">
                <input type="hidden" name="tax_id" id="taxId">

                <div class="form-group">
                    <label>Tax Name</label>
                    <input type="text" name="tax_name" id="taxName" required>
                </div>

                <div class="form-group">
                    <label>Tax Rate (%)</label>
                    <input type="number" name="tax_rate" id="taxRate" step="0.01" min="0" max="100" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="taxDescription"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="closeTaxModal()">Cancel</button>
                    <button type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and sections
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Show corresponding section
                const sectionId = this.getAttribute('data-tab') + '-section';
                document.getElementById(sectionId).classList.add('active');
            });
        });

        // Tax modal functions

        function editTax(id, name, rate, description) {
            document.getElementById('taxModalTitle').textContent = 'Edit Tax Rate';
            document.getElementById('taxAction').value = 'update_tax';
            document.getElementById('taxId').value = id;
            document.getElementById('taxName').value = name;
            document.getElementById('taxRate').value = rate;
            document.getElementById('taxDescription').value = description;
            document.getElementById('taxModal').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function deleteTax(id) {
            if (confirm('Are you sure you want to delete this tax rate?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_tax">
                    <input type="hidden" name="tax_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeTaxModal() {
            document.getElementById('taxModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Printer functions
        function showAddPrinterModal() {
            alert('Add printer functionality would be implemented here');
        }

        function editPrinter(id) {
            alert('Edit printer functionality would be implemented here');
        }

        // Receipt preview functions
        function updateReceiptPreview() {
            const businessName = document.getElementById('business-name').value || 'Delicious Restaurant';
            const phoneNumber = document.getElementById('phone-number').value || '(555) 123-4567';
            const businessAddress = document.getElementById('business-address').value || '123 Main Street, Foodville, NY 10001';
            const footerMessage = document.getElementById('footer-message').value || 'Thank you for your visit! Please come again.';
            const template = document.getElementById('receipt-template').value;

            let preview = `
                <div class="receipt-header">
                    <h3>${businessName}</h3>
                    <p>${businessAddress}</p>
                    <p>${phoneNumber}</p>
                    <p>--------------------------------</p>
                    <p>Order #12345 - ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                </div>

                <div class="receipt-items">
                    <div class="receipt-item">
                        <span>1x Margherita Pizza</span>
                        <span>₵12.99</span>
                    </div>
                    <div class="receipt-item">
                        <span>2x Iced Tea</span>
                        <span>₵5.98</span>
                    </div>
                    <div class="receipt-item">
                        <span>1x Garlic Bread</span>
                        <span>₵4.99</span>
                    </div>
                </div>

                <div class="receipt-totals">
                    <div class="receipt-total-row">
                        <span>Subtotal:</span>
                        <span>₵23.96</span>
                    </div>
                    <div class="receipt-total-row">
                        <span>Tax (8%):</span>
                        <span>₵1.92</span>
                    </div>
                    <div class="receipt-total-row" style="font-weight: bold;">
                        <span>Total:</span>
                        <span>₵25.88</span>
                    </div>
                </div>

                <div class="receipt-footer">
                    <p>${footerMessage}</p>
                </div>
            `;

            document.getElementById('receipt-preview').innerHTML = preview;
        }

        // Save all settings
        function saveAllSettings() {
            const formData = new FormData();
            formData.append('action', 'save_settings');

            // Get all form values
            const receiptForm = document.getElementById('receipt-form');
            const formElements = receiptForm.elements;
            for (let element of formElements) {
                if (element.name) {
                    formData.append(element.name, element.value);
                }
            }

            // Add other settings
            formData.append('auto_print', document.getElementById('auto-print').value);
            formData.append('default_printer', document.getElementById('default-printer').value);

            // Submit form
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(() => {
                window.location.reload();
            });
        }

        // Initialize receipt preview
        document.addEventListener('DOMContentLoaded', function() {
            updateReceiptPreview();
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('taxModal');
            if (event.target === modal) {
                closeTaxModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('taxModal');
                if (modal.style.display === 'block') {
                    closeTaxModal();
                }
            }
        });

        // Focus management for modal
        function showAddTaxModal() {
            document.getElementById('taxModalTitle').textContent = 'Add Tax Rate';
            document.getElementById('taxAction').value = 'save_tax';
            document.getElementById('taxId').value = '';
            document.getElementById('taxForm').reset();
            document.getElementById('taxModal').style.display = 'block';
            document.body.style.overflow = 'hidden';

            // Focus on first input after modal opens
            setTimeout(() => {
                document.getElementById('taxName').focus();
            }, 100);
        }
    </script>
</body>

</html>
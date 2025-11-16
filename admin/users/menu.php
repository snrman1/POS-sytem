<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Only admins can access this page
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: /');
    exit;
}

// Handle form submission for adding new menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_menu_item'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $active = isset($_POST['active']) ? 1 : 0;

    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../assets/images/menu/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = '../../assets/images/menu/' . $filename; // relative path for HTML
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO menu_items 
            (name, description, price, category, active, created_at, updated_at, image) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
        ");
        $stmt->execute([$name, $description, $price, $category, $active, $imagePath]);

        $_SESSION['success_message'] = "Menu item added successfully!";
        header('Location: menu.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error adding menu item: " . $e->getMessage();
    }
}

// Handle menu item deletion
if (isset($_GET['delete'])) {
    $itemId = (int)$_GET['delete'];

    try {
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$itemId]);

        $_SESSION['success_message'] = "Menu item deleted successfully!";
        header('Location: menu.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting menu item: " . $e->getMessage();
    }
}

// Get all menu items
try {
    $stmt = $pdo->query("
        SELECT * FROM menu_items 
        ORDER BY active DESC, category ASC, name ASC
    ");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count items by category for the filter tabs
    $itemCounts = [
        'all' => count($menuItems),
        'food' => 0,
        'drinks' => 0
    ];

    foreach ($menuItems as $item) {
        if (strtolower($item['category']) === 'food') $itemCounts['food']++;
        if (strtolower($item['category']) === 'drinks') $itemCounts['drinks']++;
    }
} catch (PDOException $e) {
    $menuItems = [];
    $_SESSION['error_message'] = "Error loading menu items: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - POS System</title>
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="../../assets/css/all.css">
</head>

<body>

    <?php include '../../includes/header.php'; ?>
    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-utensils"></i> Menu Management</h1>
            </div>
            <div class="main-container">


                <div class="page-description">Manage your restaurant's menu items, categories, and pricing</div>

                <!-- Menu Controls -->
                <div class="menu-controls">
                    <div class="search-box">
                    <i class="fas fa-search"></i> 
                        <input type="text" placeholder="Search menu items...">
                    </div>

                    <div class="filter-group">
                        <!-- Categories Filter -->
                        <div class="category-filters">
                            <div class="category-filter active">All Items (<?= $itemCounts['all'] ?>)</div>
                            <div class="category-filter">Food (<?= $itemCounts['food'] ?>)</div>
                            <div class="category-filter">Drinks (<?= $itemCounts['drinks'] ?>)</div>
                        </div>

                        <select>
                            <option>All Status</option>
                            <option>Available</option>
                            <option>Unavailable</option>
                        </select>

                        <select>
                            <option>Sort: A-Z</option>
                            <option>Sort: Z-A</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                        </select>

                        <button>
                        <i class="fas fa-plus"></i> Add Menu Item
                        </button>
                    </div>
                </div>

                <!-- Categories Filter -->
                <div class="category-filters">
                    <div class="category-filter active">All Items </div>
                    <div class="category-filter">Food </div>
                    <div class="category-filter">Drinks </div>
                </div>

                <!-- Menu Items Table -->
                <table class="menu-table">
                    <thead>
                        <tr>
                            <th>ITEM</th>
                            <th>CATEGORY</th>
                            <th>PRICE</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($menuItems)): ?>
                            <tr>
                                <td colspan="5" class="empty-table-message">
                                    No menu items found. Click "Add Menu Item" to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($menuItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="menu-item-info">
                                            <div class="menu-item-image">
                                                <?php if (!empty($item['image'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="Menu Image" style="max-width:100px;">
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="menu-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                                <div class="menu-item-description"><?= htmlspecialchars($item['description']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="menu-item-category category-<?= strtolower($item['category']) ?>">
                                            <?= htmlspecialchars($item['category']) ?>
                                        </span>
                                    </td>
                                    <td class="menu-item-price">₵<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <span class="menu-item-status status-<?= $item['active'] ? 'available' : 'unavailable' ?>">
                                            <?= $item['active'] ? 'Available' : 'Unavailable' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="menu-item-actions">
                                            <a href="edit_menu_item.php?id=<?= $item['id'] ?>" class="action-btn edit-btn">
                                            <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="menu.php?delete=<?= $item['id'] ?>" class="action-btn delete-btn"
                                                onclick="return confirm('Are you sure you want to delete this menu item?');">
                                                <i class="fas fa-trash-alt"></i> Delete
                                                
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <div>Showing 1 to 6 of 42 items</div>
                    <div class="pagination-controls">
                        <button class="pagination-btn">Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">4</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>

                <!-- Empty State (hidden by default) -->
                <div class="empty-state" style="display: none;">
                    <i>🍽️</i>
                    <h3>No Menu Items Found</h3>
                    <p>Try adjusting your search or filters to find what you're looking for.</p>
                    <button class="action-btn" style="background-color: var(--primary); color: white; padding: 8px 20px;">
                        <i>➕</i> Add New Menu Item
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Menu Item Modal -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add New Menu Item</h2>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <form method="POST" action="menu.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Item Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Food">Food</option>
                        <option value="Drinks">Drinks</option>
                    </select>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="active" name="active" checked>
                    <label for="active">Active (available for ordering)</label>
                </div>

                <div class="form-group">
                    <label for="image">Menu Image</label>
                    <input type="file" name="image" id="image" accept="image/*">
                </div>

                <input type="hidden" name="add_menu_item" value="1">
                <button type="submit" class="submit-btn">Add Menu Item</button>
            </form>
        </div>
    </div>
    <script src="../../assets/js/menu.js"></script>
</body>

</html>
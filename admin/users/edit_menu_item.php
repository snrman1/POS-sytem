<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Only admins can access this page
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: /pos/index.php');
    exit;
}

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$menuItem = null;
$error = '';
$success = '';

// Get menu item data
if ($itemId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$menuItem) {
            $error = "Menu item not found.";
        }
    } catch (PDOException $e) {
        $error = "Error loading menu item: " . $e->getMessage();
    }
} else {
    $error = "Invalid menu item ID.";
}

// Handle form submission for updating menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_menu_item'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $category = $_POST['category'];
    $active = isset($_POST['active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $error = "Item name is required.";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0.";
    } elseif (empty($category)) {
        $error = "Category is required.";
    } else {
        try {
            $imagePath = $menuItem['image']; // Keep existing image by default
            
            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../assets/images/menu/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $filename = uniqid() . '_' . basename($_FILES['image']['name']);
                $targetFile = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = '../../assets/images/menu/' . $filename;
                    
                    // Delete old image if it exists and is different
                    if (!empty($menuItem['image']) && $menuItem['image'] !== $imagePath) {
                        $oldImagePath = '../../' . ltrim($menuItem['image'], './');
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
            
            if (empty($error)) {
                $stmt = $pdo->prepare("
                    UPDATE menu_items 
                    SET name = ?, description = ?, price = ?, category = ?, active = ?, image = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $price, $category, $active, $imagePath, $itemId]);
                
                $success = "Menu item updated successfully!";
                
                // Refresh menu item data
                $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
                $stmt->execute([$itemId]);
                $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Error updating menu item: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Item - POS System</title>
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="../../assets/css/all.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .current-image {
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
        }
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="main">
            <div class="header">
                <h1><i class="fa-solid fa-edit"></i> Edit Menu Item</h1>
            </div>
            <div class="main-container">
                <div class="edit-container">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($menuItem): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="name">Item Name *</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($menuItem['name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description"><?= htmlspecialchars($menuItem['description']) ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" value="<?= $menuItem['price'] ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Food" <?= $menuItem['category'] === 'Food' ? 'selected' : '' ?>>Food</option>
                                    <option value="Drinks" <?= $menuItem['category'] === 'Drinks' ? 'selected' : '' ?>>Drinks</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <input type="checkbox" id="active" name="active" <?= $menuItem['active'] ? 'checked' : '' ?>>
                                <label for="active">Active (available for ordering)</label>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Menu Image</label>
                                <?php if (!empty($menuItem['image'])): ?>
                                    <div class="current-image">
                                        <strong>Current Image:</strong><br>
                                        <img src="<?= htmlspecialchars($menuItem['image']) ?>" alt="Current Menu Image">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="image" id="image" accept="image/*">
                                <small>Leave empty to keep the current image</small>
                            </div>
                            
                            <input type="hidden" name="update_menu_item" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Menu Item
                            </button>
                            <a href="menu.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Menu
                            </a>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error">
                            Menu item not found or invalid ID.
                            <a href="menu.php" class="btn btn-secondary" style="margin-left: 10px;">
                                <i class="fas fa-arrow-left"></i> Back to Menu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 
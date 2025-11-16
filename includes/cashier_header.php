<?php
// Check if user is logged in and is a cashier
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'cashier') {
    header('Location: /pos/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Cashier</title>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/all.css">
</head>
<body>
<header class="main-header">
    <div class="header-left">
        <div class="logo">
            <a href="../cashier/index.php">
                <span class="logo-icon">POS</span>
                <span class="logo-text">MEGAMIND</span>
            </a>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li>
                    <a href="../cashier/index.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/cashier/index') !== false ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i> New Order
                    </a>
                </li>
                <li>
                    <a href="../cashier/billing.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/cashier/billing') !== false ? 'active' : '' ?>">
                        <i class="fas fa-receipt"></i> Bill Out Orders
                    </a>
                </li>
                <li>
                    <a href="../cashier/modify.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/cashier/modify') !== false ? 'active' : '' ?>">
                        <i class="fas fa-edit"></i> Modify Order
                    </a>
                </li>
               
                <li>
                    <a href="../cashier/outstanding.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/cashier/outstanding') !== false ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i> Outstanding Orders
                    </a>
                </li>
                <li>
                    <a href="../cashier/summary.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/cashier/summary') !== false ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> Daily Summary
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    
    <div class="header-right">
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                <span class="user-role">Cashier</span>
            </div>
            <div class="user-actions">
                <a href="/pos/logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</header>
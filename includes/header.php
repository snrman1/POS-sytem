<?php
// Check if user is logged in and is admin
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'admin') {
    header('Location: /pos/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Admin</title>
    <link rel="stylesheet" href="../../assets/css/header.css">
    <link rel="stylesheet" href="../../assets/css/all.css">
</head>

<body>
    <header class="main-header">
        <div class="header-left">
            <div class="logo">
                <a href="../users/dashboard.php">
                    <span class="logo-icon">POS</span>
                    <span class="logo-text">MEGAMIND</span>
                </a>
            </div>

            <nav class="main-nav">
                <ul>
                    <li>
                        <a href="../users/dashboard.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/dashboard') !== false ? 'active' : '' ?>">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="../users/staff.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/staff') !== false ? 'active' : '' ?>">
                            <i class="fas fa-users"></i> User
                        </a>
                    </li>

                    <li>
                        <a href="../users/menu.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/menu') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-bars"></i>Menu
                        </a>
                    </li>
                    <li>
                        <a href="../users/order.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/order') !== false ? 'active' : '' ?>">
                            <i class="fas fa-boxes"></i> Order
                        </a>
                    </li>
                    <li>
                        <a href="../users/report.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/report') !== false ? 'active' : '' ?>">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li>
                        <a href="../users/setup.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/setup') !== false ? 'active' : '' ?>">
                            <i class="fas fa-cog"></i> Setup
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
                    <span class="user-role">Administrator</span>
                </div>
                <div class="user-actions">
                    <a href="/pos/logout.php" class="logout-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>
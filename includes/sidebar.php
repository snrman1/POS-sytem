<div class="sidebar">
    <div class="sidebar-header">
        <h3>POS Admin</h3>
    </div>
    <ul class="sidebar-nav">
        <li class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <a href="../admin/"><i class="icon-dashboard"></i> Dashboard</a>
        </li>
        <li class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users/') !== false ? 'active' : '' ?>">
            <a href="../../admin/users/users.php"><i class="icon-users"></i> User Management</a>
        </li>
        <li>
            <a href="../admin/products/"><i class="icon-products"></i> Products</a>
        </li>
        <li>
            <a href="../admin/reports/"><i class="icon-reports"></i> Reports</a>
        </li>
        <li>
            <a href="/admin/products"><i class="icon-settings"></i> Settings</a>
        </li>
    </ul>
</div>
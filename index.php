<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

//session_start();
redirectIfAuthenticated();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// Rate limit: track failed attempts (optional, simple version)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if ($_SESSION['login_attempts'] > 5) {
    $error = 'Too many failed attempts. Please try again later.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (!authenticateUser($username, $password)) {
            $error = 'Invalid username or password.';
            $_SESSION['login_attempts'] += 1;
        } else {
            // Successful login
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true); // Prevent session fixation

            if ($_SESSION['user']['role'] === 'admin') {
                header('Location: admin/users/dashboard.php');
            }
            else if($_SESSION['user']['role'] === 'cashier') {
                header('Location: cashier/index.php');
            }
             else {
                header('Location:index.php');
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/all.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>MEGAMIND POS</h1>
                <p>Please sign in to continue</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
          <form method="POST" action="index.php">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    
    <div class="form-group">
        <label for="username">Username</label>
        <input 
            type="text" 
            id="username" 
            name="username" 
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
            required 
            autofocus
        >
    </div>
    
    <div class="form-group">
        <label for="password">Password</label>
        <input 
            type="password" 
            id="password" 
            name="password" 
            required
        >
    </div>
    
    <button type="submit" class="btn btn-primary">Sign In</button>
</form>

        </div>
    </div>
    
    <script src="assets/js/login.js"></script>
</body>
</html>
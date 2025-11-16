<?php
// includes/auth.php
session_start();

function redirectIfAuthenticated() {
    if (isset($_SESSION['user'])) {
        // Redirect based on user role
        if ($_SESSION['user']['role'] === 'admin') {
            header('Location: admin/users/dashboard.php');
        } elseif ($_SESSION['user']['role'] === 'cashier') {
            header('Location: cashier/index.php');
        } else {
            // Default fallback
            header('Location: index.php');
        }
        exit;
    }
}

function authenticateUser($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'], // Changed from 'name'
            'role' => $user['role']
        ];
        return true;
    }
    return false;
}

function displayError($message) {
    echo '<div class="alert alert-error">' . htmlspecialchars($message) . '</div>';
}
function logActivity($userId, $action) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action)
            VALUES (?, ?)
        ");
        $stmt->execute([$userId, $action]);
    } catch (PDOException $e) {
        // Silently fail if logging isn't available
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
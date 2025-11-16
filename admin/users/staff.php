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
    if (isset($_POST['add_staff'])) {
        // Add new staff
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // Roles that need login credentials
        $rolesWithCredentials = ['admin', 'manager', 'cashier'];
        
        if (in_array($role, $rolesWithCredentials)) {
            // For roles that need login credentials
            if (empty($username) || empty($password)) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'message' => "Username and password are required for " . ucfirst($role) . " role.",
                    'title' => 'Validation Error'
                ];
                header("Location: staff.php");
                exit;
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO staff (name, email, phone, role, username, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $role, $username, $hashedPassword]);
        } else {
            // For roles that don't need login credentials (waiter, chef)
            $stmt = $pdo->prepare("INSERT INTO staff (name, email, phone, role, username, password) VALUES (?, ?, ?, ?, NULL, NULL)");
            $stmt->execute([$name, $email, $phone, $role]);
        }
        
        // Log the activity
        $staffId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO activity_log (user_id, action, created_at) VALUES (?, ?, NOW())")
            ->execute([$_SESSION['user']['id'], "Added new staff member: $name ($role)"]);
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "Staff member added successfully!",
            'title' => 'Success'
        ];
        header("Location: staff.php");
        exit;
    } elseif (isset($_POST['update_staff'])) {
        // Update existing staff
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Roles that need login credentials
        $rolesWithCredentials = ['admin', 'manager', 'cashier'];
        
        if (in_array($role, $rolesWithCredentials)) {
            // For roles that need login credentials
            if (empty($username)) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'message' => "Username is required for " . ucfirst($role) . " role.",
                    'title' => 'Validation Error'
                ];
                header("Location: staff.php");
                exit;
            }
            
            if (!empty($password)) {
                // Password provided, update it
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE staff SET name = ?, email = ?, phone = ?, role = ?, username = ?, password = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $username, $hashedPassword, $is_active, $id]);
            } else {
                // No password provided, keep existing password
                $stmt = $pdo->prepare("UPDATE staff SET name = ?, email = ?, phone = ?, role = ?, username = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $username, $is_active, $id]);
            }
        } else {
            // For roles that don't need login credentials (waiter, chef)
            $stmt = $pdo->prepare("UPDATE staff SET name = ?, email = ?, phone = ?, role = ?, username = NULL, password = NULL, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $role, $is_active, $id]);
        }
        
        // Log the activity
        $pdo->prepare("INSERT INTO activity_log (user_id, action, created_at) VALUES (?, ?, NOW())")
            ->execute([$_SESSION['user']['id'], "Updated staff member: $name ($role)"]);
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "Staff member updated successfully!",
            'title' => 'Success'
        ];
        header("Location: staff.php");
        exit;
    } elseif (isset($_POST['reset_password'])) {
        // Reset password
        $id = $_POST['id'];
        $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE staff SET password = ? WHERE id = ?");
        $stmt->execute([$password, $id]);
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "Password reset successfully!",
            'title' => 'Success'
        ];
        header("Location: staff.php");
        exit;
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Prevent deleting self
    if ($id != $_SESSION['user']['id']) {
        $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => "Staff member deleted successfully!",
            'title' => 'Success'
        ];
    } else {
        $_SESSION['alert'] = [
            'type' => 'error',
            'message' => "You cannot delete your own account!",
            'title' => 'Error'
        ];
    }
    
    header("Location: staff.php");
    exit;
}

// Get all staff members
$staff = $pdo->query("SELECT * FROM staff ORDER BY role, name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management</title>
   <link rel="stylesheet" href="../../assets/css/staff.css">
   <link rel="stylesheet" href="../../assets/css/all.css">
   <link rel="stylesheet" href="../../assets/css/alerts.css">
   <script src="../../assets/js/alerts.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container">
        <div class="main">
            <div class="header">
            <h1><i class="fas fa-users-cog"></i> Staff Management</h1>

            </div>
        <div class="main-container">
        
        <?php if (isset($_SESSION['alert'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const alertData = <?= json_encode($_SESSION['alert']) ?>;
                    if (alertData.type === 'success') {
                        showSuccess(alertData.message, alertData.title);
                    } else if (alertData.type === 'error') {
                        showError(alertData.message, alertData.title);
                    } else if (alertData.type === 'warning') {
                        showWarning(alertData.message, alertData.title);
                    } else if (alertData.type === 'info') {
                        showInfo(alertData.message, alertData.title);
                    } else if (alertData.type === 'danger') {
                        showDanger(alertData.message, alertData.title);
                    }
                });
            </script>
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>
        
        <button id="addStaffBtn" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Staff
        </button>
        
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $member): ?>
                    <tr>
                        <td><?= htmlspecialchars($member['name']) ?></td>
                        <td><?= htmlspecialchars($member['email']) ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($member['role']) ?>">
                                <?= ucfirst($member['role']) ?>
                            </span>
                            <!-- Debug: Role value: <?= htmlspecialchars($member['role']) ?> -->
                        </td>
                        <td>
                            <span class="active-status <?= $member['is_active'] ? 'active' : 'inactive' ?>"></span>
                            <?= $member['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                        <td class="action-buttons">
                            <button class="btn btn-primary edit-staff" data-id="<?= $member['id'] ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger delete-staff" data-id="<?= $member['id'] ?>">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                            <button class="btn btn-success reset-password" data-id="<?= $member['id'] ?>">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
        </div>
    </div>
    
    
    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Staff</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form method="POST" action="staff.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="cashier">Cashier</option>
                            <option value="waiter" selected>Waiter</option>
                            <option value="chef">Chef</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row" id="loginCredentialsRow" style="display: none;">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password">
                    </div>
                </div>
                
               
                
                <button type="submit" name="add_staff" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Staff
                </button>
            </form>
        </div>
    </div>
    
    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Staff</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form method="POST" action="staff.php">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Full Name</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="text" id="edit_phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role</label>
                        <select id="edit_role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="cashier">Cashier</option>
                            <option value="waiter">Waiter</option>
                            <option value="chef">Chef</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1"> Active
                    </label>
                </div>
                
                <div class="form-row" id="editLoginCredentialsRow" style="display: none;">
                    <div class="form-group">
                        <label for="edit_username">Username</label>
                        <input type="text" id="edit_username" name="username">
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Password</label>
                        <input type="password" id="edit_password" name="password" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                
                <button type="submit" name="update_staff" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Staff
                </button>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Reset Password</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form method="POST" action="staff.php">
                <input type="hidden" id="reset_id" name="id">
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-success">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <span class="close-modal">&times;</span>
            </div>
            <p>Are you sure you want to delete this staff member? This action cannot be undone.</p>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button id="cancelDelete" class="btn btn-primary">Cancel</button>
                <a id="confirmDelete" href="#" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal controls
        function hideModals() {
            document.querySelectorAll('.modal').forEach(function(modal) {
                modal.style.display = 'none';
            });
        }

        document.querySelectorAll('.close-modal, #cancelDelete').forEach(function(el) {
            el.addEventListener('click', function() {
                hideModals();
            });
        });

        var addStaffBtn = document.getElementById('addStaffBtn');
        if (addStaffBtn) {
            addStaffBtn.addEventListener('click', function() {
                var modal = document.getElementById('addStaffModal');
                if (modal) modal.style.display = 'block';
            });
        }

        // Function to toggle login credentials visibility
        function toggleLoginCredentials(role, isEdit = false) {
            const loginCredentialsRow = document.getElementById(isEdit ? 'editLoginCredentialsRow' : 'loginCredentialsRow');
            const usernameInput = document.getElementById(isEdit ? 'edit_username' : 'username');
            const passwordInput = document.getElementById(isEdit ? 'edit_password' : 'password');
            
            // Roles that need login credentials
            const rolesWithCredentials = ['admin', 'manager', 'cashier'];
            
            if (rolesWithCredentials.includes(role)) {
                loginCredentialsRow.style.display = 'flex';
                usernameInput.required = true;
                passwordInput.required = !isEdit; // Password not required for edit (can keep current)
            } else {
                loginCredentialsRow.style.display = 'none';
                usernameInput.required = false;
                passwordInput.required = false;
                if (!isEdit) {
                    usernameInput.value = '';
                    passwordInput.value = '';
                }
            }
        }

        // Add event listener for role selection in Add Staff modal
        const roleSelect = document.getElementById('role');
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                toggleLoginCredentials(this.value, false);
            });
            
            // Initialize on page load
            toggleLoginCredentials(roleSelect.value, false);
        }

                // Edit staff
        document.querySelectorAll('.edit-staff').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                fetch('../includes/get_staff.php?id=' + encodeURIComponent(id))
                    .then(function(response) { 
                        return response.json(); 
                    })
                    .then(function(staff) {
                        document.getElementById('edit_id').value = staff.id;
                        document.getElementById('edit_name').value = staff.name;
                        document.getElementById('edit_email').value = staff.email;
                        document.getElementById('edit_phone').value = staff.phone;
                        document.getElementById('edit_role').value = staff.role;
                        document.getElementById('edit_is_active').checked = staff.is_active == 1;
                        
                        // Toggle login credentials for edit modal
                        toggleLoginCredentials(staff.role, true);
                        
                        // Populate username field if it exists
                        if (staff.username) {
                            document.getElementById('edit_username').value = staff.username;
                        } else {
                            document.getElementById('edit_username').value = '';
                        }
                        
                        var modal = document.getElementById('editStaffModal');
                        if (modal) modal.style.display = 'block';
                    })
                    .catch(function(error) {
                        showError('Failed to load staff data. Please try again.', 'Error');
                    });
            });
        });

        // Add event listener for role selection in Edit Staff modal
        const editRoleSelect = document.getElementById('edit_role');
        if (editRoleSelect) {
            editRoleSelect.addEventListener('change', function() {
                toggleLoginCredentials(this.value, true);
            });
        }

        // Reset password
        document.querySelectorAll('.reset-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                document.getElementById('reset_id').value = id;
                var modal = document.getElementById('resetPasswordModal');
                if (modal) modal.style.display = 'block';
            });
        });

        // Delete staff
        document.querySelectorAll('.delete-staff').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                var confirmDelete = document.getElementById('confirmDelete');
                if (confirmDelete) confirmDelete.setAttribute('href', 'staff.php?delete=' + encodeURIComponent(id));
                var modal = document.getElementById('deleteModal');
                if (modal) modal.style.display = 'block';
            });
        });
    });
    </script>
</body>
</html>
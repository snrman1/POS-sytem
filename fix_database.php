<?php
// Database fix script - run this once to fix the foreign key constraints
require_once 'includes/config.php';

try {
    echo "Fixing database constraints...\n";
    
    // Drop the existing activity_log table to remove the old foreign key constraint
    $pdo->exec("DROP TABLE IF EXISTS activity_log");
    echo "Dropped old activity_log table\n";
    
    // Recreate activity_log table with correct foreign key to staff table
    $pdo->exec("
        CREATE TABLE activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    echo "Recreated activity_log table with correct foreign key\n";
    
    // Add some sample activity log entries if admin exists
    $stmt = $pdo->query("SELECT id FROM staff WHERE role = 'admin' LIMIT 1");
    $admin = $stmt->fetch();
    
    if ($admin) {
        $sampleActivities = [
            ['user_id' => $admin['id'], 'action' => 'Database fixed - users table removed'],
            ['user_id' => $admin['id'], 'action' => 'Activity log recreated'],
            ['user_id' => $admin['id'], 'action' => 'System updated to use staff table only']
        ];
        
        foreach ($sampleActivities as $activity) {
            $pdo->prepare("INSERT INTO activity_log (user_id, action, created_at) VALUES (?, ?, NOW())")
                ->execute([$activity['user_id'], $activity['action']]);
        }
        echo "Added sample activity log entries\n";
    }
    
    echo "Database fix completed successfully!\n";
    echo "You can now add new staff members without foreign key errors.\n";
    
} catch (PDOException $e) {
    echo "Error fixing database: " . $e->getMessage() . "\n";
}
?> 
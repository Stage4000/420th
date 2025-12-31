<?php
/**
 * Database Migration Script
 * Migrates from junction table (user_roles) to boolean columns in users table
 * This script preserves all existing role assignments
 */

require_once 'config.php';

try {
    echo "=== 420th Delta Whitelist Database Migration ===\n\n";
    
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "✓ Connected to database\n\n";
    
    // Step 1: Check if migration is needed
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role_%'");
    if ($stmt->rowCount() > 0) {
        echo "⚠ Migration appears to already be complete (boolean columns exist)\n";
        echo "Do you want to re-run the migration? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) !== 'yes') {
            echo "Migration cancelled.\n";
            exit(0);
        }
        echo "\n";
    }
    
    // Step 2: Add boolean role columns to users table
    echo "Step 1: Adding role columns to users table...\n";
    
    $roleColumns = [
        'role_s3' => 'TINYINT(1) DEFAULT 0',
        'role_cas' => 'TINYINT(1) DEFAULT 0',
        'role_s1' => 'TINYINT(1) DEFAULT 0',
        'role_opfor' => 'TINYINT(1) DEFAULT 0',
        'role_all' => 'TINYINT(1) DEFAULT 0',
        'role_admin' => 'TINYINT(1) DEFAULT 0',
        'role_moderator' => 'TINYINT(1) DEFAULT 0',
        'role_trusted' => 'TINYINT(1) DEFAULT 0',
        'role_media' => 'TINYINT(1) DEFAULT 0',
        'role_curator' => 'TINYINT(1) DEFAULT 0',
        'role_developer' => 'TINYINT(1) DEFAULT 0',
        'role_panel' => 'TINYINT(1) DEFAULT 0',
    ];
    
    foreach ($roleColumns as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS $column $definition");
            echo "  ✓ Added column: $column\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "  ℹ Column already exists: $column\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n";
    
    // Step 3: Migrate existing data
    echo "Step 2: Migrating existing role assignments...\n";
    
    // Get all role assignments from user_roles table
    $stmt = $pdo->query("
        SELECT ur.user_id, r.name
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
    ");
    
    $assignments = $stmt->fetchAll();
    $migratedCount = 0;
    
    // Role name mapping
    $roleMapping = [
        'S3' => 'role_s3',
        'CAS' => 'role_cas',
        'S1' => 'role_s1',
        'OPFOR' => 'role_opfor',
        'ALL' => 'role_all',
        'ADMIN' => 'role_admin',
        'MODERATOR' => 'role_moderator',
        'TRUSTED' => 'role_trusted',
        'MEDIA' => 'role_media',
        'CURATOR' => 'role_curator',
        'DEVELOPER' => 'role_developer',
        'PANEL' => 'role_panel',
    ];
    
    // Group assignments by user
    $userRoles = [];
    foreach ($assignments as $assignment) {
        $userId = $assignment['user_id'];
        $roleName = $assignment['name'];
        
        if (!isset($userRoles[$userId])) {
            $userRoles[$userId] = [];
        }
        
        if (isset($roleMapping[$roleName])) {
            $userRoles[$userId][] = $roleMapping[$roleName];
        }
    }
    
    // Update each user
    foreach ($userRoles as $userId => $roles) {
        $setClause = implode(' = 1, ', $roles) . ' = 1';
        $sql = "UPDATE users SET $setClause WHERE id = ?";
        $pdo->prepare($sql)->execute([$userId]);
        $migratedCount++;
    }
    
    echo "  ✓ Migrated $migratedCount users with role assignments\n";
    echo "  ✓ Total role assignments migrated: " . count($assignments) . "\n\n";
    
    // Step 4: Backup old tables (rename instead of drop)
    echo "Step 3: Backing up old tables...\n";
    
    try {
        $pdo->exec("RENAME TABLE user_roles TO user_roles_backup_" . date('Ymd_His'));
        echo "  ✓ Backed up user_roles table\n";
    } catch (PDOException $e) {
        echo "  ⚠ Could not backup user_roles: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Step 5: Verify migration
    echo "Step 4: Verifying migration...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE 
        role_s3 = 1 OR role_cas = 1 OR role_s1 = 1 OR role_opfor = 1 OR 
        role_all = 1 OR role_admin = 1 OR role_moderator = 1 OR role_trusted = 1 OR 
        role_media = 1 OR role_curator = 1 OR role_developer = 1 OR role_panel = 1");
    
    $result = $stmt->fetch();
    echo "  ✓ Users with roles after migration: " . $result['count'] . "\n";
    
    echo "\n";
    echo "=== Migration Complete! ===\n\n";
    echo "Summary:\n";
    echo "- Added 12 boolean role columns to users table\n";
    echo "- Migrated $migratedCount users with their role assignments\n";
    echo "- Backed up old user_roles table\n";
    echo "- Verified " . $result['count'] . " users have roles in new schema\n\n";
    echo "Note: The roles table is preserved for alias management.\n";
    echo "Note: Old tables are backed up with timestamp, you can drop them once verified.\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
require_once '_init.php';

Guard::adminOnly();

// Set role context for navbar
$GLOBALS['CURRENT_ROLE_CONTEXT'] = ROLE_ADMIN;

$title = 'Database Backup & Restore';

// Handle file upload and restore
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    if (!isset($_SESSION['user_id_admin'])) {
        $message = 'Unauthorized access';
        $messageType = 'error';
    } else {
        $file = $_FILES['backup_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = 'File upload error: ' . $file['error'];
            $messageType = 'error';
        } elseif (strpos($file['type'], 'sql') === false && strpos($file['name'], '.sql') === false) {
            $message = 'Invalid file type. Please upload a SQL backup file.';
            $messageType = 'error';
        } else {
            try {
                $sqlContent = file_get_contents($file['tmp_name']);
                global $connection;
                
                // Execute SQL statements
                $statements = array_filter(
                    array_map('trim', explode(';', $sqlContent)),
                    fn($s) => !empty($s) && strpos($s, '--') !== 0
                );
                
                $connection->beginTransaction();
                
                $successCount = 0;
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        try {
                            $connection->exec($statement);
                            $successCount++;
                        } catch (Exception $e) {
                            // Log error but continue with other statements
                            error_log("SQL Error: " . $e->getMessage());
                        }
                    }
                }
                
                $connection->commit();
                
                $message = "Database restored successfully! Executed $successCount SQL statements.";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Restore failed: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/util.css">
    <style>
        main {
            width: 100%;
        }
        
        .wrapper {
            max-width: 100%;
            padding: 16px;
            width: 100%;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-body);
            margin-bottom: 24px;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-body);
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--green-500);
            color: var(--green-500);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--red-500);
            color: var(--red-500);
        }
        
        .backup-section {
            margin-bottom: 30px;
        }
        
        .backup-button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-darker) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .backup-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }
        
        .backup-button.success {
            background: linear-gradient(135deg, var(--green-500) 0%, var(--green-600) 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .backup-button.success:hover {
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        .backup-button.danger {
            background: linear-gradient(135deg, var(--red-500) 0%, var(--red-600) 100%);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .backup-button.danger:hover {
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input[type="file"] {
            padding: 10px 12px;
            background: var(--input-bg);
            border: 2px dashed var(--input-border);
            border-radius: 8px;
            color: var(--input-text);
            font-size: 14px;
            width: 100%;
            max-width: 500px;
        }
        
        .form-group input[type="file"]:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--primary);
            border-radius: 8px;
            padding: 16px;
            color: var(--primary);
            font-size: 13px;
            line-height: 1.6;
        }
        
        .info-box strong {
            color: var(--text-body);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 16px;
        }
        
        .stat-card {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }
        
        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-body);
        }
        
        @media (max-width: 768px) {
            .wrapper {
                padding: 12px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'templates/admin_header.php'; ?>
    
    <div class="flex">
        <?php require_once 'templates/admin_navbar.php'; ?>
        
        <main style="flex: 1; width: 100%;">
            <div class="wrapper">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 32px 24px; margin-bottom: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);">
                    <h1 style="font-size: 2em; font-weight: 800; margin: 0 0 8px 0;">💾 Database Backup & Restore</h1>
                    <p style="margin: 0; opacity: 0.9; font-size: 1em;">Download backups and restore your database from previous versions.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Backup Section -->
                <div class="card backup-section">
                    <h2 class="card-title">💾 Download Database Backup</h2>
                    <p style="color: var(--text-muted); margin-bottom: 16px;">
                        Download a complete backup of your database including all tables and data. 
                        Use this for regular backups or before major updates.
                    </p>
                    <button onclick="downloadBackup()" class="backup-button success">
                        📥 Download Backup Now
                    </button>
                    <div class="info-box" style="margin-top: 16px;">
                        <strong>ℹ️ Backup Information:</strong><br>
                        • File Format: SQL (.sql)<br>
                        • Includes: All tables, schemas, and data<br>
                        • Size: May vary (typically 1-10 MB)<br>
                        • Can be restored using admin restore function or MySQL client
                    </div>
                </div>
                
                <!-- Restore Section -->
                <div class="card">
                    <h2 class="card-title">🔄 Restore from Backup</h2>
                    <p style="color: var(--text-muted); margin-bottom: 16px;">
                        Upload a previously downloaded SQL backup file to restore your database.
                        <strong style="color: var(--red-500);">WARNING: This will replace current data.</strong>
                    </p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="backup_file">Select SQL Backup File</label>
                            <input type="file" id="backup_file" name="backup_file" accept=".sql,application/sql" required>
                        </div>
                        <button type="submit" class="backup-button danger" onclick="return confirm('⚠️ This will replace all current database data with the backup. Are you sure?');">
                            🔄 Restore Database
                        </button>
                    </form>
                    
                    <div class="info-box" style="margin-top: 16px;">
                        <strong>⚠️ Important:</strong><br>
                        • Restoring will replace ALL current data<br>
                        • Make a backup before restoring<br>
                        • Only upload SQL files you've downloaded from this system<br>
                        • Restore process may take several seconds to minutes
                    </div>
                </div>
                
                <!-- Database Statistics -->
                <div class="card">
                    <h2 class="card-title">📊 Database Statistics</h2>
                    <div class="stats-grid">
                        <?php
                        global $connection;
                        try {
                            $stmt = $connection->query("SELECT COUNT(*) as count FROM users");
                            $users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            $stmt = $connection->query("SELECT COUNT(*) as count FROM products");
                            $products = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            $stmt = $connection->query("SELECT COUNT(*) as count FROM orders");
                            $orders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            $stmt = $connection->query("SELECT COUNT(*) as count FROM categories");
                            $categories = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                            <div class="stat-card">
                                <div class="stat-label">👥 Users</div>
                                <div class="stat-value"><?= $users ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">📦 Products</div>
                                <div class="stat-value"><?= $products ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">🛒 Orders</div>
                                <div class="stat-value"><?= $orders ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">🏷️ Categories</div>
                                <div class="stat-value"><?= $categories ?></div>
                            </div>
                        <?php
                        } catch (Exception $e) {
                            echo '<div class="stat-card"><p>Error loading statistics</p></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function downloadBackup() {
            if (!confirm('Download database backup? This may take a moment.')) {
                return;
            }
            
            fetch('api/backup_database.php', {
                method: 'POST'
            })
            .then(response => {
                if (!response.ok) throw new Error('Backup failed');
                return response.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `pos_backup_${new Date().toISOString().split('T')[0]}_${new Date().getTime()}.sql`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            })
            .catch(error => {
                console.error('Backup Error:', error);
                alert('Failed to create backup: ' + error.message);
            });
        }
    </script>
</body>
</html>

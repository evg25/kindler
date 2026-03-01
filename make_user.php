<?php
/**
 * User Management Tool
 * Allows admin to add/update users
 * 
 * SECURITY: 
 * - Requires admin login
 * - Requires setup token in URL for first access
 * - DELETE THIS FILE after initial setup is complete
 */

require_once __DIR__ . '/auth.php';

// Setup token - change this to a random string for first access
// After setup, you can remove this check or this file
$SETUP_TOKEN = 'setup_' . md5('poetry.kindler.cz'); // Change this!

// Check setup token OR require admin login
$is_setup_mode = isset($_GET['token']) && $_GET['token'] === $SETUP_TOKEN;

if (!$is_setup_mode) {
    auth_require_login();
    
    // Verify user is admin
    if (auth_get_username() !== 'admin') {
        http_response_code(403);
        exit('Only admin can manage users');
    }
}

$message = '';
$error = '';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF
    if (!$is_setup_mode && !auth_verify_csrf_token($csrf_token)) {
        $error = 'Invalid request token';
    }
    elseif (empty($username)) {
        $error = 'Username is required';
    }
    elseif ($action === 'add' || $action === 'update') {
        if (empty($password)) {
            $error = 'Password is required';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            // Load existing users
            $users_file = __DIR__ . '/_private/users.php';
            $users = file_exists($users_file) ? include($users_file) : [];
            
            // Check if user exists
            $user_exists = isset($users[$username]);
            
            if ($action === 'add' && $user_exists) {
                $error = 'User already exists. Use Update to change password.';
            } else {
                // Create/update user
                $users[$username] = password_hash($password, PASSWORD_BCRYPT);
                
                // Save to file
                $content = "<?php\n/**\n * User Credentials Storage\n * Returns array of username => password_hash\n */\n\n";
                $content .= "// Prevent direct web access\n";
                $content .= "if (basename(__FILE__) === basename(\$_SERVER['SCRIPT_FILENAME'] ?? '')) {\n";
                $content .= "    http_response_code(403);\n";
                $content .= "    exit('Access denied');\n";
                $content .= "}\n\n";
                $content .= "return [\n";
                
                foreach ($users as $user => $hash) {
                    $content .= "    " . var_export($user, true) . " => " . var_export($hash, true) . ",\n";
                }
                
                $content .= "];\n";
                
                if (file_put_contents($users_file, $content)) {
                    $message = $user_exists ? 
                        "Password updated for user: $username" : 
                        "User created: $username";
                } else {
                    $error = 'Failed to save user data';
                }
            }
        }
    }
    elseif ($action === 'delete') {
        if ($username === 'admin') {
            $error = 'Cannot delete admin user';
        } else {
            $users_file = __DIR__ . '/_private/users.php';
            $users = include($users_file);
            
            if (isset($users[$username])) {
                unset($users[$username]);
                
                $content = "<?php\n/**\n * User Credentials Storage\n */\n\n";
                $content .= "if (basename(__FILE__) === basename(\$_SERVER['SCRIPT_FILENAME'] ?? '')) {\n";
                $content .= "    http_response_code(403);\n";
                $content .= "    exit('Access denied');\n";
                $content .= "}\n\n";
                $content .= "return [\n";
                
                foreach ($users as $user => $hash) {
                    $content .= "    " . var_export($user, true) . " => " . var_export($hash, true) . ",\n";
                }
                
                $content .= "];\n";
                
                file_put_contents($users_file, $content);
                $message = "User deleted: $username";
            } else {
                $error = 'User not found';
            }
        }
    }
}

// Load current users
$users = auth_load_users();
$csrf_token = $is_setup_mode ? '' : auth_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .admin-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 0.5rem;
        }
        .btn-primary {
            background: #7d695a;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .user-list {
            list-style: none;
            padding: 0;
        }
        .user-item {
            padding: 0.75rem;
            background: white;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>User Management</h1>
        
        <?php if ($is_setup_mode): ?>
        <div class="warning-box">
            <strong>Setup Mode Active!</strong> 
            <p>You are accessing this in setup mode. After creating users, DELETE this file (make_user.php) for security.</p>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Add/Update User -->
        <div class="form-section">
            <h2>Add or Update User</h2>
            <form method="post" id="userForm">
                <?php if (!$is_setup_mode): ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php endif; ?>
                <input type="hidden" name="action" value="add" id="actionField">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password (min 8 characters)</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn">Add/Update User</button>
            </form>
            
            <script>
            // Auto-detect existing users and switch to update mode
            const existingUsers = <?php echo json_encode(array_keys($users)); ?>;
            const usernameInput = document.getElementById('username');
            const actionField = document.getElementById('actionField');
            const submitBtn = document.getElementById('submitBtn');
            
            usernameInput.addEventListener('input', function() {
                const username = this.value.trim();
                if (existingUsers.includes(username)) {
                    actionField.value = 'update';
                    submitBtn.textContent = 'Update User Password';
                } else {
                    actionField.value = 'add';
                    submitBtn.textContent = 'Add New User';
                }
            });
            </script>
        </div>
        
        <!-- Current Users -->
        <div class="form-section">
            <h2>Current Users (<?php echo count($users); ?>)</h2>
            <ul class="user-list">
                <?php foreach (array_keys($users) as $username): ?>
                <li class="user-item">
                    <span><strong><?php echo htmlspecialchars($username); ?></strong></span>
                    <?php if ($username !== 'admin'): ?>
                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($username); ?>?');">
                        <?php if (!$is_setup_mode): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                    <?php else: ?>
                    <span style="color: #666;">(protected)</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div style="margin-top: 2rem;">
            <?php if (!$is_setup_mode): ?>
            <a href="/app/poems/">← Back to Archive</a> |
            <a href="/logout.php">Logout</a>
            <?php endif; ?>
        </div>
        
        <div class="warning-box" style="margin-top: 2rem;">
            <strong>Security Notice:</strong>
            <p><strong>DELETE THIS FILE (make_user.php) after initial setup!</strong></p>
            <p>Keep this file only if you need to manage users regularly. Otherwise, remove it to prevent unauthorized access.</p>
        </div>
    </div>
</body>
</html>

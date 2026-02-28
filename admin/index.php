<?php
session_start();

// Load centralized logger
require_once __DIR__ . '/../logger.php';

// Configuration
$HTPASSWD_FILE = '/data/web/virtuals/383545/virtual/www/domains/poetry.kindler.cz/.htpasswd';
$AUDIT_LOG = __DIR__ . '/admin_audit.log';

// Get authenticated user (check both PHP_AUTH_USER and REMOTE_USER)
$auth_user = $_SERVER['PHP_AUTH_USER'] ?? $_SERVER['REMOTE_USER'] ?? null;

// Access control: only allow user "admin"
if ($auth_user !== 'admin') {
    log_error('admin/index.php', 'ACCESS_DENIED', 'Non-admin user attempted access', ['user' => $auth_user]);
    http_response_code(403);
    die('403 Forbidden: Admin access only');
}

$ACTING_USER = $auth_user;
$REMOTE_IP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Helper: validate username
function validate_username($username) {
    if (strlen($username) < 3 || strlen($username) > 32) {
        return false;
    }
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        return false;
    }
    if (strpos($username, ':') !== false || preg_match('/\s/', $username)) {
        return false;
    }
    return true;
}

// Helper: validate password
function validate_password($password) {
    return strlen($password) >= 10 && !preg_match('/[\r\n]/', $password);
}

// Helper: read users from htpasswd
function read_users($file) {
    if (!file_exists($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $users = [];
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $users[$parts[0]] = $parts[1];
        }
    }
    return $users;
}

// Helper: write users to htpasswd atomically
function write_users($file, $users) {
    $dir = dirname($file);
    $temp_file = tempnam($dir, '.htpasswd_tmp_');
    if ($temp_file === false) {
        return false;
    }
    
    $fp = fopen($temp_file, 'w');
    if (!$fp) {
        @unlink($temp_file);
        return false;
    }
    
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        @unlink($temp_file);
        return false;
    }
    
    foreach ($users as $username => $hash) {
        fwrite($fp, $username . ':' . $hash . "\n");
    }
    
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    
    // Atomic replace
    if (!rename($temp_file, $file)) {
        @unlink($temp_file);
        return false;
    }
    
    return true;
}

// Helper: audit log
function audit_log($log_file, $ip, $user, $action, $target, $status, $reason = '') {
    $entry = sprintf(
        "[%s] IP: %s | User: %s | Action: %s | Target: %s | Status: %s | Reason: %s\n",
        date('c'),
        $ip,
        $user,
        $action,
        $target,
        $status,
        $reason
    );
    @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
        log_error('admin/index.php', 'CSRF_ERROR', 'CSRF token mismatch', ['action' => $_POST['action'] ?? 'unknown']);
        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'UNKNOWN', 'N/A', 'FAIL', 'CSRF token mismatch');
    }
    else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (!validate_username($username)) {
                $error = 'Invalid username (3-32 chars, a-zA-Z0-9._- only)';
                audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'ADD', $username, 'FAIL', 'Invalid username format');
            }
            elseif (!validate_password($password)) {
                $error = 'Invalid password (min 10 chars, no line breaks)';
                audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'ADD', $username, 'FAIL', 'Invalid password format');
            }
            else {
                $users = read_users($HTPASSWD_FILE);
                if (isset($users[$username])) {
                    $error = 'User already exists';
                    audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'ADD', $username, 'FAIL', 'User already exists');
                }
                else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $users[$username] = $hash;
                    if (write_users($HTPASSWD_FILE, $users)) {
                        $message = "User '$username' added successfully";
                        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'ADD', $username, 'OK', '');
                    }
                    else {
                        $error = 'Failed to write password file';
                        log_error('admin/index.php', 'FILE_WRITE_ERROR', 'Cannot write to .htpasswd file', ['action' => 'ADD', 'username' => $username]);
                        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'ADD', $username, 'FAIL', 'Write error');
                    }
                }
            }
        }
        elseif ($action === 'reset') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (!validate_username($username)) {
                $error = 'Invalid username';
                audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'RESET', $username, 'FAIL', 'Invalid username format');
            }
            elseif (!validate_password($password)) {
                $error = 'Invalid password (min 10 chars, no line breaks)';
                audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'RESET', $username, 'FAIL', 'Invalid password format');
            }
            else {
                $users = read_users($HTPASSWD_FILE);
                if (!isset($users[$username])) {
                    $error = 'User does not exist';
                    audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'RESET', $username, 'FAIL', 'User not found');
                }
                else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $users[$username] = $hash;
                    if (write_users($HTPASSWD_FILE, $users)) {
                        $message = "Password for '$username' reset successfully";
                        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'RESET', $username, 'OK', '');
                    }
                    else {
                        $error = 'Failed to write password file';
                        log_error('admin/index.php', 'FILE_WRITE_ERROR', 'Cannot write to .htpasswd file', ['action' => 'RESET', 'username' => $username]);
                        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'RESET', $username, 'FAIL', 'Write error');
                    }
                }
            }
        }
        elseif ($action === 'delete') {
            $username = trim($_POST['username'] ?? '');
            
            if ($username === 'admin') {
                $error = 'Cannot delete admin user';
                audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'DELETE', $username, 'FAIL', 'Cannot delete admin');
            }
            elseif (!validate_username($username)) {
                $error = 'Invalid username';
                audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'DELETE', $username, 'FAIL', 'Invalid username format');
            }
            else {
                $users = read_users($HTPASSWD_FILE);
                if (!isset($users[$username])) {
                    $error = 'User does not exist';
                    audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'DELETE', $username, 'FAIL', 'User not found');
                }
                else {
                    unset($users[$username]);
                    if (write_users($HTPASSWD_FILE, $users)) {
                        $message = "User '$username' deleted successfully";
                        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'DELETE', $username, 'OK', '');
                    }
                    else {
                        $error = 'Failed to write password file';
                        log_error('admin/index.php', 'FILE_WRITE_ERROR', 'Cannot write to .htpasswd file', ['action' => 'DELETE', 'username' => $username]);
                        audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'DELETE', $username, 'FAIL', 'Write error');
                    }
                }
            }
        }
    }
}

// Read current users for display
$users = read_users($HTPASSWD_FILE);
audit_log($AUDIT_LOG, $REMOTE_IP, $ACTING_USER, 'LIST', 'N/A', 'OK', count($users) . ' users listed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — User Management</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
            background: #f5f5f5;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
        }
        h2 {
            color: #34495e;
            margin-top: 2rem;
            font-size: 1.2rem;
        }
        .message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .form-section {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            max-width: 300px;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 1rem;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover {
            background: #2980b9;
        }
        button.delete {
            background: #e74c3c;
        }
        button.delete:hover {
            background: #c0392b;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
            font-weight: 500;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .nav-link {
            display: inline-block;
            margin-top: 1rem;
            color: #3498db;
            text-decoration: none;
        }
        .nav-link:hover {
            text-decoration: underline;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <h1>🔐 User Management Panel</h1>
    
    <div class="info">
        <strong>Logged in as:</strong> <?php echo htmlspecialchars($ACTING_USER); ?><br>
        <strong>Managing:</strong> <?php echo htmlspecialchars($HTPASSWD_FILE); ?>
    </div>

    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h2>📋 Current Users</h2>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="2">No users found</td></tr>
            <?php else: ?>
                <?php foreach (array_keys($users) as $username): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($username); ?></td>
                        <td>
                            <?php if ($username === 'admin'): ?>
                                <em>(protected)</em>
                            <?php else: ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($username); ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="form-section">
        <h2>➕ Add New User</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="add_username">Username (3-32 chars, a-zA-Z0-9._- only):</label>
                <input type="text" id="add_username" name="username" required pattern="[a-zA-Z0-9._-]{3,32}">
            </div>
            <div class="form-group">
                <label for="add_password">Password (min 10 chars):</label>
                <input type="password" id="add_password" name="password" required minlength="10">
            </div>
            <button type="submit">Add User</button>
        </form>
    </div>

    <div class="form-section">
        <h2>🔄 Reset User Password</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="reset">
            <div class="form-group">
                <label for="reset_username">Username:</label>
                <input type="text" id="reset_username" name="username" required pattern="[a-zA-Z0-9._-]{3,32}">
            </div>
            <div class="form-group">
                <label for="reset_password">New Password (min 10 chars):</label>
                <input type="password" id="reset_password" name="password" required minlength="10">
            </div>
            <button type="submit">Reset Password</button>
        </form>
    </div>

    <a href="/" class="nav-link">← Back to Main Site</a>
</body>
</html>

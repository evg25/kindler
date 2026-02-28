<?php
// Centralized error log viewer
// Only accessible by admin user

// Get authenticated user
$auth_user = $_SERVER['PHP_AUTH_USER'] ?? $_SERVER['REMOTE_USER'] ?? null;

// Access control: only admin
if ($auth_user !== 'admin') {
    http_response_code(403);
    die('403 Forbidden: Admin access only');
}

$log_file = __DIR__ . '/error_log.txt';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Log Viewer</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            margin: 0;
            padding: 1rem;
        }
        h1 {
            color: #569cd6;
            font-size: 1.5rem;
        }
        .info {
            background: #264f78;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .log-container {
            background: #252526;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
        }
        .log-entry {
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        .timestamp {
            color: #6a9955;
        }
        .script {
            color: #ce9178;
        }
        .error {
            color: #f48771;
        }
        .context {
            color: #dcdcaa;
        }
        .nav-link {
            color: #4ec9b0;
            text-decoration: none;
        }
        .nav-link:hover {
            text-decoration: underline;
        }
        .empty {
            color: #858585;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>🔍 Centralized Error Log</h1>
    
    <div class="info">
        <strong>File:</strong> <?php echo htmlspecialchars($log_file); ?><br>
        <strong>Logged in as:</strong> <?php echo htmlspecialchars($auth_user); ?>
    </div>

    <div class="log-container">
        <?php if (file_exists($log_file)): ?>
            <?php
            $lines = file($log_file, FILE_IGNORE_NEW_LINES);
            if (empty($lines)) {
                echo '<p class="empty">Log file is empty.</p>';
            } else {
                // Show last 100 lines (most recent first)
                $lines = array_reverse(array_slice($lines, -100));
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    // Parse log line and colorize
                    $line = htmlspecialchars($line);
                    $line = preg_replace('/(\[[\d\-:T+]+\])/', '<span class="timestamp">$1</span>', $line);
                    $line = preg_replace('/(Script: [^\|]+)/', '<span class="script">$1</span>', $line);
                    $line = preg_replace('/(Context: [^\|]+)/', '<span class="context">$1</span>', $line);
                    $line = preg_replace('/(Error: .+)/', '<span class="error">$1</span>', $line);
                    
                    echo '<div class="log-entry">' . $line . '</div>';
                }
            }
            ?>
        <?php else: ?>
            <p class="empty">Log file does not exist yet. It will be created when the first error is logged.</p>
        <?php endif; ?>
    </div>

    <p style="margin-top: 1rem;">
        <a href="/admin/" class="nav-link">← Back to Admin Panel</a>
    </p>
</body>
</html>

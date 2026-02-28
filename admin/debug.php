<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== HTTP Basic Auth Debug Info ===\n\n";

echo "PHP_AUTH_USER: " . ($_SERVER['PHP_AUTH_USER'] ?? 'NOT SET') . "\n";
echo "PHP_AUTH_PW: " . (isset($_SERVER['PHP_AUTH_PW']) ? '[HIDDEN - present]' : 'NOT SET') . "\n";
echo "REMOTE_USER: " . ($_SERVER['REMOTE_USER'] ?? 'NOT SET') . "\n";
echo "AUTH_TYPE: " . ($_SERVER['AUTH_TYPE'] ?? 'NOT SET') . "\n";

echo "\n=== Access Status ===\n";
if (isset($_SERVER['PHP_AUTH_USER'])) {
    echo "You are logged in as: " . $_SERVER['PHP_AUTH_USER'] . "\n";
    if ($_SERVER['PHP_AUTH_USER'] === 'admin') {
        echo "✓ You have admin access\n";
    } else {
        echo "✗ You need to log in as 'admin' to access the admin panel\n";
    }
} else {
    echo "✗ No authentication detected\n";
}

echo "\n=== .htpasswd File Check ===\n";
$htpasswd = '/data/web/virtuals/383545/virtual/www/domains/poetry.kindler.cz/.htpasswd';
if (file_exists($htpasswd)) {
    echo "✓ .htpasswd file exists\n";
    $lines = file($htpasswd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Users found: " . count($lines) . "\n";
    echo "\nUsernames:\n";
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            echo "  - " . $parts[0] . "\n";
        }
    }
} else {
    echo "✗ .htpasswd file not found\n";
}
?>

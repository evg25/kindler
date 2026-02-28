<?php
// Simple log viewer
$log_file = __DIR__ . '/mail_errors.log';

header('Content-Type: text/plain; charset=utf-8');

if (file_exists($log_file)) {
    echo "=== MAIL ERROR LOG ===\n\n";
    echo file_get_contents($log_file);
} else {
    echo "Log file does not exist yet.\n";
    echo "Expected location: " . $log_file . "\n\n";
    echo "The log will be created after the first form submission.\n";
}
?>

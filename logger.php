<?php
// Centralized error logging function
// Usage: log_error($script, $context, $error_message)

function log_error($script, $context, $error_message, $extra_data = []) {
    $log_file = __DIR__ . '/error_log.txt';
    
    $log_entry = sprintf(
        "[%s] Script: %s | Context: %s | IP: %s | Error: %s",
        date('c'),
        $script,
        $context,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $error_message
    );
    
    if (!empty($extra_data)) {
        $log_entry .= " | Data: " . json_encode($extra_data, JSON_UNESCAPED_UNICODE);
    }
    
    $log_entry .= "\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function log_request($script, $action, $status, $details = '') {
    $log_file = __DIR__ . '/error_log.txt';
    
    $log_entry = sprintf(
        "[%s] Script: %s | Action: %s | IP: %s | Status: %s | Details: %s\n",
        date('c'),
        $script,
        $action,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $status,
        $details
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>

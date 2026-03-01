<?php
/**
 * User Credentials Storage
 * Returns array of username => password_hash
 * 
 * IMPORTANT: This file must be protected by Apache (.htaccess)
 */

// Prevent direct web access
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Access denied');
}

// Default admin user (password: changeme123)
// CHANGE THIS PASSWORD IMMEDIATELY after first login using make_user.php
return [
    'admin' => '$2y$10$bNuko6XiD8YXNi9Nj7VAZu7ymUwKzgQc4w12.QdK/MkaAq2.kNzpu',
];

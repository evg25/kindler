<?php
/**
 * Static File Server with Authentication Gate
 * Serves files from /app/ only to authenticated users
 */

require_once __DIR__ . '/../auth.php';

// Require authentication
auth_require_login($_SERVER['REQUEST_URI']);

// Get requested path
$request_uri = $_SERVER['REQUEST_URI'];

// Remove query string
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove /app/ prefix
if (strpos($path, '/app/') === 0) {
    $path = substr($path, 4); // Remove '/app'
} else {
    http_response_code(400);
    exit('Invalid request');
}

// Build real filesystem path
$base_dir = realpath(__DIR__);
$requested_file = $base_dir . $path;

// Normalize and verify path stays within /app/
$real_path = realpath($requested_file);

if ($real_path === false || strpos($real_path, $base_dir) !== 0) {
    http_response_code(404);
    exit('Not found');
}

// Deny access to .php files and dotfiles (except index.html)
$basename = basename($real_path);
if ($basename[0] === '.' || 
    (pathinfo($real_path, PATHINFO_EXTENSION) === 'php' && $basename !== 'index.php')) {
    http_response_code(403);
    exit('Access denied');
}

// If directory, try to serve index.html
if (is_dir($real_path)) {
    $index_file = $real_path . '/index.html';
    if (file_exists($index_file)) {
        $real_path = $index_file;
    } else {
        http_response_code(404);
        exit('Directory listing not available');
    }
}

// Check file exists
if (!is_file($real_path)) {
    http_response_code(404);
    exit('File not found');
}

// Determine content type
$ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
$content_types = [
    'html' => 'text/html',
    'htm'  => 'text/html',
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain',
    'md'   => 'text/plain',
];

$content_type = $content_types[$ext] ?? 'application/octet-stream';

// Send headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($real_path));
header('X-Content-Type-Options: nosniff');

// For HTML files add UTF-8 charset
if ($content_type === 'text/html') {
    header('Content-Type: text/html; charset=UTF-8');
}

// Stream the file
readfile($real_path);
exit;

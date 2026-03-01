<?php
/**
 * Authentication Library
 * Handles session management, credential verification, and security features
 */

// Prevent direct access
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Session configuration - call before session_start()
function auth_configure_session() {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    
    // Force secure cookies on HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_name('AUTH_SESSION');
}

// Start secure session
function auth_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        auth_configure_session();
        session_start();
    }
}

// Check if user is authenticated
function auth_is_logged_in() {
    auth_start_session();
    return isset($_SESSION['authenticated']) && 
           $_SESSION['authenticated'] === true &&
           isset($_SESSION['username']);
}

// Get current username
function auth_get_username() {
    auth_start_session();
    return $_SESSION['username'] ?? null;
}

// Load user credentials from private storage
function auth_load_users() {
    $users_file = __DIR__ . '/_private/users.php';
    if (!file_exists($users_file)) {
        return [];
    }
    return include $users_file;
}

// Verify login credentials
function auth_verify_credentials($username, $password) {
    $users = auth_load_users();
    
    if (!isset($users[$username])) {
        return false;
    }
    
    return password_verify($password, $users[$username]);
}

// Perform login
function auth_login($username) {
    auth_start_session();
    
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    
    // Generate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Perform logout
function auth_logout() {
    auth_start_session();
    
    $_SESSION = [];
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

// Generate CSRF token
function auth_get_csrf_token() {
    auth_start_session();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function auth_verify_csrf_token($token) {
    auth_start_session();
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Brute force protection - file-based tracking
function auth_get_attempts_file() {
    $dir = __DIR__ . '/_private/attempts';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

// Get client IP
function auth_get_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Check if IP or username is locked out
function auth_is_locked_out($username) {
    $attempts_dir = auth_get_attempts_file();
    $ip = auth_get_client_ip();
    
    // Check IP-based lockout
    $ip_file = $attempts_dir . '/ip_' . md5($ip) . '.json';
    if (file_exists($ip_file)) {
        $data = json_decode(file_get_contents($ip_file), true);
        if ($data && $data['locked_until'] > time()) {
            return true;
        }
    }
    
    // Check username-based lockout
    if ($username) {
        $user_file = $attempts_dir . '/user_' . md5($username) . '.json';
        if (file_exists($user_file)) {
            $data = json_decode(file_get_contents($user_file), true);
            if ($data && $data['locked_until'] > time()) {
                return true;
            }
        }
    }
    
    return false;
}

// Record failed login attempt
function auth_record_failed_attempt($username) {
    $attempts_dir = auth_get_attempts_file();
    $ip = auth_get_client_ip();
    $now = time();
    
    // Max 10 failures before 5 minute lockout
    $max_attempts = 10;
    $lockout_duration = 300; // 5 minutes
    
    // Record IP attempt
    $ip_file = $attempts_dir . '/ip_' . md5($ip) . '.json';
    $ip_data = file_exists($ip_file) ? json_decode(file_get_contents($ip_file), true) : ['count' => 0, 'first' => $now];
    
    // Reset if more than 1 hour passed
    if ($now - $ip_data['first'] > 3600) {
        $ip_data = ['count' => 0, 'first' => $now];
    }
    
    $ip_data['count']++;
    $ip_data['last'] = $now;
    
    if ($ip_data['count'] >= $max_attempts) {
        $ip_data['locked_until'] = $now + $lockout_duration;
    }
    
    file_put_contents($ip_file, json_encode($ip_data));
    
    // Record username attempt
    if ($username) {
        $user_file = $attempts_dir . '/user_' . md5($username) . '.json';
        $user_data = file_exists($user_file) ? json_decode(file_get_contents($user_file), true) : ['count' => 0, 'first' => $now];
        
        if ($now - $user_data['first'] > 3600) {
            $user_data = ['count' => 0, 'first' => $now];
        }
        
        $user_data['count']++;
        $user_data['last'] = $now;
        
        if ($user_data['count'] >= $max_attempts) {
            $user_data['locked_until'] = $now + $lockout_duration;
        }
        
        file_put_contents($user_file, json_encode($user_data));
    }
}

// Clear failed attempts on successful login
function auth_clear_failed_attempts($username) {
    $attempts_dir = auth_get_attempts_file();
    $ip = auth_get_client_ip();
    
    $ip_file = $attempts_dir . '/ip_' . md5($ip) . '.json';
    $user_file = $attempts_dir . '/user_' . md5($username) . '.json';
    
    @unlink($ip_file);
    @unlink($user_file);
}

// Validate "next" redirect URL - only allow local /app/ paths
function auth_validate_next_url($url) {
    if (empty($url)) {
        return false;
    }
    
    // Reject if contains scheme or host
    if (preg_match('#^https?://#i', $url)) {
        return false;
    }
    
    // Reject backslashes
    if (strpos($url, '\\') !== false) {
        return false;
    }
    
    // Reject parent directory traversal
    if (strpos($url, '..') !== false) {
        return false;
    }
    
    // Must start with /app/
    if (strpos($url, '/app/') !== 0) {
        return false;
    }
    
    return true;
}

// Get safe redirect URL
function auth_get_safe_redirect() {
    $default = '/app/poems/';
    
    if (isset($_GET['next'])) {
        $next = $_GET['next'];
        if (auth_validate_next_url($next)) {
            return $next;
        }
    }
    
    return $default;
}

// Require authentication - redirect to login if not logged in
function auth_require_login($next_url = null) {
    if (!auth_is_logged_in()) {
        $next_param = $next_url ? '?next=' . urlencode($next_url) : '';
        header('Location: /home/' . $next_param);
        exit;
    }
}

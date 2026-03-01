<?php
/**
 * Biography Entry Point
 * Redirects to /app/bio/ if authenticated, otherwise to login
 */

require_once __DIR__ . '/../auth.php';

if (auth_is_logged_in()) {
    header('Location: /app/bio/');
} else {
    header('Location: /login.php?next=' . urlencode('/app/bio/'));
}
exit;

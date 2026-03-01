<?php
/**
 * Photos Entry Point
 * Redirects to /app/photos/ if authenticated, otherwise to login
 */

require_once __DIR__ . '/../auth.php';

if (auth_is_logged_in()) {
    header('Location: /app/photos/');
} else {
    header('Location: /home/?next=' . urlencode('/app/photos/'));
}
exit;

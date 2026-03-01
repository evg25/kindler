<?php
/**
 * Book Entry Point
 * Redirects to /app/book/ if authenticated, otherwise to login
 */

require_once __DIR__ . '/../auth.php';

if (auth_is_logged_in()) {
    header('Location: /app/book/');
} else {
    header('Location: /home/?next=' . urlencode('/app/book/'));
}
exit;

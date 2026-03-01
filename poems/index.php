<?php
/**
 * Poems Entry Point
 * Redirects to /app/poems/ if authenticated, otherwise to login
 */

require_once __DIR__ . '/../auth.php';

if (auth_is_logged_in()) {
    header('Location: /app/poems/');
} else {
    header('Location: /home/?next=' . urlencode('/app/poems/'));
}
exit;

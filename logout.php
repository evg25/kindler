<?php
/**
 * Logout Page
 * Destroys session and redirects to public landing
 */

require_once __DIR__ . '/auth.php';

auth_logout();

header('Location: /');
exit;

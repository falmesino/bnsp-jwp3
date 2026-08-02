<?php
/**
 * Handles user sign-out process
 * 
 * Usage:
 * - Access via index.php?page=sign-out
 * - Should be accessed via GET request (link click)
 * 
 * Process:
 * 1. Unset all session variables
 * 2. Delete the session cookie if it exists
 * 3. Destroy the session
 * 4. Redirect to sign-in page
 */

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to sign-in page
header('Location: index.php?page=sign-in');
exit;

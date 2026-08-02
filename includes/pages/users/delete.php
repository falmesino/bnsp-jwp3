<?php
/**
 * Handles user deletion and undo soft delete operations
 *
 * Usage:
 * - Soft delete (default): POST with id, csrf_token, and delete_type=soft
 * - Hard delete: POST with id, csrf_token, and delete_type=hard
 * - Undo soft delete: POST with id, csrf_token, and delete_type=undo
 *
 * Parameters:
 * - id (int): User ID to delete/undo
 * - csrf_token (string): CSRF token for security
 * - delete_type (string): 'soft', 'hard', or 'undo'
 *
 * Process:
 * 1. Validate request method (POST only)
 * 2. Validate CSRF token
 * 3. Validate and sanitize user ID
 * 4. Determine operation type (soft delete, hard delete, or undo)
 * 5. Execute appropriate operation
 * 6. Set flash message and redirect to users list
 */

// Initialize errors and messages
$errors = [];
$success = false;
$redirectUrl = 'index.php?page=users';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

// Check CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    $errors[] = 'Invalid CSRF token';
}

// Get and validate user ID
$userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    $errors[] = 'Invalid user ID';
}

// Determine delete type (soft by default)
$deleteType = trim($_POST['delete_type'] ?? 'soft');
if (!in_array($deleteType, ['soft', 'hard', 'undo'])) {
    $errors[] = 'Invalid operation type';
}

// If no errors, proceed to execute operation
if (empty($errors)) {
    try {
        // Check if user exists
        $checkStmt = $pdo->prepare("SELECT id, isDeleted FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = 'User not found';
        } else {
            if ($deleteType === 'soft') {
                if ($user['isDeleted']) {
                    $errors[] = 'User is already soft deleted';
                } else {
                    // Soft delete: set isDeleted to 1
                    $stmt = $pdo->prepare("UPDATE users SET isDeleted = 1 WHERE id = ?");
                    $stmt->execute([$userId]);
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                    }
                }
            } elseif ($deleteType === 'hard') {
                // Hard delete: remove from database
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() > 0) {
                    $success = true;
                } else {
                    $errors[] = 'Failed to delete user';
                }
            } elseif ($deleteType === 'undo') {
                if (!$user['isDeleted']) {
                    $errors[] = 'User is not soft deleted';
                } else {
                    // Undo soft delete: set isDeleted back to 0
                    $stmt = $pdo->prepare("UPDATE users SET isDeleted = 0 WHERE id = ?");
                    $stmt->execute([$userId]);
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Set flash messages and redirect
if ($success) {
    $messages = [
        'soft' => 'User soft deleted successfully',
        'hard' => 'User hard deleted successfully',
        'undo' => 'User restored successfully'
    ];
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => $messages[$deleteType]
    ];
} else {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => implode(', ', $errors)
    ];
}

header('Location: ' . $redirectUrl);
exit;

<?php
/**
 * Handles Payment Deletion and Restore Operations
 * 
 * Usage:
 * - Soft delete (default): POST with id, csrf_token, and delete_type=soft
 * - Hard delete: POST with id, csrf_token, and delete_type=hard
 * - Undo soft delete: POST with id, csrf_token, and delete_type=undo
 * 
 * Parameters:
 * - id (int): Payment ID to delete/undo
 * - csrf_token (string): CSRF token for security
 * - delete_type (string): 'soft', 'hard', or 'undo'
 * 
 * Process:
 * 1. Validate request method (POST only)
 * 2. Validate CSRF token
 * 3. Validate and sanitize payment ID
 * 4. Fetch payment to get bill_id for redirect
 * 5. Determine operation type
 * 6. Execute appropriate operation
 * 7. Set flash message and redirect to bill edit page (or payments list)
 */

// Configurations
$module_name = 'payments';
$bill_module = 'bills';
$table_name = 'payments';

// Initialize errors and messages
$errors = [];
$success = false;
$billId = null;
$redirectUrl = 'index.php?page=' . $bill_module; // Default redirect

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

// Get and validate Payment ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    $errors[] = 'Invalid Payment ID';
}

// Determine delete type (soft by default)
$deleteType = trim($_POST['delete_type'] ?? 'soft');
if (!in_array($deleteType, ['soft', 'hard', 'undo'])) {
    $errors[] = 'Invalid operation type';
}

// If no errors, proceed to fetch payment and execute operation
if (empty($errors)) {
    try {
        // First, fetch the payment to get bill_id and check existence
        $checkStmt = $pdo->prepare("SELECT id, bill_id, isDeleted FROM " . $table_name . " WHERE id = ?");
        $checkStmt->execute([$id]);
        $payment = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            $errors[] = 'Payment not found';
        } else {
            $billId = $payment['bill_id'];
            // Update redirect URL to go back to bill edit page
            $redirectUrl = 'index.php?page=' . $bill_module . '/edit&id=' . $billId;
            
            if ($deleteType === 'soft') {
                if ($payment['isDeleted']) {
                    $errors[] = 'Payment is already soft deleted';
                } else {
                    // Soft delete: set isDeleted to 1
                    $stmt = $pdo->prepare("UPDATE " . $table_name . " SET isDeleted = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                    }
                }
            } elseif ($deleteType === 'hard') {
                // Hard delete: remove from database
                $stmt = $pdo->prepare("DELETE FROM " . $table_name . " WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    $success = true;
                } else {
                    $errors[] = 'Failed to delete payment';
                }
            } elseif ($deleteType === 'undo') {
                if (!$payment['isDeleted']) {
                    $errors[] = 'Payment is not soft deleted';
                } else {
                    // Undo soft delete: set isDeleted back to 0
                    $stmt = $pdo->prepare("UPDATE " . $table_name . " SET isDeleted = 0 WHERE id = ?");
                    $stmt->execute([$id]);
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
        'soft' => 'Payment deleted successfully',
        'hard' => 'Payment permanently deleted successfully',
        'undo' => 'Payment restored successfully'
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

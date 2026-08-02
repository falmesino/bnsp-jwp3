<?php
/**
 * Handles Prescription Deletion and Restore Operations
 * 
 * Usage:
 * - Soft delete (default): POST with id, csrf_token, and delete_type=soft
 * - Hard delete: POST with id, csrf_token, and delete_type=hard
 * - Undo soft delete: POST with id, csrf_token, and delete_type=undo
 * 
 * Parameters:
 * - id (int): Prescription ID to delete/undo
 * - csrf_token (string): CSRF token for security
 * - delete_type (string): 'soft', 'hard', or 'undo'
 * 
 * Process:
 * 1. Validate request method (POST only)
 * 2. Validate CSRF token
 * 3. Validate and sanitize prescription ID
 * 4. Fetch prescription to get medical_record_id for redirect
 * 5. Determine operation type (soft delete, hard delete, or undo)
 * 6. Execute appropriate operation
 * 7. Set flash message and redirect to medical record edit page
 */

// Configurations
$module_name = 'prescriptions';
$parent_module = 'medical-records';
$table_name = 'prescriptions';

// Initialize errors and messages
$errors = [];
$success = false;
$medicalRecordId = null;

// Default redirect (will be updated with medical_record_id)
$redirectUrl = 'index.php?page=' . $parent_module;

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

// Get and validate Prescription ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    $errors[] = 'Invalid Prescription ID';
}

// Determine delete type (soft by default)
$deleteType = trim($_POST['delete_type'] ?? 'soft');
if (!in_array($deleteType, ['soft', 'hard', 'undo'])) {
    $errors[] = 'Invalid operation type';
}

// If no errors, proceed to fetch prescription and execute operation
if (empty($errors)) {
    try {
        // First, fetch the prescription to get medical_record_id and check existence
        $checkStmt = $pdo->prepare("SELECT id, medical_record_id, isDeleted FROM " . $table_name . " WHERE id = ?");
        $checkStmt->execute([$id]);
        $prescription = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
            $errors[] = 'Prescription not found';
        } else {
            $medicalRecordId = $prescription['medical_record_id'];
            // Update redirect URL to go back to medical record edit page
            $redirectUrl = 'index.php?page=' . $parent_module . '/edit&id=' . $medicalRecordId;
            
            if ($deleteType === 'soft') {
                if ($prescription['isDeleted']) {
                    $errors[] = 'Prescription is already soft deleted';
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
                    $errors[] = 'Failed to delete prescription';
                }
            } elseif ($deleteType === 'undo') {
                if (!$prescription['isDeleted']) {
                    $errors[] = 'Prescription is not soft deleted';
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
        'soft' => 'Prescription soft deleted successfully',
        'hard' => 'Prescription permanently deleted successfully',
        'undo' => 'Prescription restored successfully'
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

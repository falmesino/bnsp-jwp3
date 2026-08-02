<?php
/**
 * Edit Payment Page
 * 
 * Usage:
 * - Access via index.php?page=payments/edit&id={payment_id}
 * - Allows editing an existing payment
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Validate and fetch payment by ID
 * 3. Fetch related bill details
 * 4. Handle POST request to update payment
 * 5. Redirect back to bill edit page
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'payments';
$bill_module = 'bills';

// Initialize variables
$errors = [];
$payment = null;
$bill = null;

// Get and validate payment ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Invalid payment ID.'
    ];
    header('Location: index.php?page=' . $bill_module);
    exit;
}

// Fetch the payment
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            bill_id,
            amount,
            method,
            date,
            isDeleted
        FROM payments
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Payment not found.'
        ];
        header('Location: index.php?page=' . $bill_module);
        exit;
    }
    
    if ($payment['isDeleted']) {
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'text' => 'Cannot edit a deleted payment. Please restore it first.'
        ];
        header('Location: index.php?page=' . $bill_module);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Database error: ' . $e->getMessage()
    ];
    header('Location: index.php?page=' . $bill_module);
    exit;
}

// Fetch related bill details
try {
    $stmtBill = $pdo->prepare("
        SELECT 
            b.id,
            b.total,
            b.bill_status,
            p.name AS patient_name
        FROM bills b
        LEFT JOIN medical_records mr ON b.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        WHERE b.id = ?
    ");
    $stmtBill->execute([$payment['bill_id']]);
    $bill = $stmtBill->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $billError = 'Failed to fetch bill details: ' . $e->getMessage();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        // Get and sanitize input
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $method = trim($_POST['method'] ?? '');
        $date = trim($_POST['date'] ?? '');
        
        // Validate input
        if (!$amount || $amount <= 0) {
            $errors[] = 'Please enter a valid payment amount (must be greater than 0).';
        }
        
        // If no errors, proceed to update
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET 
                        amount = ?,
                        method = ?,
                        date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $amount,
                    $method ?: null,
                    $date ?: null,
                    $id
                ]);
                
                // Set success flash message
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Payment updated successfully.'
                ];
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Redirect back to bill edit page
                header('Location: index.php?page=' . $bill_module . '/edit&id=' . $payment['bill_id']);
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <h3>Edit Payment #<?php echo htmlspecialchars($payment['id']); ?></h3>
        <a href="index.php?page=<?php echo $bill_module; ?>/edit&id=<?php echo htmlspecialchars($payment['bill_id']); ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ri-arrow-left-line"></i> Back to Bill
        </a>
      </div>
    </div>
  </div>

  <!-- Bill Info Card -->
  <?php if ($bill): ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Related Bill Information</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <strong>Bill #:</strong> <?php echo htmlspecialchars($bill['id']); ?>
          </div>
          <div class="col-md-4">
            <strong>Patient:</strong> <?php echo htmlspecialchars($bill['patient_name'] ?? 'Unknown'); ?>
          </div>
          <div class="col-md-4">
            <strong>Bill Total:</strong> Rp <?php echo number_format((float)$bill['total'], 0, ',', '.'); ?>
          </div>
        </div>
      </div>
    </div>
  <?php elseif (isset($billError)): ?>
    <div class="alert alert-warning mb-4" role="alert">
      <?php echo htmlspecialchars($billError); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="amount" class="form-label">Payment Amount (Rp) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($payment['amount']); ?>" min="100" step="100" required>
                <div class="form-text">Amount being paid</div>
              </div>
              
              <div class="col-md-6">
                <label for="date" class="form-label">Payment Date & Time</label>
                <input type="datetime-local" class="form-control" id="date" name="date" value="<?php echo $payment['date'] ? date('Y-m-d\TH:i', strtotime($payment['date'])) : date('Y-m-d\TH:i'); ?>">
                <div class="form-text">Date and time of payment</div>
              </div>
            </div>
            
            <div class="mb-4">
              <label for="method" class="form-label">Payment Method</label>
              <select class="form-select" id="method" name="method">
                <option value="">-- Select Method --</option>
                <option value="Cash" <?php echo $payment['method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="Credit Card" <?php echo $payment['method'] === 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                <option value="Debit Card" <?php echo $payment['method'] === 'Debit Card' ? 'selected' : ''; ?>>Debit Card</option>
                <option value="Bank Transfer" <?php echo $payment['method'] === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="E-Wallet" <?php echo $payment['method'] === 'E-Wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                <option value="Insurance" <?php echo $payment['method'] === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
              </select>
              <div class="form-text">Method of payment</div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Update Payment
              </button>
              <a href="index.php?page=<?php echo $bill_module; ?>/edit&id=<?php echo htmlspecialchars($payment['bill_id']); ?>" class="btn btn-outline-secondary btn-sm">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
/**
 * Add New Payment Page
 * 
 * Usage:
 * - Access via index.php?page=payments/add&bill_id={bill_id}
 * - Allows adding a new payment to a specific bill
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Validate bill_id parameter
 * 3. Check if bill exists and is not deleted
 * 4. Fetch bill details for reference
 * 5. Handle POST request to add payment
 * 6. Redirect back to bill edit page
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'payments';
$bill_module = 'bills';

// Initialize variables
$errors = [];
$bill = null;
$amount = 0;
$method = '';
$date = date('Y-m-d H:i:s');

// Get and validate bill_id
$billId = filter_input(INPUT_GET, 'bill_id', FILTER_VALIDATE_INT);
if (!$billId || $billId <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Invalid bill ID.'
    ];
    header('Location: index.php?page=' . $bill_module);
    exit;
}

// Fetch the bill
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.medical_record_id,
            b.total,
            b.bill_status,
            b.isDeleted,
            p.name AS patient_name
        FROM bills b
        LEFT JOIN medical_records mr ON b.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Bill not found.'
        ];
        header('Location: index.php?page=' . $bill_module);
        exit;
    }
    
    if ($bill['isDeleted']) {
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'text' => 'Cannot add payment to a deleted bill.'
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

// Calculate total payments and remaining
try {
    $stmtPayments = $pdo->prepare("
        SELECT SUM(amount) AS total_payments
        FROM payments
        WHERE bill_id = ? AND isDeleted = 0
    ");
    $stmtPayments->execute([$billId]);
    $paymentsResult = $stmtPayments->fetch(PDO::FETCH_ASSOC);
    $totalPayments = (float)$paymentsResult['total_payments'] ?? 0;
    $remaining = (float)$bill['total'] - $totalPayments;
} catch (PDOException $e) {
    $totalPayments = 0;
    $remaining = (float)$bill['total'];
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
        
        // If no errors, proceed to insert
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO payments 
                    (bill_id, amount, method, date, status)
                    VALUES 
                    (?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $billId,
                    $amount,
                    $method ?: null,
                    $date ?: null
                ]);
                
                if ($stmt->rowCount() > 0) {
                    // Set success flash message
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'text' => 'Payment added successfully.'
                    ];
                    
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Redirect back to bill edit page
                    header('Location: index.php?page=' . $bill_module . '/edit&id=' . $billId);
                    exit;
                } else {
                    $errors[] = 'Failed to add payment. Please try again.';
                }
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
        <h3>Add Payment to Bill #<?php echo htmlspecialchars($bill['id']); ?></h3>
        <a href="index.php?page=<?php echo $bill_module; ?>/edit&id=<?php echo htmlspecialchars($billId); ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ri-arrow-left-line"></i> Back to Bill
        </a>
      </div>
    </div>
  </div>

  <!-- Bill Summary Card -->
  <div class="card mb-4">
    <div class="card-header bg-light">
      <h5 class="mb-0">Bill Summary</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <strong>Patient:</strong> <?php echo htmlspecialchars($bill['patient_name'] ?? 'Unknown'); ?>
        </div>
        <div class="col-md-4">
          <strong>Bill Total:</strong> Rp <?php echo number_format((float)$bill['total'], 0, ',', '.'); ?>
        </div>
        <div class="col-md-4">
          <strong>Status:</strong> 
          <?php if ($bill['bill_status'] === 'paid'): ?>
            <span class="badge bg-success">Paid</span>
          <?php else: ?>
            <span class="badge bg-warning">Pending</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-md-4">
          <strong>Total Paid:</strong> Rp <?php echo number_format($totalPayments, 0, ',', '.'); ?>
        </div>
        <div class="col-md-4">
          <strong>Remaining:</strong> 
          <span class="<?php echo $remaining > 0 ? 'text-danger' : 'text-success'; ?>">
            Rp <?php echo number_format($remaining, 0, ',', '.'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>

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
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/add&bill_id=<?php echo htmlspecialchars($billId); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="amount" class="form-label">Payment Amount (Rp) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($remaining > 0 ? $remaining : ''); ?>" min="100" step="100" required>
                <div class="form-text">Amount being paid (suggested: remaining amount)</div>
              </div>
              
              <div class="col-md-6">
                <label for="date" class="form-label">Payment Date & Time</label>
                <input type="datetime-local" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                <div class="form-text">Date and time of payment</div>
              </div>
            </div>
            
            <div class="mb-4">
              <label for="method" class="form-label">Payment Method</label>
              <select class="form-select" id="method" name="method">
                <option value="">-- Select Method --</option>
                <option value="Cash" <?php echo $method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="Credit Card" <?php echo $method === 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                <option value="Debit Card" <?php echo $method === 'Debit Card' ? 'selected' : ''; ?>>Debit Card</option>
                <option value="Bank Transfer" <?php echo $method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="E-Wallet" <?php echo $method === 'E-Wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                <option value="Insurance" <?php echo $method === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
              </select>
              <div class="form-text">Method of payment</div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Record Payment
              </button>
              <a href="index.php?page=<?php echo $bill_module; ?>/edit&id=<?php echo htmlspecialchars($billId); ?>" class="btn btn-outline-secondary btn-sm">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

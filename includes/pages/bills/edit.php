<?php
/**
 * Edit Bill Page
 * 
 * Usage:
 * - Access via index.php?page=bills/edit&id={bill_id}
 * - Allows editing an existing bill and viewing related payments
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Validate and fetch the bill by ID
 * 3. Fetch medical record details for reference
 * 4. Fetch related payments for this bill
 * 5. Handle POST request to update bill
 * 6. Display pre-populated form with existing data and payments list
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'bills';
$payment_module = 'payments';

// Initialize variables
$errors = [];
$bill = null;
$payments = [];
$calculatedTotal = 0;

// Get and validate bill ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Invalid bill ID.'
    ];
    header('Location: index.php?page=' . $module_name);
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
            b.createdAt,
            b.updatedAt,
            b.isDeleted,
            b.status,
            p.name AS patient_name,
            mr.visit_date
        FROM bills b
        LEFT JOIN medical_records mr ON b.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Bill not found.'
        ];
        header('Location: index.php?page=' . $module_name);
        exit;
    }
    
    if ($bill['isDeleted']) {
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'text' => 'Cannot edit a deleted bill. Please restore it first.'
        ];
        header('Location: index.php?page=' . $module_name);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Database error: ' . $e->getMessage()
    ];
    header('Location: index.php?page=' . $module_name);
    exit;
}

// Fetch related payments
try {
    $stmtPayments = $pdo->prepare("
        SELECT 
            id,
            amount,
            method,
            date,
            createdAt,
            isDeleted
        FROM payments
        WHERE bill_id = ?
        ORDER BY date DESC, createdAt DESC
    ");
    $stmtPayments->execute([$id]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $paymentError = 'Failed to fetch payments: ' . $e->getMessage();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        // Get and sanitize input
        $total = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_FLOAT);
        $billStatus = trim($_POST['bill_status'] ?? 'pending');
        $useCalculatedTotal = isset($_POST['use_calculated_total']) ? true : false;
        
        // Validate input
        if (!in_array($billStatus, ['pending', 'paid'])) {
            $errors[] = 'Invalid bill status.';
        }
        
        // Calculate total from prescriptions if needed
        if ($useCalculatedTotal) {
            try {
                $stmtPrescriptions = $pdo->prepare("
                    SELECT 
                        SUM(p.qty * m.price) AS calculated_total
                    FROM prescriptions p
                    LEFT JOIN medications m ON p.medication_id = m.id
                    WHERE p.medical_record_id = ?
                        AND p.isDeleted = 0
                        AND p.status = 1
                ");
                $stmtPrescriptions->execute([$bill['medical_record_id']]);
                $calcResult = $stmtPrescriptions->fetch(PDO::FETCH_ASSOC);
                $calculatedTotal = (float)$calcResult['calculated_total'] ?? 0;
                $total = $calculatedTotal;
            } catch (PDOException $e) {
                $errors[] = 'Failed to calculate total from prescriptions: ' . $e->getMessage();
            }
        } else {
            if ($total === false || $total === null) {
                $errors[] = 'Please enter a valid total amount.';
            }
        }
        
        // Ensure total is non-negative
        if ($total < 0) {
            $total = 0;
        }
        
        // If no errors, proceed to update
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE bills 
                    SET 
                        total = ?,
                        bill_status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $total,
                    $billStatus,
                    $id
                ]);
                
                // Set success flash message
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Bill updated successfully.'
                ];
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Redirect to list page
                header('Location: index.php?page=' . $module_name);
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
        <h3>Edit Bill #<?php echo htmlspecialchars($bill['id']); ?></h3>
        <a href="index.php?page=<?php echo $module_name; ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ri-arrow-left-line"></i> Back to Bills List
        </a>
      </div>
    </div>
  </div>

  <!-- Bill Info Card -->
  <div class="card mb-4">
    <div class="card-header bg-light">
      <h5 class="mb-0">Bill Information</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3">
          <strong>Patient:</strong>
        </div>
        <div class="col-md-3">
          <?php echo htmlspecialchars($bill['patient_name'] ?? 'Unknown'); ?>
        </div>
        <div class="col-md-3">
          <strong>Visit Date:</strong>
        </div>
        <div class="col-md-3">
          <?php echo htmlspecialchars($bill['visit_date'] ?? '-'); ?>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-md-3">
          <strong>Created At:</strong>
        </div>
        <div class="col-md-3">
          <?php echo htmlspecialchars($bill['createdAt'] ?? '-'); ?>
        </div>
        <div class="col-md-3">
          <strong>Last Updated:</strong>
        </div>
        <div class="col-md-3">
          <?php echo htmlspecialchars($bill['updatedAt'] ?? '-'); ?>
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

  <!-- Edit Bill Form -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Update Bill</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($id); ?>" id="billEditForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="use_calculated_total" name="use_calculated_total">
              <label class="form-check-label" for="use_calculated_total">
                Recalculate total from prescriptions (will overwrite current total)
              </label>
            </div>
            <label for="total" class="form-label">Total Amount (Rp)</label>
            <input type="number" class="form-control" id="total" name="total" value="<?php echo htmlspecialchars($bill['total']); ?>" min="0" step="100">
            <div class="form-text">Current total: Rp <?php echo number_format((float)$bill['total'], 0, ',', '.'); ?></div>
          </div>
          
          <div class="col-md-6">
            <label for="bill_status" class="form-label">Payment Status</label>
            <select class="form-select" id="bill_status" name="bill_status">
              <option value="pending" <?php echo $bill['bill_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="paid" <?php echo $bill['bill_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
            <div class="form-text">Update the payment status of this bill</div>
          </div>
        </div>
        
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-dark btn-sm">
            <i class="ri-save-line"></i> Update Bill
          </button>
          <a href="index.php?page=<?php echo $module_name; ?>" class="btn btn-outline-secondary btn-sm">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Payments Section -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Payments</h5>
      <a href="index.php?page=<?php echo $payment_module; ?>/add&bill_id=<?php echo htmlspecialchars($id); ?>" class="btn btn-outline-success btn-sm">
        <i class="ri-add-line"></i> Add Payment
      </a>
    </div>
    <div class="card-body p-0">
      <?php if (isset($paymentError)): ?>
        <div class="alert alert-warning m-3" role="alert">
          <?php echo htmlspecialchars($paymentError); ?>
        </div>
      <?php elseif (empty($payments)): ?>
        <div class="text-center py-4 text-muted">
          No payments found for this bill.
        </div>
      <?php else: ?>
        <?php 
            // Calculate total payments
            $totalPayments = 0;
            foreach ($payments as $payment) {
                if (!$payment['isDeleted']) {
                    $totalPayments += (float)$payment['amount'];
                }
            }
            $remaining = (float)$bill['total'] - $totalPayments;
        ?>
        <div class="p-3 bg-light border-bottom">
          <div class="row">
            <div class="col-md-4">
              <strong>Bill Total:</strong> Rp <?php echo number_format((float)$bill['total'], 0, ',', '.'); ?>
            </div>
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
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" style="width: 50px;">ID</th>
                <th scope="col">Amount</th>
                <th scope="col">Method</th>
                <th scope="col">Date</th>
                <th scope="col">Status</th>
                <th scope="col" style="width: 200px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $payment): ?>
                <tr class="<?php echo $payment['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                  <th scope="row"><?php echo htmlspecialchars($payment['id']); ?></th>
                  <td>Rp <?php echo number_format((float)$payment['amount'], 0, ',', '.'); ?></td>
                  <td><?php echo htmlspecialchars($payment['method'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($payment['date'] ?? $payment['createdAt'] ?? '-'); ?></td>
                  <td>
                    <?php if ($payment['isDeleted']): ?>
                      <span class="badge bg-secondary">Deleted</span>
                    <?php else: ?>
                      <span class="badge bg-success">Valid</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <a
                        href="index.php?page=<?php echo $payment_module; ?>/edit&id=<?php echo htmlspecialchars($payment['id']); ?>"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        title="Edit"
                        <?php echo $payment['isDeleted'] ? 'disabled' : ''; ?>
                      >
                        <i class="ri-edit-line"></i>
                      </a>
                      <?php if (!$payment['isDeleted']): ?>
                        <form
                          method="POST"
                          action="index.php?page=<?php echo $payment_module; ?>/delete"
                          style="display: inline;"
                          onsubmit="return confirm('Are you sure you want to delete this payment?');"
                        >
                          <input type="hidden" name="id" value="<?php echo htmlspecialchars($payment['id']); ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="delete_type" value="soft">
                          <button
                            type="submit"
                            class="btn btn-outline-danger btn-sm"
                            title="Delete"
                          >
                            <i class="ri-delete-bin-line"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

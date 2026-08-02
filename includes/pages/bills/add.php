<?php
/**
 * Add New Bill Page
 * 
 * Usage:
 * - Access via index.php?page=bills/add
 * - Allows creating a new bill for a medical record
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Fetch medical records that don't already have a bill (or allow multiple bills? Probably one bill per medical record for simplicity)
 * 3. Handle POST request:
 *    a. Validate CSRF token
 *    b. Sanitize and validate input
 *    c. Calculate total from prescriptions if not manually set
 *    d. Insert new bill into database
 *    e. Set flash message and redirect to list
 * 4. Display form with medical record dropdown, total calculation, and bill status
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'bills';

// Initialize variables
$errors = [];
$medicalRecordId = '';
$total = 0;
$billStatus = 'pending';
$calculatedTotal = 0;

// Fetch medical records (for simplicity, let's allow all active medical records, even if they have a bill - maybe allow multiple bills?)
// Actually, let's allow all active, non-deleted medical records
try {
    $stmtMr = $pdo->prepare("
        SELECT 
            mr.id,
            mr.patient_id,
            mr.visit_date,
            p.name AS patient_name
        FROM medical_records mr
        LEFT JOIN patients p ON mr.patient_id = p.id
        WHERE mr.isDeleted = 0
        ORDER BY mr.visit_date DESC
    ");
    $stmtMr->execute();
    $medicalRecords = $stmtMr->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Failed to fetch medical records: ' . $e->getMessage();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        // Get and sanitize input
        $medicalRecordId = filter_input(INPUT_POST, 'medical_record_id', FILTER_VALIDATE_INT);
        $total = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_FLOAT);
        $billStatus = trim($_POST['bill_status'] ?? 'pending');
        $useCalculatedTotal = isset($_POST['use_calculated_total']) ? true : false;
        
        // Validate input
        if (!$medicalRecordId || $medicalRecordId <= 0) {
            $errors[] = 'Please select a valid medical record.';
        }
        if (!in_array($billStatus, ['pending', 'paid'])) {
            $errors[] = 'Invalid bill status.';
        }
        
        // Calculate total from prescriptions if needed
        if ($useCalculatedTotal || $total === false || $total === null) {
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
                $stmtPrescriptions->execute([$medicalRecordId]);
                $calcResult = $stmtPrescriptions->fetch(PDO::FETCH_ASSOC);
                $calculatedTotal = (float)$calcResult['calculated_total'] ?? 0;
                $total = $calculatedTotal;
            } catch (PDOException $e) {
                $errors[] = 'Failed to calculate total from prescriptions: ' . $e->getMessage();
            }
        }
        
        // Ensure total is non-negative
        if ($total < 0) {
            $total = 0;
        }
        
        // If no errors, proceed to insert
        if (empty($errors)) {
            try {
                // Check if medical record exists
                $checkStmt = $pdo->prepare("SELECT id FROM medical_records WHERE id = ? AND isDeleted = 0");
                $checkStmt->execute([$medicalRecordId]);
                $mrExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$mrExists) {
                    $errors[] = 'Selected medical record does not exist or is deleted.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO bills 
                        (medical_record_id, total, bill_status, status)
                        VALUES 
                        (?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $medicalRecordId,
                        $total,
                        $billStatus
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Set success flash message
                        $_SESSION['flash_message'] = [
                            'type' => 'success',
                            'text' => 'Bill created successfully.'
                        ];
                        
                        // Regenerate CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        
                        // Redirect to list page
                        header('Location: index.php?page=' . $module_name);
                        exit;
                    } else {
                        $errors[] = 'Failed to create bill. Please try again.';
                    }
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
        <h3>Create New Bill</h3>
        <a href="index.php?page=<?php echo $module_name; ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ri-arrow-left-line"></i> Back to Bills List
        </a>
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
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/add" id="billForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="mb-3">
              <label for="medical_record_id" class="form-label">Medical Record <span class="text-danger">*</span></label>
              <select class="form-select" id="medical_record_id" name="medical_record_id" required>
                <option value="">-- Select Medical Record --</option>
                <?php foreach ($medicalRecords as $mr): ?>
                  <option value="<?php echo htmlspecialchars($mr['id']); ?>" <?php echo $medicalRecordId == $mr['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars('MR #' . $mr['id'] . ' - ' . $mr['patient_name'] . ' (' . $mr['visit_date'] . ')'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Select the medical record to create a bill for</div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" id="use_calculated_total" name="use_calculated_total" checked>
                  <label class="form-check-label" for="use_calculated_total">
                    Auto-calculate total from prescriptions
                  </label>
                </div>
                <label for="total" class="form-label">Total Amount (Rp)</label>
                <input type="number" class="form-control" id="total" name="total" value="<?php echo htmlspecialchars($total); ?>" min="0" step="100" disabled>
                <div class="form-text">Total bill amount (auto-calculated if checkbox is checked)</div>
              </div>
              
              <div class="col-md-6">
                <label for="bill_status" class="form-label">Payment Status</label>
                <select class="form-select" id="bill_status" name="bill_status">
                  <option value="pending" <?php echo $billStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="paid" <?php echo $billStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
                <div class="form-text">Current payment status of the bill</div>
              </div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Create Bill
              </button>
              <a href="index.php?page=<?php echo $module_name; ?>" class="btn btn-outline-secondary btn-sm">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // JavaScript to enable/disable total input based on checkbox
  document.addEventListener('DOMContentLoaded', function() {
      const checkbox = document.getElementById('use_calculated_total');
      const totalInput = document.getElementById('total');
      const mrSelect = document.getElementById('medical_record_id');
      
      function toggleTotalInput() {
          if (checkbox.checked) {
              totalInput.disabled = true;
              // If medical record is selected, we could calculate here via JS, but let's let PHP handle it on submit for simplicity
          } else {
              totalInput.disabled = false;
          }
      }
      
      checkbox.addEventListener('change', toggleTotalInput);
      toggleTotalInput(); // Initial call
  });
</script>

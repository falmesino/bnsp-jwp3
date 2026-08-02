<?php
/**
 * Add Prescription to Medical Record Page
 * 
 * Usage:
 * - Access via index.php?page=prescriptions/add&medical_record_id={id}
 * - Allows adding a new prescription item to an existing medical record
 * 
 * Process:
 * 1. Validate medical_record_id parameter
 * 2. Check if medical record exists and is not deleted
 * 3. Fetch active medications for dropdown
 * 4. Handle POST request to add prescription
 * 5. Redirect back to medical record edit page
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'prescriptions';
$parent_module = 'medical-records';

// Initialize variables
$errors = [];
$medicalRecord = null;
$medicationId = '';
$qty = 1;
$dosage = '';

// Get and validate medical_record_id
$medicalRecordId = filter_input(INPUT_GET, 'medical_record_id', FILTER_VALIDATE_INT);
if (!$medicalRecordId || $medicalRecordId <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Invalid medical record ID.'
    ];
    header('Location: index.php?page=' . $parent_module);
    exit;
}

// Fetch the medical record
try {
    $stmt = $pdo->prepare("
        SELECT id, patient_id, user_id, visit_date, isDeleted
        FROM medical_records
        WHERE id = ?
    ");
    $stmt->execute([$medicalRecordId]);
    $medicalRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicalRecord) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Medical record not found.'
        ];
        header('Location: index.php?page=' . $parent_module);
        exit;
    }
    
    if ($medicalRecord['isDeleted']) {
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'text' => 'Cannot add prescription to a deleted medical record.'
        ];
        header('Location: index.php?page=' . $parent_module);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Database error: ' . $e->getMessage()
    ];
    header('Location: index.php?page=' . $parent_module);
    exit;
}

// Fetch active medications
try {
    $stmtMedications = $pdo->prepare("
        SELECT id, name, stock, price 
        FROM medications 
        WHERE isDeleted = 0 AND status = 1 
        ORDER BY name ASC
    ");
    $stmtMedications->execute();
    $medications = $stmtMedications->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Failed to fetch medications: ' . $e->getMessage();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        // Get and sanitize input
        $medicationId = filter_input(INPUT_POST, 'medication_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_INT);
        $dosage = trim($_POST['dosage'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Validate input
        if (!$medicationId || $medicationId <= 0) {
            $errors[] = 'Please select a valid medication.';
        }
        if (!$qty || $qty <= 0) {
            $errors[] = 'Please enter a valid quantity (must be at least 1).';
        }
        
        // If no errors, proceed to insert
        if (empty($errors)) {
            try {
                // Check if medication exists
                $stmtCheckMed = $pdo->prepare("SELECT id FROM medications WHERE id = ? AND isDeleted = 0");
                $stmtCheckMed->execute([$medicationId]);
                $medicationExists = $stmtCheckMed->fetch(PDO::FETCH_ASSOC);
                
                if (!$medicationExists) {
                    $errors[] = 'Selected medication does not exist.';
                } else {
                    // Check if this medication is already in this medical record (optional, maybe allow duplicates? Or not?)
                    // For now, allow duplicates (e.g., same medication but different dosage? Or maybe not. Let's allow for flexibility)
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO prescriptions 
                        (medical_record_id, medication_id, qty, dosage, status)
                        VALUES 
                        (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $medicalRecordId,
                        $medicationId,
                        $qty,
                        $dosage ?: null,
                        $status
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Set success flash message
                        $_SESSION['flash_message'] = [
                            'type' => 'success',
                            'text' => 'Prescription added successfully.'
                        ];
                        
                        // Regenerate CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        
                        // Redirect back to medical record edit page
                        header('Location: index.php?page=' . $parent_module . '/edit&id=' . $medicalRecordId);
                        exit;
                    } else {
                        $errors[] = 'Failed to add prescription. Please try again.';
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
        <h3>Add Prescription to Medical Record #<?php echo htmlspecialchars($medicalRecord['id']); ?></h3>
        <a href="index.php?page=<?php echo $parent_module; ?>/edit&id=<?php echo htmlspecialchars($medicalRecordId); ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ri-arrow-left-line"></i> Back to Medical Record
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
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/add&medical_record_id=<?php echo htmlspecialchars($medicalRecordId); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="medication_id" class="form-label">Medication <span class="text-danger">*</span></label>
                <select class="form-select" id="medication_id" name="medication_id" required>
                  <option value="">-- Select Medication --</option>
                  <?php foreach ($medications as $medication): ?>
                    <option value="<?php echo htmlspecialchars($medication['id']); ?>" <?php echo $medicationId == $medication['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($medication['name']); ?> 
                      (Stock: <?php echo htmlspecialchars($medication['stock']); ?>, 
                      Price: Rp <?php echo number_format((float)$medication['price'], 0, ',', '.'); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Select the medication to prescribe</div>
              </div>
              
              <div class="col-md-4">
                <label for="qty" class="form-label">Quantity <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="qty" name="qty" value="<?php echo htmlspecialchars($qty); ?>" min="1" required>
                <div class="form-text">Number of units to prescribe</div>
              </div>
              
              <div class="col-md-2 d-flex align-items-end">
                <div class="mb-3 form-check">
                  <input type="checkbox" class="form-check-input" id="status" name="status" value="1" checked>
                  <label class="form-check-label" for="status">Active</label>
                </div>
              </div>
            </div>
            
            <div class="mb-4">
              <label for="dosage" class="form-label">Dosage Instructions</label>
              <textarea class="form-control" id="dosage" name="dosage" rows="2"><?php echo htmlspecialchars($dosage); ?></textarea>
              <div class="form-text">How the medication should be taken (e.g., "1 tablet 3x daily after meals")</div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Add Prescription
              </button>
              <a href="index.php?page=<?php echo $parent_module; ?>/edit&id=<?php echo htmlspecialchars($medicalRecordId); ?>" class="btn btn-outline-secondary btn-sm">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

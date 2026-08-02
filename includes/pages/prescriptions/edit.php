<?php
/**
 * Edit Prescription Page
 * 
 * Usage:
 * - Access via index.php?page=prescriptions/edit&id={prescription_id}
 * - Allows editing an existing prescription item
 * 
 * Process:
 * 1. Validate prescription ID parameter
 * 2. Check if prescription exists and is not deleted
 * 3. Fetch related medical record info
 * 4. Fetch active medications for dropdown
 * 5. Handle POST request to update prescription
 * 6. Redirect back to medical record edit page
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'prescriptions';
$parent_module = 'medical-records';

// Initialize variables
$errors = [];
$prescription = null;
$medicalRecordId = null;

// Get and validate prescription ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Invalid prescription ID.'
    ];
    header('Location: index.php?page=' . $parent_module);
    exit;
}

// Fetch the prescription
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.medical_record_id, 
            p.medication_id, 
            p.qty, 
            p.dosage, 
            p.status, 
            p.isDeleted,
            m.name AS medication_name
        FROM prescriptions p
        LEFT JOIN medications m ON p.medication_id = m.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prescription) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Prescription not found.'
        ];
        header('Location: index.php?page=' . $parent_module);
        exit;
    }
    
    if ($prescription['isDeleted']) {
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'text' => 'Cannot edit a deleted prescription. Please restore it first.'
        ];
        header('Location: index.php?page=' . $parent_module);
        exit;
    }
    
    $medicalRecordId = $prescription['medical_record_id'];
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
        
        // If no errors, proceed to update
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE prescriptions 
                    SET 
                        medication_id = ?,
                        qty = ?,
                        dosage = ?,
                        status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $medicationId,
                    $qty,
                    $dosage ?: null,
                    $status,
                    $id
                ]);
                
                // Set success flash message
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Prescription updated successfully.'
                ];
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Redirect back to medical record edit page
                header('Location: index.php?page=' . $parent_module . '/edit&id=' . $medicalRecordId);
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
        <h3>Edit Prescription #<?php echo htmlspecialchars($prescription['id']); ?></h3>
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
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="medication_id" class="form-label">Medication <span class="text-danger">*</span></label>
                <select class="form-select" id="medication_id" name="medication_id" required>
                  <option value="">-- Select Medication --</option>
                  <?php foreach ($medications as $medication): ?>
                    <option value="<?php echo htmlspecialchars($medication['id']); ?>" <?php echo $prescription['medication_id'] == $medication['id'] ? 'selected' : ''; ?>>
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
                <input type="number" class="form-control" id="qty" name="qty" value="<?php echo htmlspecialchars($prescription['qty']); ?>" min="1" required>
                <div class="form-text">Number of units to prescribe</div>
              </div>
              
              <div class="col-md-2 d-flex align-items-end">
                <div class="mb-3 form-check">
                  <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo $prescription['status'] ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="status">Active</label>
                </div>
              </div>
            </div>
            
            <div class="mb-4">
              <label for="dosage" class="form-label">Dosage Instructions</label>
              <textarea class="form-control" id="dosage" name="dosage" rows="2"><?php echo htmlspecialchars($prescription['dosage'] ?? ''); ?></textarea>
              <div class="form-text">How the medication should be taken (e.g., "1 tablet 3x daily after meals")</div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Update Prescription
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

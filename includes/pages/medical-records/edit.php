<?php
/**
 * Edit Medical Record Page
 * 
 * Usage:
 * - Access via index.php?page=medical-records/edit&id={record_id}
 * - Allows updating an existing medical record
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Validate and fetch the medical record by ID
 * 3. Fetch active patients and doctors for dropdowns
 * 4. Fetch existing prescriptions for this medical record
 * 5. Handle POST request:
 *    a. Validate CSRF token
 *    b. Sanitize and validate input
 *    c. Update medical record in database
 *    d. Set flash message and redirect to list
 * 6. Display pre-populated form with existing data and prescriptions
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'medical-records';
$prescription_module = 'prescriptions';

// Initialize variables
$errors = [];
$medicalRecord = null;
$prescriptions = [];

// Get and validate record ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'Invalid medical record ID.'
    ];
    header('Location: index.php?page=' . $module_name);
    exit;
}

// Fetch the medical record
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, patient_id, user_id, subjective, objective, assessment, plan, visit_date, status, isDeleted
        FROM medical_records
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $medicalRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicalRecord) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Medical record not found.'
        ];
        header('Location: index.php?page=' . $module_name);
        exit;
    }
    
    if ($medicalRecord['isDeleted']) {
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'text' => 'Cannot edit a deleted record. Please restore it first.'
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

// Fetch active patients and doctors for dropdowns
try {
    $stmtPatients = $pdo->prepare("SELECT id, name FROM patients WHERE isDeleted = 0 ORDER BY name ASC");
    $stmtPatients->execute();
    $patients = $stmtPatients->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtUsers = $pdo->prepare("SELECT id, name FROM users WHERE isDeleted = 0 AND role IN ('admin', 'dokter') ORDER BY name ASC");
    $stmtUsers->execute();
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Failed to fetch dropdown data: ' . $e->getMessage();
}

// Fetch existing prescriptions
try {
    $stmtPrescriptions = $pdo->prepare("
        SELECT 
            p.id,
            p.medication_id,
            p.qty,
            p.dosage,
            p.status,
            p.isDeleted,
            m.name AS medication_name,
            m.price AS medication_price
        FROM prescriptions p
        LEFT JOIN medications m ON p.medication_id = m.id
        WHERE p.medical_record_id = ?
        ORDER BY p.createdAt ASC
    ");
    $stmtPrescriptions->execute([$id]);
    $prescriptions = $stmtPrescriptions->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Don't fail the whole page if prescriptions can't be fetched, just show error
    $prescriptionError = 'Failed to fetch prescriptions: ' . $e->getMessage();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        // Get and sanitize input
        $patientId = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $subjective = trim($_POST['subjective'] ?? '');
        $objective = trim($_POST['objective'] ?? '');
        $assessment = trim($_POST['assessment'] ?? '');
        $plan = trim($_POST['plan'] ?? '');
        $visitDate = trim($_POST['visit_date'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Validate input
        if (!$patientId || $patientId <= 0) {
            $errors[] = 'Please select a valid patient.';
        }
        if (!$userId || $userId <= 0) {
            $errors[] = 'Please select a valid doctor.';
        }
        if (empty($visitDate)) {
            $errors[] = 'Please enter a visit date.';
        }
        
        // If no errors, proceed to update
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE medical_records 
                    SET 
                        patient_id = ?,
                        user_id = ?,
                        subjective = ?,
                        objective = ?,
                        assessment = ?,
                        plan = ?,
                        visit_date = ?,
                        status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $patientId,
                    $userId,
                    $subjective ?: null,
                    $objective ?: null,
                    $assessment ?: null,
                    $plan ?: null,
                    $visitDate,
                    $status,
                    $id
                ]);
                
                // Set success flash message
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Medical record updated successfully.'
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
        <h3>Edit Medical Record #<?php echo htmlspecialchars($medicalRecord['id']); ?></h3>
        <a href="index.php?page=<?php echo $module_name; ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ri-arrow-left-line"></i> Back to List
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
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Medical Record Details</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                <select class="form-select" id="patient_id" name="patient_id" required>
                  <option value="">-- Select Patient --</option>
                  <?php foreach ($patients as $patient): ?>
                    <option value="<?php echo htmlspecialchars($patient['id']); ?>" <?php echo $medicalRecord['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($patient['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Select the patient for this medical record</div>
              </div>
              
              <div class="col-md-6">
                <label for="user_id" class="form-label">Doctor / Examiner <span class="text-danger">*</span></label>
                <select class="form-select" id="user_id" name="user_id" required>
                  <option value="">-- Select Doctor --</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $medicalRecord['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Select the doctor or examiner</div>
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-4">
                <label for="visit_date" class="form-label">Visit Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?php echo htmlspecialchars($medicalRecord['visit_date']); ?>" required>
                <div class="form-text">Date of the patient visit</div>
              </div>
              
              <div class="col-md-8 d-flex align-items-end">
                <div class="mb-3 form-check">
                  <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo $medicalRecord['status'] ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="status">
                    Active Status
                  </label>
                  <div class="form-text">Uncheck to mark as inactive</div>
                </div>
              </div>
            </div>
            
            <hr class="my-4">
            
            <h5 class="mb-3">SOAP Notes</h5>
            
            <div class="mb-3">
              <label for="subjective" class="form-label">Subjective (S)</label>
              <textarea class="form-control" id="subjective" name="subjective" rows="3"><?php echo htmlspecialchars($medicalRecord['subjective'] ?? ''); ?></textarea>
              <div class="form-text">Patient's description of symptoms, feelings, concerns</div>
            </div>
            
            <div class="mb-3">
              <label for="objective" class="form-label">Objective (O)</label>
              <textarea class="form-control" id="objective" name="objective" rows="3"><?php echo htmlspecialchars($medicalRecord['objective'] ?? ''); ?></textarea>
              <div class="form-text">Measurable or observable findings (vitals, exam results)</div>
            </div>
            
            <div class="mb-3">
              <label for="assessment" class="form-label">Assessment (A)</label>
              <textarea class="form-control" id="assessment" name="assessment" rows="2"><?php echo htmlspecialchars($medicalRecord['assessment'] ?? ''); ?></textarea>
              <div class="form-text">Doctor's diagnosis or clinical impression</div>
            </div>
            
            <div class="mb-4">
              <label for="plan" class="form-label">Plan (P)</label>
              <textarea class="form-control" id="plan" name="plan" rows="3"><?php echo htmlspecialchars($medicalRecord['plan'] ?? ''); ?></textarea>
              <div class="form-text">Treatment plan, medications, follow-up instructions</div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Update Medical Record
              </button>
              <a href="index.php?page=<?php echo $module_name; ?>" class="btn btn-outline-secondary btn-sm">
                Cancel
              </a>
            </div>
          </form>
        </div>
      </div>

      <!-- Prescriptions Section -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Prescriptions</h5>
          <a href="index.php?page=<?php echo $prescription_module; ?>/add&medical_record_id=<?php echo htmlspecialchars($id); ?>" class="btn btn-outline-success btn-sm">
            <i class="ri-add-line"></i> Add Prescription
          </a>
        </div>
        <div class="card-body p-0">
          <?php if (isset($prescriptionError)): ?>
            <div class="alert alert-warning m-3" role="alert">
              <?php echo htmlspecialchars($prescriptionError); ?>
            </div>
          <?php elseif (empty($prescriptions)): ?>
            <div class="text-center py-4 text-muted">
              No prescriptions found for this medical record.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th scope="col" style="width: 50px;">ID</th>
                    <th scope="col">Medication</th>
                    <th scope="col">Quantity</th>
                    <th scope="col">Dosage</th>
                    <th scope="col">Unit Price</th>
                    <th scope="col">Subtotal</th>
                    <th scope="col">Status</th>
                    <th scope="col" style="width: 280px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($prescriptions as $prescription): ?>
                    <tr class="<?php echo $prescription['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                      <th scope="row"><?php echo htmlspecialchars($prescription['id']); ?></th>
                      <td><?php echo htmlspecialchars($prescription['medication_name'] ?? 'Unknown Medication'); ?></td>
                      <td><?php echo htmlspecialchars($prescription['qty']); ?></td>
                      <td><?php echo htmlspecialchars($prescription['dosage'] ?? '-'); ?></td>
                      <td>Rp <?php echo number_format((float)$prescription['medication_price'], 0, ',', '.'); ?></td>
                      <td>Rp <?php echo number_format((float)$prescription['medication_price'] * $prescription['qty'], 0, ',', '.'); ?></td>
                      <td>
                        <?php if ($prescription['isDeleted']): ?>
                          <span class="badge bg-secondary">Deleted</span>
                        <?php elseif ($prescription['status']): ?>
                          <span class="badge bg-success">Active</span>
                        <?php else: ?>
                          <span class="badge bg-warning">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex gap-1">
                          <a
                            href="index.php?page=<?php echo $prescription_module; ?>/edit&id=<?php echo htmlspecialchars($prescription['id']); ?>"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            title="Edit"
                            <?php echo $prescription['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-edit-line"></i>
                          </a>
                          <?php if ($prescription['isDeleted']): ?>
                            <form
                              method="POST"
                              action="index.php?page=<?php echo $prescription_module; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to restore this prescription?');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($prescription['id']); ?>">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="delete_type" value="undo">
                              <button
                                type="submit"
                                class="btn btn-outline-success btn-sm"
                                title="Restore"
                              >
                                <i class="ri-eye-line"></i>
                              </button>
                            </form>
                          <?php else: ?>
                            <form
                              method="POST"
                              action="index.php?page=<?php echo $prescription_module; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to delete this prescription? This can be undone later.');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($prescription['id']); ?>">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="delete_type" value="soft">
                              <button
                                type="submit"
                                class="btn btn-outline-danger btn-sm"
                                title="Soft Delete"
                              >
                                <i class="ri-delete-bin-line"></i>
                              </button>
                            </form>
                          <?php endif; ?>
                          <form
                            method="POST"
                            action="index.php?page=<?php echo $prescription_module; ?>/delete"
                            style="display: inline;"
                            onsubmit="return confirm('WARNING: This will permanently delete the prescription and cannot be undone! Are you sure?');"
                          >
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($prescription['id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="delete_type" value="hard">
                            <button
                              type="submit"
                              class="btn btn-outline-dark btn-sm"
                              title="Permanent Delete"
                            >
                              <i class="ri-delete-bin-2-line"></i>
                            </button>
                          </form>
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
  </div>
</div>

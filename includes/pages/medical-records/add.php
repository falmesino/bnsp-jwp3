<?php
/**
 * Add New Medical Record Page
 * 
 * Usage:
 * - Access via index.php?page=medical-records/add
 * - Allows creating a new medical record with patient, doctor, and SOAP notes
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Fetch active patients and doctors for dropdowns
 * 3. Handle POST request:
 *    a. Validate CSRF token
 *    b. Sanitize and validate input
 *    c. Insert new medical record into database
 *    d. Set flash message and redirect to list
 * 4. Display form with dropdowns for patient/doctor and SOAP note fields
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'medical-records';

// Initialize variables
$errors = [];
$patientId = '';
$userId = '';
$subjective = '';
$objective = '';
$assessment = '';
$plan = '';
$visitDate = date('Y-m-d');

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
        
        // If no errors, proceed to insert
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO medical_records 
                    (patient_id, user_id, subjective, objective, assessment, plan, visit_date, status)
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $patientId,
                    $userId,
                    $subjective ?: null,
                    $objective ?: null,
                    $assessment ?: null,
                    $plan ?: null,
                    $visitDate,
                    $status
                ]);
                
                if ($stmt->rowCount() > 0) {
                    // Set success flash message
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'text' => 'Medical record created successfully.'
                    ];
                    
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Redirect to list page
                    header('Location: index.php?page=' . $module_name);
                    exit;
                } else {
                    $errors[] = 'Failed to create medical record. Please try again.';
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
        <h3>Add New Medical Record</h3>
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
      <div class="card">
        <div class="card-body">
          <form method="POST" action="index.php?page=<?php echo $module_name; ?>/add">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                <select class="form-select" id="patient_id" name="patient_id" required>
                  <option value="">-- Select Patient --</option>
                  <?php foreach ($patients as $patient): ?>
                    <option value="<?php echo htmlspecialchars($patient['id']); ?>" <?php echo $patientId == $patient['id'] ? 'selected' : ''; ?>>
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
                    <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
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
                <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?php echo htmlspecialchars($visitDate); ?>" required>
                <div class="form-text">Date of the patient visit</div>
              </div>
              
              <div class="col-md-8 d-flex align-items-end">
                <div class="mb-3 form-check">
                  <input type="checkbox" class="form-check-input" id="status" name="status" value="1" checked>
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
              <textarea class="form-control" id="subjective" name="subjective" rows="3"><?php echo htmlspecialchars($subjective); ?></textarea>
              <div class="form-text">Patient's description of symptoms, feelings, concerns</div>
            </div>
            
            <div class="mb-3">
              <label for="objective" class="form-label">Objective (O)</label>
              <textarea class="form-control" id="objective" name="objective" rows="3"><?php echo htmlspecialchars($objective); ?></textarea>
              <div class="form-text">Measurable or observable findings (vitals, exam results)</div>
            </div>
            
            <div class="mb-3">
              <label for="assessment" class="form-label">Assessment (A)</label>
              <textarea class="form-control" id="assessment" name="assessment" rows="2"><?php echo htmlspecialchars($assessment); ?></textarea>
              <div class="form-text">Doctor's diagnosis or clinical impression</div>
            </div>
            
            <div class="mb-4">
              <label for="plan" class="form-label">Plan (P)</label>
              <textarea class="form-control" id="plan" name="plan" rows="3"><?php echo htmlspecialchars($plan); ?></textarea>
              <div class="form-text">Treatment plan, medications, follow-up instructions</div>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark btn-sm">
                <i class="ri-save-line"></i> Create Medical Record
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

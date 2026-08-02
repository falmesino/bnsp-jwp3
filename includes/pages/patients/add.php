<?php
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Sanitize and validate input
        $name = trim($_POST['name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

        // Validate required fields
        if (empty($name)) {
            $error = 'Patient name is required.';
        } elseif (!empty($gender) && !in_array($gender, ['M', 'F'])) {
            $error = 'Invalid gender value.';
        } else {
            try {
                // Check if email already exists (if provided)
                if (!empty($email)) {
                    $checkEmailStmt = $pdo->prepare("SELECT id FROM patients WHERE email = :email");
                    $checkEmailStmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $checkEmailStmt->execute();
                    if ($checkEmailStmt->rowCount() > 0) {
                        $error = 'Patient with this email already exists.';
                    }
                }

                // Check if phone already exists (if provided)
                if (empty($error) && !empty($phone)) {
                    $checkPhoneStmt = $pdo->prepare("SELECT id FROM patients WHERE phone = :phone");
                    $checkPhoneStmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                    $checkPhoneStmt->execute();
                    if ($checkPhoneStmt->rowCount() > 0) {
                        $error = 'Patient with this phone number already exists.';
                    }
                }

                if (empty($error)) {
                    // Insert new patient
                    $insertStmt = $pdo->prepare("
                        INSERT INTO patients (name, gender, birthdate, phone, email, address, status, createdAt, updatedAt)
                        VALUES (:name, :gender, :birthdate, :phone, :email, :address, :status, NOW(), NOW())
                    ");

                    $insertStmt->bindParam(':name', $name, PDO::PARAM_STR);
                    $insertStmt->bindParam(':gender', $gender, PDO::PARAM_STR);
                    $insertStmt->bindParam(':birthdate', $birthdate, PDO::PARAM_STR);
                    $insertStmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                    $insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $insertStmt->bindParam(':address', $address, PDO::PARAM_STR);
                    $insertStmt->bindParam(':status', $status, PDO::PARAM_INT);

                    if ($insertStmt->execute()) {
                        $success = 'Patient added successfully!';
                        // Reset form values
                        $name = '';
                        $gender = '';
                        $birthdate = '';
                        $phone = '';
                        $email = '';
                        $address = '';
                        $status = 0;
                    } else {
                        $error = 'Failed to add patient. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <h3>Add New Patient</h3>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="index.php?page=patients" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Back to Patients List
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="index.php?page=patients/add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">Patient Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required maxlength="255">
                            <div class="form-text">Enter the full name of the patient</div>
                        </div>

                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="M" <?php echo (isset($gender) && $gender === 'M') ? 'selected' : ''; ?>>Male</option>
                                <option value="F" <?php echo (isset($gender) && $gender === 'F') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="birthdate" class="form-label">Birthdate</label>
                            <input type="date" class="form-control" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($birthdate ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" maxlength="32">
                            <div class="form-text">Enter the patient's phone number (max 32 characters)</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" maxlength="64">
                            <div class="form-text">Enter the patient's email address (max 64 characters)</div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                            <div class="form-text">Enter the patient's full address</div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo isset($status) && $status ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">
                                Active Status
                            </label>
                            <div class="form-text">Check to mark the patient as active</div>
                        </div>

                        <button type="submit" class="btn btn-dark btn-sm">
                            <i class="ri-save-line"></i> Add Patient
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

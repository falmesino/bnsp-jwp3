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
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

        // Validate required fields
        if (empty($name)) {
            $error = 'Medication name is required.';
        } elseif ($stock === false) {
            $error = 'Stock must be a valid non-negative integer.';
        } elseif ($price === false) {
            $error = 'Price must be a valid number.';
        } else {
            try {
                // Check if medication name already exists
                $checkStmt = $pdo->prepare("SELECT id FROM medications WHERE name = :name");
                $checkStmt->bindParam(':name', $name, PDO::PARAM_STR);
                $checkStmt->execute();

                if ($checkStmt->rowCount() > 0) {
                    $error = 'Medication with this name already exists.';
                } else {
                    // Insert new medication
                    $insertStmt = $pdo->prepare("
                        INSERT INTO medications (name, stock, price, status, createdAt, updatedAt)
                        VALUES (:name, :stock, :price, :status, NOW(), NOW())
                    ");

                    $insertStmt->bindParam(':name', $name, PDO::PARAM_STR);
                    $insertStmt->bindParam(':stock', $stock, PDO::PARAM_INT);
                    $insertStmt->bindParam(':price', $price, PDO::PARAM_STR);
                    $insertStmt->bindParam(':status', $status, PDO::PARAM_INT);

                    if ($insertStmt->execute()) {
                        $success = 'Medication added successfully!';
                        // Reset form values
                        $name = '';
                        $stock = 0;
                        $price = 0.00;
                        $status = 0;
                    } else {
                        $error = 'Failed to add medication. Please try again.';
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
            <h3>Add New Medication</h3>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="index.php?page=medications" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Back to Medications List
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
                    <form method="POST" action="index.php?page=medications/add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">Medication Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required maxlength="64">
                            <div class="form-text">Enter the name of the medication (max 64 characters)</div>
                        </div>

                        <div class="mb-3">
                            <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($stock ?? 0); ?>" min="0" required>
                            <div class="form-text">Enter the initial stock quantity (non-negative integer)</div>
                        </div>

                        <div class="mb-3">
                            <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($price ?? 0.00); ?>" min="0" step="0.01" required>
                            <div class="form-text">Enter the price per unit (e.g., 10.50)</div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo isset($status) && $status ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">
                                Active Status
                            </label>
                            <div class="form-text">Check to mark the medication as active</div>
                        </div>

                        <button type="submit" class="btn btn-dark btn-sm">
                            <i class="ri-save-line"></i> Add Medication
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

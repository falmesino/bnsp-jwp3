<?php
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$medication = null;

// Get medication ID from query string
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $error = 'Invalid medication ID.';
} else {
    try {
        // Fetch existing medication data
        $stmt = $pdo->prepare("
            SELECT id, name, stock, price, status, isDeleted, createdAt, updatedAt
            FROM medications
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $medication = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$medication) {
            $error = 'Medication not found.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $medication) {
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
                // Check if medication name already exists (excluding current medication)
                $checkStmt = $pdo->prepare("SELECT id FROM medications WHERE name = :name AND id != :id");
                $checkStmt->bindParam(':name', $name, PDO::PARAM_STR);
                $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->rowCount() > 0) {
                    $error = 'Medication with this name already exists.';
                } else {
                    // Update medication
                    $updateStmt = $pdo->prepare("
                        UPDATE medications
                        SET name = :name, stock = :stock, price = :price, status = :status, updatedAt = NOW()
                        WHERE id = :id
                    ");

                    $updateStmt->bindParam(':name', $name, PDO::PARAM_STR);
                    $updateStmt->bindParam(':stock', $stock, PDO::PARAM_INT);
                    $updateStmt->bindParam(':price', $price, PDO::PARAM_STR);
                    $updateStmt->bindParam(':status', $status, PDO::PARAM_INT);
                    $updateStmt->bindParam(':id', $id, PDO::PARAM_INT);

                    if ($updateStmt->execute()) {
                        $success = 'Medication updated successfully!';
                        // Refresh medication data
                        $medication['name'] = $name;
                        $medication['stock'] = $stock;
                        $medication['price'] = $price;
                        $medication['status'] = $status;
                    } else {
                        $error = 'Failed to update medication. Please try again.';
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
            <h3>Edit Medication</h3>
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

    <?php if ($medication): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="index.php?page=medications/edit&id=<?php echo htmlspecialchars($medication['id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="mb-3">
                                <label for="name" class="form-label">Medication Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($medication['name']); ?>" required maxlength="64">
                                <div class="form-text">Enter the name of the medication (max 64 characters)</div>
                            </div>

                            <div class="mb-3">
                                <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($medication['stock']); ?>" min="0" required>
                                <div class="form-text">Enter the stock quantity (non-negative integer)</div>
                            </div>

                            <div class="mb-3">
                                <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($medication['price']); ?>" min="0" step="0.01" required>
                                <div class="form-text">Enter the price per unit (e.g., 10.50)</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo $medication['status'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status">
                                    Active Status
                                </label>
                                <div class="form-text">Check to mark the medication as active</div>
                            </div>

                            <button type="submit" class="btn btn-dark btn-sm">
                                <i class="ri-save-line"></i> Update Medication
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

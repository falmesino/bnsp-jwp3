<?php
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'medications';

// Fetch all medications
try {
    $stmt = $pdo->prepare("
        SELECT id, name, stock, price, status, isDeleted, createdAt, updatedAt
        FROM medications
        ORDER BY createdAt DESC
    ");
    $stmt->execute();
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch medications: ' . $e->getMessage();
}
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <h3>Medications</h3>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-12">
      <a href="index.php?page=medications/add" type="button" class="btn btn-dark btn-sm">
        <span>Add New Medication</span>
      </a>
    </div>
  </div>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col" style="width: 50px;">ID</th>
                  <th scope="col">Name</th>
                  <th scope="col">Stock</th>
                  <th scope="col">Price</th>
                  <th scope="col">Status</th>
                  <th scope="col">Created At</th>
                  <th scope="col">Updated At</th>
                  <th scope="col" style="width: 220px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($medications)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No medications found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($medications as $medication): ?>
                    <tr class="<?php echo $medication['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                      <th scope="row"><?php echo htmlspecialchars($medication['id']); ?></th>
                      <td><?php echo htmlspecialchars($medication['name']); ?></td>
                      <td><?php echo htmlspecialchars($medication['stock']); ?></td>
                      <td><?php echo htmlspecialchars(number_format((float)$medication['price'], 2)); ?></td>
                      <td>
                        <?php if ($medication['status']): ?>
                          <span class="badge bg-success">Active</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($medication['createdAt'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($medication['updatedAt'] ?? '-'); ?></td>
                      <td>
                        <div class="d-flex gap-1">
                          <a
                            href="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($medication['id']); ?>"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            title="Edit"
                            <?php echo $medication['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-edit-line"></i>
                          </a>
                          <?php if ($medication['isDeleted']): ?>
                            <form
                              method="POST"
                              action="index.php?page=<?php echo $module_name; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to restore this medication?');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($medication['id']); ?>">
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
                              action="index.php?page=<?php echo $module_name; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to soft delete this medication? This action can be undone.');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($medication['id']); ?>">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="delete_type" value="soft">
                              <button
                                type="submit"
                                class="btn btn-outline-warning btn-sm"
                                title="Soft Delete"
                              >
                                <i class="ri-eye-off-line"></i>
                              </button>
                            </form>
                          <?php endif; ?>
                          <form
                            method="POST"
                            action="index.php?page=<?php echo $module_name; ?>/delete"
                            style="display: inline;"
                            onsubmit="return confirm('Are you sure you want to permanently delete this medication? This action cannot be undone!');"
                          >
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($medication['id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="delete_type" value="hard">
                            <button
                              type="submit"
                              class="btn btn-outline-danger btn-sm"
                              title="Permanent Delete"
                            >
                              <i class="ri-delete-bin-2-line"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

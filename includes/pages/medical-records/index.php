<?php
/**
 * Medical Records List Page
 * 
 * Usage:
 * - Access via index.php?page=medical-records
 * - Displays all medical records with patient and doctor information
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Fetch all medical records with patient and user (doctor) details
 * 3. Display records in a table with actions (edit, soft delete, restore)
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'medical-records';

// Fetch all medical records with patient and doctor details
try {
    $stmt = $pdo->prepare("
        SELECT 
            mr.id,
            mr.patient_id,
            mr.user_id,
            mr.subjective,
            mr.objective,
            mr.assessment,
            mr.plan,
            mr.visit_date,
            mr.createdAt,
            mr.updatedAt,
            mr.isDeleted,
            mr.status,
            p.name AS patient_name,
            u.name AS doctor_name
        FROM medical_records mr
        LEFT JOIN patients p ON mr.patient_id = p.id
        LEFT JOIN users u ON mr.user_id = u.id
        ORDER BY mr.visit_date DESC, mr.createdAt DESC
    ");
    $stmt->execute();
    $medicalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch medical records: ' . $e->getMessage();
}
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <h3>Medical Records</h3>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-12">
      <a href="index.php?page=medical-records/add" type="button" class="btn btn-dark btn-sm">
        <i class="ri-add-line"></i> <span>Add New Medical Record</span>
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
                  <th scope="col">Patient</th>
                  <th scope="col">Doctor</th>
                  <th scope="col">Visit Date</th>
                  <th scope="col">Status</th>
                  <th scope="col">Created At</th>
                  <th scope="col" style="width: 280px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($medicalRecords)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No medical records found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($medicalRecords as $record): ?>
                    <tr class="<?php echo $record['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                      <th scope="row"><?php echo htmlspecialchars($record['id']); ?></th>
                      <td><?php echo htmlspecialchars($record['patient_name'] ?? 'Unknown Patient'); ?></td>
                      <td><?php echo htmlspecialchars($record['doctor_name'] ?? 'Unknown Doctor'); ?></td>
                      <td><?php echo htmlspecialchars($record['visit_date'] ?? '-'); ?></td>
                      <td>
                        <?php if ($record['isDeleted']): ?>
                          <span class="badge bg-secondary">Deleted</span>
                        <?php elseif ($record['status']): ?>
                          <span class="badge bg-success">Active</span>
                        <?php else: ?>
                          <span class="badge bg-warning">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($record['createdAt'] ?? '-'); ?></td>
                      <td>
                        <div class="d-flex gap-1">
                          <a
                            href="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($record['id']); ?>"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            title="Edit"
                            <?php echo $record['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-edit-line"></i>
                          </a>
                          <?php if ($record['isDeleted']): ?>
                            <form
                              method="POST"
                              action="index.php?page=<?php echo $module_name; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to restore this medical record?');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($record['id']); ?>">
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
                              onsubmit="return confirm('Are you sure you want to delete this medical record? This can be undone later.');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($record['id']); ?>">
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
                            action="index.php?page=<?php echo $module_name; ?>/delete"
                            style="display: inline;"
                            onsubmit="return confirm('WARNING: This will permanently delete the record and cannot be undone! Are you sure?');"
                          >
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($record['id']); ?>">
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
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

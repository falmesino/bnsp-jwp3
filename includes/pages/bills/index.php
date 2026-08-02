<?php
/**
 * Bills List Page
 * 
 * Usage:
 * - Access via index.php?page=bills
 * - Displays all bills with patient, medical record, total amount, and payment status
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Fetch all bills with patient and medical record details
 * 3. Display bills in a table with actions (view, add payment, edit, soft delete, restore, hard delete)
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'bills';
$payment_module = 'payments';

// Fetch all bills with patient and medical record details
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.medical_record_id,
            b.total,
            b.bill_status,
            b.createdAt,
            b.updatedAt,
            b.isDeleted,
            b.status,
            p.name AS patient_name,
            mr.visit_date
        FROM bills b
        LEFT JOIN medical_records mr ON b.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        ORDER BY b.createdAt DESC
    ");
    $stmt->execute();
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch bills: ' . $e->getMessage();
}
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <h3>Bills</h3>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-12">
      <a href="index.php?page=<?php echo $module_name; ?>/add" type="button" class="btn btn-dark btn-sm">
        <i class="ri-add-line"></i> <span>Create New Bill</span>
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
                  <th scope="col">Visit Date</th>
                  <th scope="col">Total Amount</th>
                  <th scope="col">Payment Status</th>
                  <th scope="col">Created At</th>
                  <th scope="col" style="width: 320px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($bills)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No bills found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($bills as $bill): ?>
                    <tr class="<?php echo $bill['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                      <th scope="row"><?php echo htmlspecialchars($bill['id']); ?></th>
                      <td><?php echo htmlspecialchars($bill['patient_name'] ?? 'Unknown Patient'); ?></td>
                      <td><?php echo htmlspecialchars($bill['visit_date'] ?? '-'); ?></td>
                      <td>Rp <?php echo number_format((float)$bill['total'], 0, ',', '.'); ?></td>
                      <td>
                        <?php if ($bill['isDeleted']): ?>
                          <span class="badge bg-secondary">Deleted</span>
                        <?php else: ?>
                          <?php if ($bill['bill_status'] === 'paid'): ?>
                            <span class="badge bg-success">Paid</span>
                          <?php else: ?>
                            <span class="badge bg-warning">Pending</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($bill['createdAt'] ?? '-'); ?></td>
                      <td>
                        <div class="d-flex gap-1">
                          <a
                            href="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($bill['id']); ?>"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            title="Edit Bill"
                            <?php echo $bill['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-edit-line"></i>
                          </a>
                          
                          <a
                            href="index.php?page=<?php echo $payment_module; ?>/add&bill_id=<?php echo htmlspecialchars($bill['id']); ?>"
                            type="button"
                            class="btn btn-outline-success btn-sm"
                            title="Add Payment"
                            <?php echo $bill['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-money-dollar-circle-line"></i>
                          </a>
                          
                          <?php if ($bill['isDeleted']): ?>
                            <form
                              method="POST"
                              action="index.php?page=<?php echo $module_name; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to restore this bill?');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($bill['id']); ?>">
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
                              onsubmit="return confirm('Are you sure you want to delete this bill? This can be undone later.');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($bill['id']); ?>">
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
                            onsubmit="return confirm('WARNING: This will permanently delete the bill and cannot be undone! Are you sure?');"
                          >
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($bill['id']); ?>">
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

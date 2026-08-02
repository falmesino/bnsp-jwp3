<?php
/**
 * Payments List Page
 * 
 * Usage:
 * - Access via index.php?page=payments
 * - Displays all payments with bill, patient, and amount details
 * 
 * Process:
 * 1. Generate CSRF token if not exists
 * 2. Fetch all payments with bill and patient details
 * 3. Display payments in a table with actions (edit, delete)
 */

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$module_name = 'payments';
$bill_module = 'bills';

// Fetch all payments with bill and patient details
try {
    $stmt = $pdo->prepare("
        SELECT 
            pay.id,
            pay.bill_id,
            pay.amount,
            pay.method,
            pay.date,
            pay.createdAt,
            pay.isDeleted,
            b.total AS bill_total,
            b.bill_status,
            p.name AS patient_name,
            mr.visit_date
        FROM payments pay
        LEFT JOIN bills b ON pay.bill_id = b.id
        LEFT JOIN medical_records mr ON b.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        ORDER BY pay.date DESC, pay.createdAt DESC
    ");
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch payments: ' . $e->getMessage();
}
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <h3>Payments</h3>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-12">
      <a href="index.php?page=<?php echo $bill_module; ?>" type="button" class="btn btn-outline-secondary btn-sm">
        <i class="ri-arrow-left-line"></i> <span>Go to Bills</span>
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
                  <th scope="col">Bill ID</th>
                  <th scope="col">Bill Total</th>
                  <th scope="col">Payment Amount</th>
                  <th scope="col">Method</th>
                  <th scope="col">Date</th>
                  <th scope="col">Status</th>
                  <th scope="col" style="width: 150px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No payments found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $payment): ?>
                    <tr class="<?php echo $payment['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                      <th scope="row"><?php echo htmlspecialchars($payment['id']); ?></th>
                      <td><?php echo htmlspecialchars($payment['patient_name'] ?? 'Unknown'); ?></td>
                      <td>
                        <a href="index.php?page=<?php echo $bill_module; ?>/edit&id=<?php echo htmlspecialchars($payment['bill_id']); ?>">
                          #<?php echo htmlspecialchars($payment['bill_id']); ?>
                        </a>
                      </td>
                      <td>Rp <?php echo number_format((float)$payment['bill_total'], 0, ',', '.'); ?></td>
                      <td>Rp <?php echo number_format((float)$payment['amount'], 0, ',', '.'); ?></td>
                      <td><?php echo htmlspecialchars($payment['method'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($payment['date'] ?? $payment['createdAt'] ?? '-'); ?></td>
                      <td>
                        <?php if ($payment['isDeleted']): ?>
                          <span class="badge bg-secondary">Deleted</span>
                        <?php else: ?>
                          <span class="badge bg-success">Valid</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex gap-1">
                          <a
                            href="index.php?page=<?php echo $module_name; ?>/edit&id=<?php echo htmlspecialchars($payment['id']); ?>"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            title="Edit"
                            <?php echo $payment['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-edit-line"></i>
                          </a>
                          <?php if (!$payment['isDeleted']): ?>
                            <form
                              method="POST"
                              action="index.php?page=<?php echo $module_name; ?>/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to delete this payment?');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($payment['id']); ?>">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="delete_type" value="soft">
                              <button
                                type="submit"
                                class="btn btn-outline-danger btn-sm"
                                title="Delete"
                              >
                                <i class="ri-delete-bin-line"></i>
                              </button>
                            </form>
                          <?php endif; ?>
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

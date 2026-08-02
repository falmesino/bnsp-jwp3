<?php
/**
 * Dashboard page - displayed after successful login
 * 
 * Shows welcome message and quick stats
 */
?>

<div class="container mt-4">
  <div class="row mb-4">
    <div class="col-12">
      <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?>!</h1>
      <p class="text-muted">Role: <?php echo htmlspecialchars(ucfirst($_SESSION['user_role'] ?? 'user')); ?></p>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="me-3">
              <i class="ri-file-list-3-line text-primary" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h5 class="card-title mb-0">Medical Records</h5>
              <p class="card-text text-muted">Manage patient medical records</p>
            </div>
          </div>
          <a href="index.php?page=medical-records" class="btn btn-outline-primary btn-sm mt-3">
            View Records
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="me-3">
              <i class="ri-user-line text-success" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h5 class="card-title mb-0">Patients</h5>
              <p class="card-text text-muted">Manage patient information</p>
            </div>
          </div>
          <a href="index.php?page=patients" class="btn btn-outline-success btn-sm mt-3">
            View Patients
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="me-3">
              <i class="ri-capsule-line text-warning" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h5 class="card-title mb-0">Medications</h5>
              <p class="card-text text-muted">Manage medication inventory</p>
            </div>
          </div>
          <a href="index.php?page=medications" class="btn btn-outline-warning btn-sm mt-3">
            View Medications
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="me-3">
              <i class="ri-receipt-line text-info" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h5 class="card-title mb-0">Bills</h5>
              <p class="card-text text-muted">Manage patient billing</p>
            </div>
          </div>
          <a href="index.php?page=bills" class="btn btn-outline-info btn-sm mt-3">
            View Bills
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="me-3">
              <i class="ri-bank-card-line text-danger" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h5 class="card-title mb-0">Payments</h5>
              <p class="card-text text-muted">Track payment records</p>
            </div>
          </div>
          <a href="index.php?page=payments" class="btn btn-outline-danger btn-sm mt-3">
            View Payments
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

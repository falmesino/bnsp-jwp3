<?php
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all users
try {
    $stmt = $pdo->prepare("
        SELECT id, username, name, email, phone, role, gender, status, isDeleted, createdAt, updatedAt
        FROM users
        ORDER BY createdAt DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch users: ' . $e->getMessage();
}

?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <h3>Users</h3>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-12">
      <a href="index.php?page=users/add" type="button" class="btn btn-dark btn-sm">
        <span>Add New User</span>
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
                  <th scope="col">Username</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Role</th>
                  <th scope="col">Gender</th>
                  <th scope="col">Status</th>
                  <th scope="col" style="width: 220px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No users found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($users as $user): ?>
                    <tr class="<?php echo $user['isDeleted'] ? 'table-secondary text-muted' : ''; ?>">
                      <th scope="row"><?php echo htmlspecialchars($user['id']); ?></th>
                      <td><?php echo htmlspecialchars($user['username']); ?></td>
                      <td><?php echo htmlspecialchars($user['name']); ?></td>
                      <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                      <td>
                        <span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($user['role']); ?></span>
                      </td>
                      <td><?php echo htmlspecialchars($user['gender'] ?? '-'); ?></td>
                      <td>
                        <?php if ($user['status']): ?>
                          <span class="badge bg-success">Active</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex gap-1">
                          <a
                            href="index.php?page=users/edit&id=<?php echo htmlspecialchars($user['id']); ?>"
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            title="Edit"
                            <?php echo $user['isDeleted'] ? 'disabled' : ''; ?>
                          >
                            <i class="ri-edit-line"></i>
                          </a>
                          <?php if ($user['isDeleted']): ?>
                            <form
                              method="POST"
                              action="index.php?page=users/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to restore this user?');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
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
                              action="index.php?page=users/delete"
                              style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to soft delete this user? This action can be undone.');"
                            >
                              <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
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
                            action="index.php?page=users/delete"
                            style="display: inline;"
                            onsubmit="return confirm('Are you sure you want to permanently delete this user? This action cannot be undone!');"
                          >
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
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

<?php
$errors = [];
$success = false;
$user = null;

// Get user ID from query parameter
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    header('Location: index.php?page=users');
    exit;
}

// Fetch existing user data
try {
    $stmt = $pdo->prepare("
        SELECT id, username, name, email, phone, address, gender, role, status
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: index.php?page=users');
        exit;
    }
} catch (PDOException $e) {
    $errors['general'] = 'Database error: ' . $e->getMessage();
}

// Initialize form values from database
$formValues = [
    'username' => $user['username'] ?? '',
    'name' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'address' => $user['address'] ?? '',
    'gender' => $user['gender'] ?? '',
    'role' => $user['role'] ?? 'user',
    'status' => $user['status'] ?? 0
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? ''); // Optional: only hash if provided
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

    // Update form values for re-population
    $formValues = [
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'gender' => $gender,
        'role' => $role,
        'status' => $status
    ];

    // Validation
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($name)) $errors['name'] = 'Name is required';
    if (!empty($password) && strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters';

    // Check for duplicate username, email, phone (excluding current user)
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->rowCount() > 0) $errors['username'] = 'Username already exists';

        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->rowCount() > 0) $errors['email'] = 'Email already exists';
        }

        if (!empty($phone)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $stmt->execute([$phone, $userId]);
            if ($stmt->rowCount() > 0) $errors['phone'] = 'Phone number already exists';
        }
    } catch (PDOException $e) {
        $errors['general'] = 'Database error: ' . $e->getMessage();
    }

    // If no errors, proceed to update
    if (empty($errors)) {
        try {
            if (!empty($password)) {
                // Update with new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, password = ?, name = ?, email = ?, phone = ?, address = ?, gender = ?, role = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username,
                    $hashedPassword,
                    $name,
                    !empty($email) ? $email : null,
                    !empty($phone) ? $phone : null,
                    !empty($address) ? $address : null,
                    !empty($gender) ? $gender : null,
                    $role,
                    $status,
                    $userId
                ]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, name = ?, email = ?, phone = ?, address = ?, gender = ?, role = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username,
                    $name,
                    !empty($email) ? $email : null,
                    !empty($phone) ? $phone : null,
                    !empty($address) ? $address : null,
                    !empty($gender) ? $gender : null,
                    $role,
                    $status,
                    $userId
                ]);
            }
            $success = true;
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to update user: ' . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">

  <div class="row">
    <div class="col-12 col-lg-8 mx-auto">
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          User updated successfully!
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php echo htmlspecialchars($errors['general']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header d-flex flex-row align-items-center justify-content-between">
          <h5 class="mb-0">Edit User</h5>
          <a href="index.php?page=users" type="button" class="btn btn-dark btn-sm">
            <span>Back to List</span>
          </a>
        </div>
        <div class="card-body">
          <form method="POST" action="index.php?page=users/edit&id=<?php echo htmlspecialchars($userId); ?>">
            <!-- Username -->
            <div class="mb-3">
              <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($formValues['username']); ?>"
                required
              >
              <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Password (Optional) -->
            <div class="mb-3">
              <label for="password" class="form-label">Password (Leave empty to keep current)</label>
              <input
                type="password"
                class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                id="password"
                name="password"
              >
              <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Name -->
            <div class="mb-3">
              <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                id="name"
                name="name"
                value="<?php echo htmlspecialchars($formValues['name']); ?>"
                required
              >
              <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input
                type="email"
                class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars($formValues['email']); ?>"
              >
              <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Phone -->
            <div class="mb-3">
              <label for="phone" class="form-label">Phone</label>
              <input
                type="text"
                class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                id="phone"
                name="phone"
                value="<?php echo htmlspecialchars($formValues['phone']); ?>"
              >
              <?php if (isset($errors['phone'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Gender -->
            <div class="mb-3">
              <label class="form-label">Gender</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="gender" id="genderM" value="M" <?php echo $formValues['gender'] === 'M' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="genderM">Male</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="gender" id="genderF" value="F" <?php echo $formValues['gender'] === 'F' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="genderF">Female</label>
              </div>
            </div>

            <!-- Role -->
            <div class="mb-3">
              <label for="role" class="form-label">Role</label>
              <select class="form-select" id="role" name="role">
                <option value="user" <?php echo $formValues['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo $formValues['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="dokter" <?php echo $formValues['role'] === 'dokter' ? 'selected' : ''; ?>>Dokter</option>
                <option value="apoteker" <?php echo $formValues['role'] === 'apoteker' ? 'selected' : ''; ?>>Apoteker</option>
              </select>
            </div>

            <!-- Address -->
            <div class="mb-3">
              <label for="address" class="form-label">Address</label>
              <textarea
                class="form-control"
                id="address"
                name="address"
                rows="3"
              ><?php echo htmlspecialchars($formValues['address']); ?></textarea>
            </div>

            <!-- Status -->
            <div class="mb-3">
              <label for="status" class="form-label">Status</label>
              <select class="form-select" id="status" name="status">
                <option value="0" <?php echo $formValues['status'] === 0 ? 'selected' : ''; ?>>Inactive</option>
                <option value="1" <?php echo $formValues['status'] === 1 ? 'selected' : ''; ?>>Active</option>
              </select>
            </div>

            <button type="submit" class="btn btn-primary">Update User</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

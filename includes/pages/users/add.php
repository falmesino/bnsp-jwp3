<?php
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

    // Validation
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters';
    if (empty($name)) $errors['name'] = 'Name is required';

    // Check for duplicate username, email, phone
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) $errors['username'] = 'Username already exists';

        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) $errors['email'] = 'Email already exists';
        }

        if (!empty($phone)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() > 0) $errors['phone'] = 'Phone number already exists';
        }
    } catch (PDOException $e) {
        $errors['general'] = 'Database error: ' . $e->getMessage();
    }

    // If no errors, proceed to insert
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, name, email, phone, address, gender, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $status
            ]);
            $success = true;
            // Reset form
            $username = $name = $email = $phone = $address = $gender = $role = '';
            $status = 0;
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to create user: ' . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <a href="index.php?page=users" type="button" class="btn btn-dark btn-sm">
        <span>Back to List</span>
      </a>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8 mx-auto">
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          User created successfully!
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
        <div class="card-header">
          <h5 class="mb-0">Add New User</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="index.php?page=users/add">
            <!-- Username -->
            <div class="mb-3">
              <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($username ?? ''); ?>"
                required
              >
              <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="mb-3">
              <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
              <input
                type="password"
                class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                id="password"
                name="password"
                required
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
                value="<?php echo htmlspecialchars($name ?? ''); ?>"
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
                value="<?php echo htmlspecialchars($email ?? ''); ?>"
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
                value="<?php echo htmlspecialchars($phone ?? ''); ?>"
              >
              <?php if (isset($errors['phone'])): ?>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div>
              <?php endif; ?>
            </div>

            <!-- Gender -->
            <div class="mb-3">
              <label class="form-label">Gender</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="gender" id="genderM" value="M" <?php echo ($gender ?? '') === 'M' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="genderM">Male</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="gender" id="genderF" value="F" <?php echo ($gender ?? '') === 'F' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="genderF">Female</label>
              </div>
            </div>

            <!-- Role -->
            <div class="mb-3">
              <label for="role" class="form-label">Role</label>
              <select class="form-select" id="role" name="role">
                <option value="user" <?php echo ($role ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="dokter" <?php echo ($role ?? '') === 'dokter' ? 'selected' : ''; ?>>Dokter</option>
                <option value="apoteker" <?php echo ($role ?? '') === 'apoteker' ? 'selected' : ''; ?>>Apoteker</option>
              </select>
            </div>

            <!-- Status -->
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="status" name="status" value="1" <?php echo ($status ?? 0) === 1 ? 'checked' : ''; ?>>
                <label class="form-check-label" for="status">Active Status</label>
              </div>
            </div>

            <!-- Address -->
            <div class="mb-3">
              <label for="address" class="form-label">Address</label>
              <textarea
                class="form-control"
                id="address"
                name="address"
                rows="3"
              ><?php echo htmlspecialchars($address ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Create User</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

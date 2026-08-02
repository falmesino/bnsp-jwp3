<?php
/**
 * Handles user sign-in process
 * 
 * Usage:
 * - Access via index.php?page=sign-in
 * - Submit username and password via POST
 * 
 * Process:
 * 1. Check if user is already logged in, redirect to dashboard if true
 * 2. Generate CSRF token if not exists
 * 3. Handle POST request:
 *    a. Validate CSRF token
 *    b. Sanitize and validate input
 *    c. Query database for user
 *    d. Verify password
 *    e. Set session variables on success
 *    f. Redirect to dashboard
 * 4. Display login form with errors if any
 */

// Check if user is already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Initialize variables
$error = '';
$username = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Get and sanitize input
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                // Query database for user
                $stmt = $pdo->prepare("
                    SELECT id, username, password, role, name, email
                    FROM users
                    WHERE username = ? AND isDeleted = 0
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify user exists and password is correct
                if ($user && password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    
                    // Regenerate CSRF token for security
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Redirect to dashboard
                    header('Location: index.php?page=dashboard');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container">
  <div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5">

      <div class="card shadow border-0">
        <div class="card-body p-4 p-lg-5">

          <h2 class="text-center mb-4">Sign In</h2>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?php echo htmlspecialchars($error); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="POST" action="index.php?page=sign-in">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input
                type="text"
                class="form-control"
                id="username"
                name="username"
                placeholder="Enter your username"
                value="<?php echo htmlspecialchars($username); ?>"
                required
                autofocus
              >
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
              >
            </div>

            <button
              type="submit"
              class="btn btn-primary w-100"
            >
              Sign In
            </button>

          </form>

        </div>
      </div><!--/ .card -->
    </div><!--/ .col-12 -->
  </div><!--/ .row -->
</div><!--/ .container -->

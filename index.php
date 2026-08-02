<?php
  require_once('./includes/config/main.php');

  // Get flash message and clear it
  $flashMessage = $_SESSION['flash_message'] ?? null;
  if ($flashMessage) {
      unset($_SESSION['flash_message']);
  }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css"
      integrity="sha512-XcIsjKMcuVe0Ucj/xgIXQnytNwBttJbNjltBV18IOnru2lDPe9KRRyvCXw6Y5H415vbBLRm8+q6fmLUU7DfO6Q=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
      crossorigin="anonymous"
    >
    <link
      rel="stylesheet"
      href="./css/style.css"
    />
    <title>SIMRS BNSP JWP3</title>
  </head>
  <body style="padding-top: 57px;">
    <nav class="navbar navbar-expand-lg fixed-top bg-dark border-bottom border-body" data-bs-theme="dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
          SIMRS
        </a>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarSupportedContent"
          aria-controls="navbarSupportedContent"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a
                class="nav-link active"
                aria-current="page"
                href="index.php?page=dashboard"
              >
                Dashboard
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="index.php?page=medical-records">
                Medical Records
              </a>
            </li>
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                Billing
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="index.php?page=bills">Bills</a></li>
                <li><a class="dropdown-item" href="index.php?page=payments">Payments</a></li>
              </ul>
            </li>
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                Master Data
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="index.php?page=users">Users</a></li>
                <li><a class="dropdown-item" href="index.php?page=patients">Patients</a></li>
                <li><a class="dropdown-item" href="index.php?page=medications">Medications</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Something else here</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="container-fluid pb-5">
      <?php if ($flashMessage): ?>
        <div class="container mt-4">
          <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flashMessage['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        </div>
      <?php endif; ?>

      <?php
        $page = 'index';

        if (isset($_GET['page']) && !empty($_GET['page'])) {
          $page = $_GET['page'];
          
          // Sanitize the page parameter to prevent directory traversal
          $page = ltrim($page, '/');
          $page = preg_replace('/\.\.(?:\/|\\\)/', '', $page);
        }

        $filePath = './includes/pages' . '/' . $page . '.php';
        $dirPath = './includes/pages' . '/' . $page . '/index.php';

        /*
        echo $filePath;
        echo '<br />';
        echo $dirPath;
        echo '<br />';
        */

        if (file_exists($filePath)) {
            include_once($filePath);
        } elseif (file_exists($dirPath)) {
            include_once($dirPath);
        } else {
            echo 'Page not found';
        }
      ?>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
      crossorigin="anonymous"
    ></script>
  </body>
</html>

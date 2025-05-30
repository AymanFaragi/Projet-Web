<?php
require_once 'config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
  header("Location: admin.php");
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin_number = trim($_POST['admin_number'] ?? '');

  try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_number = ?");
    $stmt->execute([$admin_number]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
      $_SESSION['admin_logged_in'] = true;
      $_SESSION['admin_id'] = $admin['admin_id'];
      $_SESSION['admin_number'] = $admin['admin_number'];
      $_SESSION['admin_name'] = $admin['full_name'];
      $_SESSION['admin_role'] = $admin['role'];
      $_SESSION['admin_profile'] = $admin['profile_picture'];

      $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE admin_id = ?");
      $stmt->execute([$admin['admin_id']]);

      header("Location: admin.php");
      exit;
    } else {
      $error = "Invalid admin ID";
    }
  } catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="admin_login.css">
</head>

<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h1 class="login-title">Admin Portal</h1>
        <p class="login-subtitle">Enter your admin ID to continue</p>
      </div>

      <div class="login-body">
        <?php if ($error): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">
          <div class="form-group">
            <label for="admin_id" class="form-label">Admin ID</label>
            <div class="input-icon">
              <i class="fas fa-id-card"></i>
              <input type="text" id="admin_id" name="admin_number" class="form-control" placeholder="Enter your admin ID"
                required autofocus>
            </div>
          </div>
          <div class="form-group">
            <button type="submit" class="btn btn-primary btn-block">
              <i class="fas fa-sign-in-alt"></i> Login
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('admin_id').addEventListener('input', function (e) {
      this.value = this.value.toUpperCase();
    });
  </script>
</body>

</html>
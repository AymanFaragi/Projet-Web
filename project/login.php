<?php
include 'config.php';

if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $conn = getDBConnection();
  $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
  $stmt->bindParam(':email', $email);
  $stmt->execute();
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

    header("Location: index.php");
    exit;
  } else {
    $error = "Invalid email or password";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="login.css">
  <link rel="stylesheet" href="index.css">
</head>

<body>
  <div class="auth-container">
    <div class="auth-image"></div>
    <div class="auth-form-container">
      <form class="auth-form" method="POST" action="login.php">
        <h2>Welcome Back</h2>

        <?php if ($error): ?>
          <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="auth-btn">Login</button>

        <div class="form-divider">
          <span>OR</span>
        </div>
        <div class="auth-link">
          Don't have an account? <a href="signup.php">Sign up</a>
        </div>
      </form>
    </div>
  </div>
</body>

</html>
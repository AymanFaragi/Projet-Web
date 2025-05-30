
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="index.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <span class="brand-name"><i class="fas fa-cloud"></i> sKYS</span>
    </div>
    <div class="nav-links">
      <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> <span>Home</span></a>
      <a href="products.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>"><i class="fas fa-box-open"></i> <span>Products</span></a>
      <a href="cart.php" class="nav-link cart-link <?= basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : '' ?>">
        <i class="fas fa-shopping-cart"></i> <span>Cart</span> 
        <span class="cart-count"><?= isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0 ?></span>
      </a>
      <a href="wishlist.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'wishlist.php' ? 'active' : '' ?>"><i class="fas fa-heart"></i> <span>Wishlist</span></a>
      <?php if (isset($_SESSION['user_id'])): ?>
    <a href="account.php" class="nav-link"><i class="fas fa-user"></i> <span>Account</span></a>
    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
  <?php else: ?>
    <a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
    <a href="signup.php" class="nav-link"><i class="fas fa-user-plus"></i> <span>Sign Up</span></a>
  <?php endif; ?>
    </div>
    <div class="mobile-menu-btn">
      <i class="fas fa-bars"></i>
    </div>
  </div>
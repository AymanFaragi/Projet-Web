<?php
include 'config.php';
$conn = getDBConnection();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php?redirect=cart");
  exit;
}

$cartItems = [];
$subtotal = 0;
$shipping = 5.00;
$tax = 0.08;
$total = 0;
$error = '';
$success = '';

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
  $cartItems = [];
  $subtotal = 0;
  $batchSize = 50;

  $cartChunks = array_chunk($_SESSION['cart'], $batchSize, true);

  foreach ($cartChunks as $chunk) {
    $cartIds = array_column($chunk, 'product_id');
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));

    $stmt = $conn->prepare("
          SELECT product_id, name, price, quantity, image_url 
          FROM products 
          WHERE product_id IN ($placeholders)
      ");
    $stmt->execute($cartIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $productLookup = [];
    foreach ($products as $product) {
      $productLookup[$product['product_id']] = $product;
    }

    foreach ($chunk as $cartItem) {
      if (isset($productLookup[$cartItem['product_id']])) {
        $product = $productLookup[$cartItem['product_id']];
        $cartItems[] = [
          'product' => $product,
          'quantity' => $cartItem['quantity']
        ];
        $subtotal += $product['price'] * $cartItem['quantity'];
      }
    }
  }

  $total = $subtotal + $shipping + ($subtotal * $tax);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
  $productId = $_POST['product_id'];
  $newQuantity = (int) $_POST['quantity'];

  if ($newQuantity > 0) {
    foreach ($_SESSION['cart'] as &$item) {
      if ($item['product_id'] == $productId) {
        $item['quantity'] = $newQuantity;
        break;
      }
    }
    header("Location: cart.php");
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
  $productId = $_POST['product_id'];
  $_SESSION['cart'] = array_filter($_SESSION['cart'], function ($item) use ($productId) {
    return $item['product_id'] != $productId;
  });
  header("Location: cart.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
  $_SESSION['pending_order'] = [
    'cart_items' => $cartItems,
    'subtotal' => $subtotal,
    'shipping' => $shipping,
    'total' => $total
  ];
  header("Location: payment.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="cart.css">
</head>

<body>
  <?php include 'header.php'; ?>

  <div class="cart-container">
    <div class="cart-header">
      <h1 class="cart-title">Your Shopping Cart</h1>
      <div class="cart-steps">
        <div class="step active">
          <span class="step-number">1</span>
        </div>
        <div class="step">
          <span class="step-number">2</span>
        </div>
        <div class="step">
          <span class="step-number">3</span>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
      <div class="empty-cart-container">
        <div class="empty-cart-content">
          <h2 class="empty-cart-title">Your Cart is Empty</h2>
          <p class="empty-cart-message">Looks like you haven't added anything to your cart yet. Let's get shopping!</p>
          <div class="empty-cart-actions">
            <a href="products.php" class="btn-primary">
              <i class="fas fa-shopping-bag"></i> Start Shopping
            </a>
            <a href="index.php" class="btn-secondary">
              <i class="fas fa-home"></i> Back to Home
            </a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="cart-content">
        <div class="cart-items-container">
          <div class="cart-items-header">
            <h3>Your Items (<?= count($cartItems) ?>)</h3>
            <a href="products.php" class="continue-shopping">
              <i class="fas fa-plus"></i> Add More Items
            </a>
          </div>

          <div class="cart-items">
            <?php foreach ($cartItems as $cartItem):
              $product = $cartItem['product'];
              $itemTotal = $product['price'] * $cartItem['quantity'];
              ?>
              <div class="cart-item">
                <div class="cart-item-image">
                  <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                </div>

                <div class="cart-item-details">
                  <h4><?= htmlspecialchars($product['name']) ?></h4>
                  <div class="cart-item-meta">
                    <div class="cart-item-price">
                      $<?= number_format($product['price'], 2) ?>
                    </div>
                    <?php if ($product['quantity'] < $cartItem['quantity']): ?>
                      <div class="stock-warning">
                        <i class="fas fa-exclamation-triangle"></i> Only <?= $product['quantity'] ?> available
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="cart-item-actions">
                    <form method="POST" action="cart.php" class="quantity-selector">
                      <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                      <button type="button" class="quantity-btn minus" onclick="updateQuantity(this, -1)">
                        <i class="fas fa-minus"></i>
                      </button>
                      <input type="number" name="quantity" value="<?= $cartItem['quantity'] ?>" min="1"
                        max="<?= $product['quantity'] ?>" readonly>
                      <button type="button" class="quantity-btn plus" onclick="updateQuantity(this, 1)">
                        <i class="fas fa-plus"></i>
                      </button>
                      <input type="hidden" name="update_quantity" value="1">
                    </form>

                    <form method="POST" action="cart.php" class="remove-form">
                      <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                      <button type="submit" name="remove_item" class="remove-btn">
                        <i class="fas fa-trash-alt"></i> Remove
                      </button>
                    </form>
                  </div>
                </div>

                <div class="cart-item-total">
                  $<?= number_format($itemTotal, 2) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="cart-summary">
          <div class="summary-card">
            <h3 class="summary-title">Order Summary</h3>

            <div class="summary-details">
              <div class="summary-row">
                <span>Subtotal</span>
                <span>$<?= number_format($subtotal, 2) ?></span>
              </div>

              <div class="summary-row">
                <span>Shipping</span>
                <span>$<?= number_format($shipping, 2) ?></span>
              </div>

              <div class="summary-row">
                <span>Estimated Tax</span>
                <span>$<?= number_format($subtotal * $tax, 2) ?></span>
              </div>

              <div class="summary-divider"></div>

              <div class="summary-row summary-total">
                <span>Total</span>
                <span>$<?= number_format($total, 2) ?></sdecimals: pan>
              </div>
            </div>

            <form method="POST" action="cart.php">
              <button type="submit" name="checkout" class="checkout-btn">
                <i class="fas fa-lock"></i> Proceed to Checkout
              </button>
            </form>
          </div>

        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    function updateQuantity(button, change) {
      const form = button.closest('form');
      const input = form.querySelector('input[name="quantity"]');
      let newValue = parseInt(input.value) + change;
      newValue = Math.max(parseInt(input.min), Math.min(newValue, parseInt(input.max)));
      input.value = newValue;
      form.submit();
    }


    document.querySelectorAll('input[name="quantity"]').forEach(input => {
      input.addEventListener('change', function () {
        this.form.submit();
      });
    });
  </script>
</body>

</html>
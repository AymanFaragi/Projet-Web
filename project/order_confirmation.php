<?php
include 'config.php';
$conn = getDBConnection();
if (!isset($_SESSION['order_success'])) {
  header("Location: cart.php");
  exit;
}

$orderId = $_SESSION['order_success'];
unset($_SESSION['order_success']);

$stmt = $conn->prepare("
    SELECT o.*, u.first_name, u.email 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.image_url 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="order_confirmation.css">
  <link rel="stylesheet" href="cart.css">
</head>

<body>
  <?php include 'header.php'; ?>
  <div class="cart-header">
    <h1 class="cart-title">Your Shopping Cart</h1>
    <div class="cart-steps">
      <div class="step ">
        <span class="step-number">1</span>
      </div>
      <div class="step">
        <span class="step-number">2</span>
      </div>
      <div class="step active">
        <span class="step-number">3</span>
      </div>
    </div>
  </div>

  <div class="confirmation-container">
    <div class="confirmation-header">
      <h1>Order Confirmed!</h1>
      <p>Thank you for your purchase, <?= htmlspecialchars($order['first_name']) ?>!</p>
    </div>

    <div class="confirmation-content">
      <div class="order-details">
        <h2>Order #<?= $orderId ?></h2>
        <div class="detail-row">
          <span>Order Date:</span>
          <span><?= date('F j, Y', strtotime($order['order_date'])) ?></span>
        </div>
        <div class="detail-row">
          <span>Total:</span>
          <span>$<?= number_format($order['total_amount'], 2) ?></span>
        </div>
        <div class="detail-row">
          <span>Payment Method:</span>
          <span><?= htmlspecialchars($order['payment_method']) ?></span>
        </div>
        <div class="detail-row">
          <span>Shipment Address</span>
          <span><?= htmlspecialchars($order['shipping_address']) ?></span>
        </div>
        <div class="detail-row">
          <span>Status:</span>
          <span class="status-badge"><?= ucfirst($order['status']) ?></span>
        </div>
      </div>

      <div class="order-items">
        <h3>Your Items</h3>
        <?php foreach ($items as $item): ?>
          <div class="order-item">
            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <div class="item-info">
              <h4><?= htmlspecialchars($item['name']) ?></h4>
              <div class="item-meta">
                <span>Qty: <?= $item['quantity'] ?></span>
                <span>$<?= number_format($item['price'], 2) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="confirmation-actions">
        <a href="products.php" class="continue-btn">
          Continue Shopping
        </a>
        <a href="account.php" class="orders-btn">
          View Order History
        </a>
      </div>
    </div>
  </div>

</body>

</html>
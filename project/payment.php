<?php
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
  die("Database connection failed");
}

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php?redirect=payment");
  exit;
}

if (!isset($_SESSION['pending_order'])) {
  header("Location: cart.php");
  exit;
}

$order = $_SESSION['pending_order'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
  $cardNumber = str_replace(' ', '', $_POST['card_number'] ?? '');
  $cardExpiry = explode('/', $_POST['card_expiry'] ?? '');
  $cardCvc = $_POST['card_cvc'] ?? '';
  $address = $_POST['adress'] ?? '';

  if (strlen($cardNumber) != 16 || !ctype_digit($cardNumber)) {
    $error = "Invalid card number";
  } elseif (count($cardExpiry) != 2 || !checkdate($cardExpiry[0], 1, $cardExpiry[1])) {
    $error = "Invalid expiration date (use MM/YY format)";
  } elseif (strlen($cardCvc) < 3 || strlen($cardCvc) > 4 || !ctype_digit($cardCvc)) {
    $error = "Invalid CVC code";
  } else {
    try {
      $conn->beginTransaction();
      $stmt = $conn->prepare("
                INSERT INTO orders (user_id, order_date, total_amount, status, payment_method, shipping_address) 
                VALUES (:user_id, NOW(), :total, 'processing', :payment_method, :adress)
            ");
      $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':total' => $order['total'],
        ':payment_method' => 'Credit Card',
        ':adress' => $address
      ]);
      $orderId = $conn->lastInsertId();
      foreach ($order['cart_items'] as $item) {
        $product = $item['product'];

        $stmt = $conn->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price)
                    VALUES (:order_id, :product_id, :quantity, :price)
                ");
        $stmt->execute([
          ':order_id' => $orderId,
          ':product_id' => $product['product_id'],
          ':quantity' => $item['quantity'],
          ':price' => $product['price']
        ]);
        $stmt = $conn->prepare("
                    UPDATE products SET quantity = quantity - :quantity 
                    WHERE product_id = :product_id
                ");
        $stmt->execute([
          ':quantity' => $item['quantity'],
          ':product_id' => $product['product_id']
        ]);
      }

      $conn->commit();
      unset($_SESSION['cart']);
      unset($_SESSION['pending_order']);
      $_SESSION['order_success'] = $orderId;
      header("Location: order_confirmation.php");
      exit;

    } catch (PDOException $e) {
      if ($conn->inTransaction()) {
        $conn->rollBack();
      }
      $error = "Payment processing failed. Please try again.";
      error_log("Payment processing error: " . $e->getMessage());
    }
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
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="payment.css">
  <link rel="stylesheet" href="cart.css">
</head>

<body>
  <?php include 'header.php'; ?>

  <div class="payment-container">
    <div class="cart-header">
    <h1 class="cart-title">Your Shopping Cart</h1>
    <div class="cart-steps">
      <div class="step ">
        <span class="step-number">1</span>
      </div>
      <div class="step active">
        <span class="step-number">2</span>
      </div>
      <div class="step ">
        <span class="step-number">3</span>
      </div>
    </div>
  </div>

    <div class="payment-content">
      <div class="payment-form-container">
        <h2 class="payment-title">Payment Information</h2>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="payment-form">
          <div class="form-group">
            <label for="card_name">Name on Card</label>
            <input type="text" id="card_name" name="card_name" required>
          </div>

          <div class="form-group">
            <label for="card_number">Card Number</label>
            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required
              pattern="[\d ]{16,19}" maxlength="19" oninput="formatCardNumber(this)">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="card_expiry">Expiration Date</label>
              <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" required pattern="\d{2}/\d{2}"
                maxlength="5" oninput="formatExpiry(this)">
            </div>

            <div class="form-group">
              <label for="card_cvc">CVC</label>
              <input type="text" id="card_cvc" name="card_cvc" placeholder="123" required pattern="\d{3,4}"
                maxlength="4">
            </div>
          </div>
          <div class="form-group">
            <label for="adress">Shipping Adress</label>
            <input type="text" id="adress" name="adress" placeholder="123 Main St, City, State, ZIP" required>
          </div>

          <div class="order-summary">
            <div class="summary-row">
              <span>Subtotal (<?= count($order['cart_items']) ?> items)</span>
              <span>$<?= number_format($order['subtotal'], 2) ?></span>
            </div>
            <div class="summary-row">
              <span>Shipping</span>
              <span>$<?= number_format($order['shipping'], 2) ?></span>
            </div>
            <div class="summary-row summary-total">
              <span>Total</span>
              <span>$<?= number_format($order['total'], 2) ?></span>
            </div>
          </div>

          <button type="submit" name="process_payment" class="payment-btn">
            Complete Payment
          </button>
        </form>
      </div>

      <div class="payment-security">
        <div class="security-info">
          <i class="fas fa-lock"></i>
          <p>Secure SSL Encryption</p>
        </div>
        <div class="accepted-cards">
          <img src="https://download.logo.wine/logo/Visa_Inc./Visa_Inc.-Logo.wine.png" alt="Visa">
          <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Mastercard-logo.png" alt="Mastercard">
          <img
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/American_Express_logo_%282018%29.svg/2052px-American_Express_logo_%282018%29.svg.png"
            alt="American Express">
        </div>
      </div>
    </div>
  </div>


  <script>
    function formatCardNumber(input) {
      let value = input.value.replace(/\D/g, '');
      value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
      input.value = value.substring(0, 19);
    }

    function formatExpiry(input) {
      let value = input.value.replace(/\D/g, '');

      if (value.length > 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
      }

      input.value = value.substring(0, 5);
    }
  </script>
</body>

</html>
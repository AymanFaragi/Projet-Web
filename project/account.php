<?php

include 'config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$orderStmt = $conn->prepare("
    SELECT o.order_id, o.order_date, o.total_amount, o.status,
           oi.order_item_id, oi.quantity, oi.price,
           p.product_id, p.name, p.image_url
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.user_id = :user_id
    ORDER BY o.order_date DESC
");
$orderStmt->bindParam(':user_id', $_SESSION['user_id']);
$orderStmt->execute();
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

$groupedOrders = [];
foreach ($orders as $item) {
  $orderId = $item['order_id'];
  if (!isset($groupedOrders[$orderId])) {
    $groupedOrders[$orderId] = [
      'order_id' => $item['order_id'],
      'order_date' => $item['order_date'],
      'total_amount' => $item['total_amount'],
      'status' => $item['status'],
      'items' => []
    ];
  }
  $groupedOrders[$orderId]['items'][] = [
    'product_id' => $item['product_id'],
    'name' => $item['name'],
    'image_url' => $item['image_url'],
    'quantity' => $item['quantity'],
    'price' => $item['price']
  ];
}


$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  $firstName = trim($_POST['first_name']);
  $lastName = trim($_POST['last_name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $address = trim($_POST['address']);
  $city = trim($_POST['city']);
  $state = trim($_POST['state']);
  $zipCode = trim($_POST['zip_code']);
  $country = trim($_POST['country']);

  try {
    $updateStmt = $conn->prepare("
            UPDATE users SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                country = :country
            WHERE user_id = :user_id
        ");

    $updateStmt->execute([
      ':first_name' => $firstName,
      ':last_name' => $lastName,
      ':email' => $email,
      ':phone' => $phone,
      ':address' => $address,
      ':city' => $city,
      ':state' => $state,
      ':zip_code' => $zipCode,
      ':country' => $country,
      ':user_id' => $_SESSION['user_id']
    ]);

    $message = "Profile updated successfully!";
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
  } catch (PDOException $e) {
    $error = "Error updating profile: " . $e->getMessage();
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_picture'])) {
  $uploadResult = handleProfilePictureUpload();

  if ($uploadResult['success']) {
    if (!empty($user['profile_picture']) && $user['profile_picture'] !== 'images/default-profile.png') {
      @unlink($user['profile_picture']);
    }

    $updateStmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE user_id = :user_id");
    $updateStmt->execute([
      ':profile_picture' => $uploadResult['file_path'],
      ':user_id' => $_SESSION['user_id']
    ]);

    $message = "Profile picture updated successfully!";
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
  } else {
    $error = $uploadResult['error'];
  }
}

function handleProfilePictureUpload()
{
  if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    return ['success' => false, 'error' => 'Please select a valid image file'];
  }

  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
  $fileType = $_FILES['profile_picture']['type'];

  if (!in_array($fileType, $allowedTypes)) {
    return ['success' => false, 'error' => 'Only JPG, PNG, and GIF images are allowed'];
  }

  if ($_FILES['profile_picture']['size'] > 2097152) {
    return ['success' => false, 'error' => 'File size must be less than 2MB'];
  }

  $uploadDir = 'uploads/profile_pictures/';
  if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $fileName = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
  $targetPath = $uploadDir . $fileName;

  if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
    return ['success' => true, 'file_path' => $targetPath];
  }

  return ['success' => false, 'error' => 'Failed to upload image'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $currentPassword = $_POST['current_password'];
  $newPassword = $_POST['new_password'];
  $confirmPassword = $_POST['confirm_password'];

  if (!password_verify($currentPassword, $user['password'])) {
    $error = "Current password is incorrect";
  } elseif ($newPassword !== $confirmPassword) {
    $error = "New passwords don't match";
  } elseif (strlen($newPassword) < 8) {
    $error = "Password must be at least 8 characters";
  } else {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
    $updateStmt->execute([
      ':password' => $hashedPassword,
      ':user_id' => $_SESSION['user_id']
    ]);
    $message = "Password changed successfully!";
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
  $confirmPassword = $_POST['confirm_password_delete'];

  if (!password_verify($confirmPassword, $user['password'])) {
    $error = "Incorrect password. Account not deleted.";
  } else {
    try {
      $conn->beginTransaction();

      $getOrders = $conn->prepare("SELECT order_id FROM orders WHERE user_id = :user_id");
      $getOrders->execute([':user_id' => $_SESSION['user_id']]);
      $orderIds = $getOrders->fetchAll(PDO::FETCH_COLUMN);

      if ($orderIds) {
        $inQuery = implode(',', array_fill(0, count($orderIds), '?'));
        $deleteItems = $conn->prepare("DELETE FROM order_items WHERE order_id IN ($inQuery)");
        $deleteItems->execute($orderIds);

        $deleteOrders = $conn->prepare("DELETE FROM orders WHERE order_id IN ($inQuery)");
        $deleteOrders->execute($orderIds);
      }

      $deleteWishlist = $conn->prepare("DELETE FROM wishlist WHERE user_id = :user_id");
      $deleteWishlist->execute([':user_id' => $_SESSION['user_id']]);

      $deleteUser = $conn->prepare("DELETE FROM users WHERE user_id = :user_id");
      $deleteUser->execute([':user_id' => $_SESSION['user_id']]);

      $conn->commit();

      session_destroy();
      header("Location: index.php?account_deleted=1");
      exit;
    } catch (PDOException $e) {
      $conn->rollBack();
      $error = "Error deleting account: " . $e->getMessage();
    }

  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
  $order_id = $_POST['order_id'];
  $reason = $_POST['reason'] ?? 'No reason provided';

  try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled', cancellation_reason = ? WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$reason, $order_id, $_SESSION['user_id']]);

    $conn->commit();
    $_SESSION['success'] = "Order #$order_id has been cancelled successfully!";
    header("Location: account.php#purchases");
    exit;
  } catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['error'] = "Error cancelling order: " . $e->getMessage();
    header("Location: account.php#purchases");
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_order'])) {
  $order_id = $_POST['order_id'];
  $reason = $_POST['reason'] ?? 'No reason provided';

  try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE orders SET status = 'Returned', return_reason = ? WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$reason, $order_id, $_SESSION['user_id']]);

    $conn->commit();
    $_SESSION['success'] = "Return request for order #$order_id has been submitted!";
    header("Location: account.php#purchases");
    exit;
  } catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['error'] = "Error processing return: " . $e->getMessage();
    header("Location: account.php#purchases");
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel=" stylesheet" href="index.css">
  <link rel="stylesheet" href="account.css">
</head>

<body>
  <?php include 'header.php'; ?>

  <div class="account-container">
    <div class="account-header">
      <h1 class="account-title">My Account</h1>
      <div>Welcome back, <?= htmlspecialchars($user['first_name']) ?>!</div>
    </div>

    <div class="account-sections">
      <div class="account-nav">
        <ul>
          <li><a href="#profile" class="active"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="#purchases"><i class="fas fa-shopping-bag"></i> Purchase History</a></li>
          <li><a href="#password"><i class="fas fa-lock"></i> Change Password</a></li>
          <li><a href="#delete"><i class="fas fa-exclamation-triangle"></i> Delete Account</a></li>
        </ul>
      </div>

      <div class="account-content">
        <?php if ($message): ?>
          <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section id="profile">
          <h2 class="section-title">Profile Information</h2>

          <div class="profile-picture-section">
            <div class="profile-picture-upload">
              <div class="preview-container">
                <img id="profile-preview"
                  src="<?= htmlspecialchars($user['profile_picture'] ?? 'images/default-profile.png') ?>"
                  alt="Profile Preview" class="profile-preview">
                <div class="upload-overlay">
                  <i class="fas fa-camera"></i>
                  <span>Change Photo</span>
                </div>
              </div>
            </div>

            <form method="POST" action="account.php#profile" enctype="multipart/form-data" class="profile-picture-form">
              <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input">
              <button type="submit" name="update_profile_picture" class="btn btn-secondary">
                Update Profile Picture
              </button>
              <div class="form-note">
                Recommended size: 500x500px. Max file size: 2MB.
              </div>
            </form>
          </div>
          <form method="POST" action="account.php#profile">
            <div class="form-grid">
              <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control"
                  value="<?= htmlspecialchars($user['first_name']) ?>" required>
              </div>

              <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control"
                  value="<?= htmlspecialchars($user['last_name']) ?>" required>
              </div>

              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                  value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>

              <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                  value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
              </div>

              <div class="form-group full-width">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" class="form-control"
                  value="<?= htmlspecialchars($user['address'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" class="form-control"
                  value="<?= htmlspecialchars($user['city'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label for="state">State/Province</label>
                <input type="text" id="state" name="state" class="form-control"
                  value="<?= htmlspecialchars($user['state'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label for="zip_code">ZIP/Postal Code</label>
                <input type="text" id="zip_code" name="zip_code" class="form-control"
                  value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" class="form-control"
                  value="<?= htmlspecialchars($user['country'] ?? '') ?>">
              </div>

              <div class="form-group full-width">
                <button type="submit" name="update_profile" class="btn btn-primary">
                  Update Profile
                </button>
              </div>
            </div>
          </form>
        </section>

        <section id="purchases" class="purchase-history">
          <h2 class="section-title">Purchase History</h2>

          <?php if (!empty($groupedOrders)): ?>
            <?php foreach ($groupedOrders as $order): ?>
              <div class="order-card">
                <div class="order-header">
                  <div class="order-meta">
                    <h3>Order #<?= $order['order_id'] ?></h3>
                    <div class="order-date-status">
                      <span><?= date('M j, Y', strtotime($order['order_date'])) ?></span>
                      <span class="status-badge <?= strtolower($order['status']) ?>">
                        <?= ucfirst($order['status']) ?>
                      </span>
                    </div>
                  </div>
                  <div class="order-total">
                    Total: $<?= number_format($order['total_amount'], 2) ?>
                  </div>
                </div>

                <div class="order-items">
                  <?php foreach ($order['items'] as $item): ?>
                    <div class="order-item">
                      <div class="item-image">
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                      </div>
                      <div class="item-info">
                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                        <div class="item-meta">
                          <span>Qty: <?= $item['quantity'] ?></span>
                          <span>$<?= number_format($item['price'], 2) ?> each</span>
                        </div>
                      </div>
                      <div class="item-total">
                        $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="order-actions">
                  <?php if ($order['status'] === 'completed'): ?>
                    <button type="button" class="btn btn-warning" onclick="showReturnModal(<?= $order['order_id'] ?>)">
                      <i class="fas fa-undo"></i> Return Order
                    </button>
                  <?php elseif ($order['status'] !== 'shipped' && $order['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-danger" onclick="showCancelModal(<?= $order['order_id'] ?>)">
                      <i class="fas fa-times"></i> Cancel Order
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="margin-bottom: 20px;">You haven't made any purchases yet.</p>
            <a href="products.php" class="btn btn-primary">Browse Products</a>
          <?php endif; ?>
        </section>

        <section id="password">
          <h2 class="section-title">Change Password</h2>
          <form method="POST" action="account.php#password">
            <div class="form-grid">
              <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
              </div>

              <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required
                  minlength="8">
              </div>

              <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                  minlength="8">
              </div>

              <div class="form-group full-width">
                <button type="submit" name="change_password" class="btn btn-primary">
                  Change Password
                </button>
              </div>
            </div>
          </form>
        </section>

        <section id="delete" class="danger-zone">
          <h2 class="section-title">Delete Account</h2>
          <div class="alert alert-danger">
            <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
          </div>

          <form method="POST" action="account.php#delete"
            onsubmit="return confirm('Are you absolutely sure you want to delete your account? This cannot be undone.');">
            <div class="form-group">
              <label for="confirm_password_delete">Enter your password to confirm</label>
              <input type="password" id="confirm_password_delete" name="confirm_password_delete" class="form-control"
                required>
            </div>

            <button type="submit" name="delete_account" class="btn btn-danger">
              <i class="fas fa-exclamation-triangle"></i> Permanently Delete My Account
            </button>
          </form>
        </section>
      </div>
    </div>
  </div>
  <div id="cancelModal" class="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cancel Order</h5>
          <button type="button" class="close" onclick="closeModal('cancelModal')">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="account.php#purchases">
          <input type="hidden" name="order_id" id="cancel_order_id">
          <div class="modal-body">
            <div class="form-group">
              <label for="cancel_reason">Reason for Cancellation</label>
              <textarea id="cancel_reason" name="reason" class="form-control" rows="4" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('cancelModal')">
              <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" name="cancel_order" class="btn btn-danger">
              <i class="fas fa-check"></i> Confirm Cancellation
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div id="returnModal" class="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Return Order</h5>
          <button type="button" class="close" onclick="closeModal('returnModal')">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="account.php#purchases">
          <input type="hidden" name="order_id" id="return_order_id">
          <div class="modal-body">
            <div class="form-group">
              <label for="return_reason">Reason for Return</label>
              <textarea id="return_reason" name="reason" class="form-control" rows="4" required></textarea>
            </div>
            <div class="form-group">
              <label for="return_instructions">Return Instructions</label>
              <p class="small-text">Please package the items securely and ship them to our return center at:</p>
              <address>
                sKYS Tech Returns<br>
                123 Return Street<br>
                Tech City, TC 12345
              </address>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('returnModal')">
              <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" name="return_order" class="btn btn-warning">
              <i class="fas fa-check"></i> Submit Return Request
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('.account-nav a').forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();

        document.querySelectorAll('.account-nav a').forEach(a => {
          a.classList.remove('active');
        });

        this.classList.add('active');

        const targetId = this.getAttribute('href');
        document.querySelectorAll('section').forEach(section => {
          section.style.display = 'none';
        });
        document.querySelector(targetId).style.display = 'block';
      });
    });


    document.querySelector('section').style.display = 'block';
    document.getElementById('profile_picture')?.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (event) {
          document.getElementById('profile-preview').src = event.target.result;
        }
        reader.readAsDataURL(file);
      }
    });

    document.querySelector('.upload-overlay')?.addEventListener('click', function () {
      document.getElementById('profile_picture').click();
    });
    function showCancelModal(orderId) {
      document.getElementById('cancel_order_id').value = orderId;
      document.getElementById('cancelModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function showReturnModal(orderId) {
      document.getElementById('return_order_id').value = orderId;
      document.getElementById('returnModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    window.addEventListener('click', function (event) {
      if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeModal('cancelModal');
        closeModal('returnModal');
      }
    });
  </script>
</body>

</html>
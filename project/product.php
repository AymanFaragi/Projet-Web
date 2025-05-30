<?php
include 'config.php';
include 'header.php';

if (!isset($_GET['id'])) {
  header("Location: products.php");
  exit;
}

$productId = intval($_GET['id']);

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = :id");
$stmt->bindParam(':id', $productId, PDO::PARAM_INT);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
  header("Location: products.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=wishlist");
    exit;
  }

  $userId = $_SESSION['user_id'];
  $productId = (int) $_POST['product_id'];

  $checkStmt = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
  $checkStmt->execute([$userId, $productId]);

  if (!$checkStmt->fetch()) {
    $insertStmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    if ($insertStmt->execute([$userId, $productId])) {
      $successMessage = "Item added to your wishlist.";
    } else {
      $errorMessage = "Failed to add item to wishlist.";
    }
  } else {
    $errorMessage = "Item is already in your wishlist.";
  }

  header("Location: product.php?id=$productId");
  exit;
}

$wishlistItems = [];
$wishlistCount = 0;
if (isset($_SESSION['user_id'])) {
  $wishlistStmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
  $wishlistStmt->execute([$_SESSION['user_id']]);
  $wishlistItems = $wishlistStmt->fetchAll(PDO::FETCH_COLUMN);
  $wishlistCount = count($wishlistItems);
}

$stmt = $conn->prepare("SELECT * FROM products WHERE category = :category AND product_id != :id LIMIT 4");
$stmt->bindParam(':category', $product['category']);
$stmt->bindParam(':id', $productId, PDO::PARAM_INT);
$stmt->execute();
$relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<head>
  <link rel="stylesheet" href="product.css">
</head>

<div class="product-detail-container">
  <?php if (isset($successMessage)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
  <?php endif; ?>

  <?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
  <?php endif; ?>

  <div class="product-detail-grid">
    <div class="product-gallery">
      <div class="main-image">
        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
      </div>
    </div>

    <div class="product-info">
      <h1><?= htmlspecialchars($product['name']) ?></h1>
      <div class="product-meta">
        <span class="brand">Brand: <?= htmlspecialchars($product['brand']) ?></span>
        <span class="category">Category: <?= ucfirst($product['category']) ?></span>
      </div>

      <div class="price-section">
        <?php if ($product['discount'] > 0): ?>
          <span class="current-price">$<?= number_format($product['price'] * (1 - $product['discount'] / 100), 2) ?></span>
          <span class="original-price">$<?= number_format($product['price'], 2) ?></span>
          <span class="discount-percent">Save <?= $product['discount'] ?>%</span>
        <?php else: ?>
          <span class="current-price">$<?= number_format($product['price'], 2) ?></span>
        <?php endif; ?>
      </div>

      <div class="stock-status">
        <?php if ($product['quantity'] > 0): ?>
          <span class="in-stock"><i class="fas fa-check-circle"></i> In Stock (<?= $product['quantity'] ?>
            available)</span>
        <?php else: ?>
          <span class="out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
        <?php endif; ?>
      </div>

      <div class="product-actions">
        <button class="add-to-cart primary-btn" data-product-id="<?= $product['product_id'] ?>">
          <i class="fas fa-shopping-cart"></i> Add to Cart
        </button>

        <button class="wishlist-btn <?= in_array($product['product_id'], $wishlistItems) ? 'in-wishlist' : '' ?>"
          data-product-id="<?= $product['product_id'] ?>">
          <i class="<?= in_array($product['product_id'], $wishlistItems) ? 'fas' : 'far' ?> fa-heart"></i>
        </button>
      </div>

      <div class="product-description">
        <h3>Description</h3>
        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
      </div>

      <div class="product-specs">
        <h3>Specifications</h3>
        <div class="specs-content">
          <?= nl2br(htmlspecialchars($product['specs'])) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="related-products">
    <h2>You May Also Like</h2>
    <div class="related-products-grid">
      <?php foreach ($relatedProducts as $related): ?>
        <a href="product.php?id=<?= $related['product_id'] ?>" class="related-product">
          <img src="<?= htmlspecialchars($related['image_url']) ?>" alt="<?= htmlspecialchars($related['name']) ?>">
          <h4><?= htmlspecialchars($related['name']) ?></h4>
          <div class="price">$<?= number_format($related['price'], 2) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.add-to-cart').forEach(button => {
      button.addEventListener('click', function (e) {
        e.preventDefault();
        const productId = this.getAttribute('data-product-id');
        const button = this;
        const cartIcon = this.querySelector('i');

        button.disabled = true;
        cartIcon.classList.remove('fa-shopping-cart');
        cartIcon.classList.add('fa-spinner', 'fa-spin');

        fetch('add_to_cart.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `product_id=${productId}&quantity=1`
        })
          .then(response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
              return response.text().then(text => {
                throw new Error(`Invalid response: ${text}`);
              });
            }
            return response.json();
          })
          .then(data => {
            button.disabled = false;
            cartIcon.classList.remove('fa-spinner', 'fa-spin');
            cartIcon.classList.add('fa-shopping-cart');

            if (data.success) {
              const cartCountElements = document.querySelectorAll('.cart-count');
              cartCountElements.forEach(el => {
                el.textContent = data.cart_count;
              });
              const headerCartIcon = document.querySelector('.cart-link i');
              if (headerCartIcon) {
                headerCartIcon.classList.add('cart-pulse');
                setTimeout(() => {
                  headerCartIcon.classList.remove('cart-pulse');
                }, 500);
              }
              showCartNotification('success', data.message);
              button.classList.add('cart-btn-pulse');
              setTimeout(() => {
                button.classList.remove('cart-btn-pulse');
              }, 500);
            } else {
              if (data.message.includes('log in')) {
                window.location.href = 'login.php?redirect=products.php';
              } else {
                showCartNotification('error', data.message);
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);
            button.disabled = false;
            cartIcon.classList.remove('fa-spinner', 'fa-spin');
            cartIcon.classList.add('fa-shopping-cart');
            showCartNotification('error', 'An error occurred. Please try again.');
          });
      });
    });

    function showCartNotification(type, message) {
      const existing = document.querySelector('.cart-notification');
      if (existing) {
        existing.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => existing.remove(), 300);
      }

      const notification = document.createElement('div');
      notification.className = `cart-notification ${type}`;

      let iconClass = 'fa-info-circle';
      if (type === 'success') iconClass = 'fa-check-circle';
      if (type === 'error') iconClass = 'fa-exclamation-circle';

      notification.innerHTML = `
        <i class="fas ${iconClass}"></i>
        <span>${message}</span>
    `;

      document.body.appendChild(notification);

      setTimeout(() => {
        notification.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }

    document.querySelectorAll('.wishlist-btn').forEach(button => {
      button.addEventListener('click', function (e) {
        e.preventDefault();
        const productId = this.getAttribute('data-product-id');
        const button = this;
        const heartIcon = this.querySelector('i');

        button.disabled = true;
        heartIcon.classList.add('fa-spinner', 'fa-spin');
        heartIcon.classList.remove('far', 'fas');

        fetch('add_to_wishlist.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `product_id=${productId}`
        })
          .then(response => response.json())
          .then(data => {
            button.disabled = false;
            heartIcon.classList.remove('fa-spinner', 'fa-spin');

            if (data.success) {
              if (data.action === 'added') {
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
                button.classList.add('in-wishlist');
              }
              else {
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
                button.classList.remove('in-wishlist');
              }

              heartIcon.classList.add('wishlist-pulse');
              setTimeout(() => {
                heartIcon.classList.remove('wishlist-pulse');
              }, 500);

              showWishlistNotification(data.action, data.message);

              if (data.wishlist_count !== undefined) {
                const wishlistCountElement = document.querySelector('.wishlist-count');
                if (wishlistCountElement) {
                  wishlistCountElement.textContent = data.wishlist_count;
                }
              }
            }
            else {
              if (data.message.includes('log in')) {
                window.location.href = 'login.php?redirect=products.php';
              } else {
                showWishlistNotification('error', data.message);
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);
            button.disabled = false;
            heartIcon.classList.remove('fa-spinner', 'fa-spin');
            heartIcon.classList.add('far');
            showWishlistNotification('error', 'An error occurred');
          });
      });
    });

    function showWishlistNotification(action, message) {
      const existing = document.querySelector('.wishlist-notification');
      if (existing) {
        existing.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => existing.remove(), 300);
      }
      const notification = document.createElement('div');
      notification.className = 'wishlist-notification';

      let iconClass = 'fa-info-circle';
      if (action === 'added') iconClass = 'fa-heart';
      if (action === 'removed') iconClass = 'fa-heart-broken';
      if (action === 'error') iconClass = 'fa-exclamation-circle';

      notification.innerHTML = `
          <i class="fas ${iconClass}"></i>
          <span>${message}</span>
        `;

      document.body.appendChild(notification);
      setTimeout(() => {
        notification.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }
  });
</script>
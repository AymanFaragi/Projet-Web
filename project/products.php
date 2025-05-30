<?php
include 'config.php';
include 'header.php';
$conn = getDBConnection();

$category = isset($_POST['category']) ? $_POST['category'] : (isset($_GET['category']) ? $_GET['category'] : null);
$brand = isset($_POST['brand']) ? $_POST['brand'] : (isset($_GET['brand']) ? $_GET['brand'] : null);
$min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : (isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0);
$max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : (isset($_GET['max_price']) ? floatval($_GET['max_price']) : PHP_INT_MAX);
$search = isset($_POST['search']) ? $_POST['search'] : (isset($_GET['search']) ? $_GET['search'] : null);

$sql = "SELECT * FROM products WHERE price BETWEEN :min_price AND :max_price";
$params = [
  ':min_price' => $min_price,
  ':max_price' => $max_price
];

if ($category) {
  $sql .= " AND category = :category";
  $params[':category'] = $category;
}

if ($brand) {
  $sql .= " AND brand = :brand";
  $params[':brand'] = $brand;
}

if ($search) {
  $sql .= " AND (name LIKE :search OR description LIKE :search)";
  $params[':search'] = "%$search%";
}

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
  $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $conn->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$brands = $conn->query("SELECT DISTINCT brand FROM products ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
$priceRange = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products")->fetch(PDO::FETCH_ASSOC);

$wishlistItems = [];
$wishlistCount = 0;
if (isset($_SESSION['user_id'])) {
  $wishlistStmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
  $wishlistStmt->execute([$_SESSION['user_id']]);
  $wishlistItems = $wishlistStmt->fetchAll(PDO::FETCH_COLUMN);
  $wishlistCount = count($wishlistItems);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>sKYS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.5.0/nouislider.min.css">
  <link rel="stylesheet" href="products.css">
</head>

<body>

  <div class="products-page-container">
    <div class="products-header">
      <h1>Our Products</h1>
      <div class="products-controls">
        <form method="GET" class="search-form">
          <input type="text" name="search" placeholder="Search products..."
            value="<?= htmlspecialchars($search ?? '') ?>">
          <button type="submit"><i class="fas fa-search"></i></button>
        </form>
      </div>
    </div>

    <div class="products-layout">
      <div class="products-filters">
        <form method="GET" id="filter-form">
          <input type="hidden" name="search" value="<?= htmlspecialchars($search ?? '') ?>">

          <div class="filter-section">
            <h3>Price Range</h3>
            <div id="price-slider" class="price-slider-container"></div>
            <div class="price-inputs">
              <div class="price-input-group">
                <label>Min</label>
                <input type="number" name="min_price" id="min-price-input" value="<?= $min_price ?>"
                  min="<?= $priceRange['min_price'] ?>" max="<?= $priceRange['max_price'] ?>">
              </div>
              <div class="price-input-group">
                <label>Max</label>
                <input type="number" name="max_price" id="max-price-input" value="<?= $max_price ?>"
                  min="<?= $priceRange['min_price'] ?>" max="<?= $priceRange['max_price'] ?>">
              </div>
            </div>
          </div>

          <div class="filter-section">
            <h3>Categories</h3>
            <div class="filter-options">
              <div class="filter-option">
                <input type="radio" id="category-all" name="category" value="" <?= !$category ? 'checked' : '' ?>>
                <label for="category-all">All Categories</label>
              </div>
              <?php foreach ($categories as $cat): ?>
                <div class="filter-option">
                  <input type="radio" id="category-<?= htmlspecialchars($cat) ?>" name="category" 
                    value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'checked' : '' ?>>
                  <label for="category-<?= htmlspecialchars($cat) ?>"><?= ucfirst($cat) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="filter-section">
            <h3>Brands</h3>
            <div class="filter-options">
              <div class="filter-option">
                <input type="radio" id="brand-all" name="brand" value="" <?= !$brand ? 'checked' : '' ?>>
                <label for="brand-all">All Brands</label>
              </div>
              <?php foreach ($brands as $br): ?>
                <div class="filter-option">
                  <input type="radio" id="brand-<?= htmlspecialchars($br) ?>" name="brand" 
                    value="<?= htmlspecialchars($br) ?>" <?= ($brand === $br) ? 'checked' : '' ?>>
                  <label for="brand-<?= htmlspecialchars($br) ?>"><?= ucfirst($br) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="filter-actions">
            <button type="submit" class="apply-filters">
              <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="products.php" class="clear-filters">
              <i class="fas fa-times"></i> Clear All
            </a>
          </div>
        </form>
      </div>

      <div class="products-grid-container">
        <?php if (count($products) > 0): ?>
          <div class="products-grid">
            <?php foreach ($products as $product): ?>
              <div class="product-item">
                <a href="product.php?id=<?= $product['product_id'] ?>" class="product-link">
                  <div class="product-image">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>"
                      alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php if ($product['discount'] > 0): ?>
                      <span class="discount-badge">-<?= $product['discount'] ?>%</span>
                    <?php endif; ?>
                  </div>
                  <div class="product-info">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="product-brand"><?= htmlspecialchars($product['brand']) ?></p>
                    <p class="product-category"><?= ucfirst($product['category']) ?></p>
                    <p class="product-description"><?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...</p>
                  </div>
                </a>
                <div class="product-details">
                  <div class="product-price">
                    <?php if ($product['discount'] > 0): ?>
                      <span
                        class="current-price">$<?= number_format($product['price'] * (1 - $product['discount'] / 100), 2) ?></span>
                      <span class="original-price">$<?= number_format($product['price'], 2) ?></span>
                    <?php else: ?>
                      <span class="current-price">$<?= number_format($product['price'], 2) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="product-stock">
                    <?php if ($product['quantity'] > 0): ?>
                      <span class="in-stock"><i class="fas fa-check-circle"></i> In Stock</span>
                    <?php else: ?>
                      <span class="out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
                    <?php endif; ?>
                  </div>
                  <div class="product-actions">
                    <button class="add-to-cart" data-product-id="<?= $product['product_id'] ?>">
                      <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                    <button
                      class="wishlist-btn <?= in_array($product['product_id'], $wishlistItems) ? 'in-wishlist' : '' ?>"
                      data-product-id="<?= $product['product_id'] ?>">
                      <i class="<?= in_array($product['product_id'], $wishlistItems) ? 'fas' : 'far' ?> fa-heart"></i>
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-products">
            <i class="fas fa-box-open"></i>
            <p>No products found matching your filters. Please try different criteria.</p>
            <a href="products.php" class="btn">Clear Filters</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.5.0/nouislider.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const priceSlider = document.getElementById('price-slider');
      const minPriceInput = document.getElementById('min-price-input');
      const maxPriceInput = document.getElementById('max-price-input');
      const filterForm = document.getElementById('filter-form');

      noUiSlider.create(priceSlider, {
        start: [<?= $min_price ?>, <?= $max_price ?>],
        connect: true,
        range: {
          'min': <?= $priceRange['min_price'] ?>,
          'max': <?= $priceRange['max_price'] ?>
        },
        step: 1,
        tooltips: [true, true],
        format: {
          to: function (value) {
            return Math.round(value);
          },
          from: function (value) {
            return Number(value);
          }
        }
      });

      priceSlider.noUiSlider.on('update', function (values, handle) {
        const value = values[handle];
        if (handle) {
          maxPriceInput.value = value;
        }
        else {
          minPriceInput.value = value;
        }
      });

      minPriceInput.addEventListener('change', function () {
        priceSlider.noUiSlider.set([this.value, null]);
      });

      maxPriceInput.addEventListener('change', function () {
        priceSlider.noUiSlider.set([null, this.value]);
      });

      document.querySelectorAll('.filter-option input').forEach(input => {
        input.addEventListener('click', function(e) {
        });
      });

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
              }
              else {
                if (data.message.includes('log in')) {
                  window.location.href = 'login.php?redirect=products.php';
                }
                else {
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
                } else {
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
              } else {
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
</body>

</html>
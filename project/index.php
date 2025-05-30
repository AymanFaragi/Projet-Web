<?php
include 'config.php';
include 'header.php';

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM products WHERE featured = 1 LIMIT 4");
$stmt->execute();
$featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-banners">
  <div class="product-banner banner-1">
    <div class="product-content">
      <span class="product-tag">NEW RELEASE</span>
      <h1>SAMSUNG GALAXY S25 ULTRA</h1>
      <p class="product-description">Experience the future with our revolutionary smartphone featuring an advanced camera system, lightning-fast processor, and all-day battery life.</p>
      <div class="price-tag">$1,199 <span class="original-price">$1,399</span></div>
      <div class="button-group">
        <a href="/product.php?id=1"><button class="secondary-btn"><i class="fas fa-info-circle"></i> Learn More</button></a>
      </div>
      <div class="product-highlights">
        <div class="highlight"><i class="fas fa-camera"></i> 200MP Camera</div>
        <div class="highlight"><i class="fas fa-bolt"></i> Snapdragon 8 Gen 3</div>
        <div class="highlight"><i class="fas fa-battery-full"></i> 5000mAh Battery</div>
      </div>
    </div>
  </div>
  
  <div class="product-banner banner-2">
    <div class="product-content">
      <span class="product-tag">PREMIUM</span>
      <h1>APPLE VISION PRO</h1>
      <p class="product-description">Step into the future of spatial computing with our most advanced AR headset featuring revolutionary displays and intuitive controls.</p>
      <div class="price-tag">$3,499</div>
      <div class="button-group">
        <a href="/product.php?id=2"><button class="secondary-btn"><i class="fas fa-info-circle"></i> Learn More</button></a>
      </div>
      <div class="product-highlights">
        <div class="highlight"><i class="fas fa-eye"></i> 4K Micro-OLED</div>
        <div class="highlight"><i class="fas fa-microchip"></i> M2 Ultra Chip</div>
        <div class="highlight"><i class="fas fa-hand-pointer"></i> Eye Tracking</div>
      </div>
    </div>
  </div>
</div>
<div class="product-cards-section">
  <h2 class="section-title">Featured Products</h2>
  <div class="product-cards-grid">
    <?php foreach ($featuredProducts as $product): ?>
      <div class="product-card">
        <div class="card-image" style="background-image: url('<?= htmlspecialchars($product['image_url']) ?>')"></div>
        <div class="card-badge">
          <?php if ($product['discount'] > 0): ?>
            <span class="discount-badge">-<?= $product['discount'] ?>%</span>
          <?php endif; ?>
        </div>
        <div class="card-content">
          <div class="card-details">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p class="card-description"><?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...</p>
          </div>
          <div class="card-price">
            <?php if ($product['discount'] > 0): ?>
              <span class="current-price">$<?= number_format($product['price'] * (1 - $product['discount']/100), 2) ?></span>
              <span class="original-price">$<?= number_format($product['price'], 2) ?></span>
            <?php else: ?>
              <span class="current-price">$<?= number_format($product['price'], 2) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-actions">
          <a href="product.php?id=<?= $product['product_id'] ?>" class="product-link">
            <button class="card-btn" data-product-id="<?= $product['product_id'] ?>">
              <i class="fas fa-eye"></i> 
            </button>
          </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
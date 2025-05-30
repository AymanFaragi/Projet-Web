<?php
include 'config.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=wishlist");
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    $productId = (int) $_POST['product_id'];

    $checkStmt = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $checkStmt->execute([$userId, $productId]);

    if (!$checkStmt->fetch()) {
        $insertStmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        if ($insertStmt->execute([$userId, $productId])) {
            $success = "Item added to your wishlist!";
        } else {
            $error = "Failed to add item to wishlist.";
        }
    } else {
        $error = "This item is already in your wishlist.";
    }

    header("Location: wishlist.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $productId = (int) $_POST['product_id'];

    $deleteStmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    if ($deleteStmt->execute([$userId, $productId])) {
        $success = "Item removed from your wishlist.";
    } else {
        $error = "Failed to remove item.";
    }

    header("Location: wishlist.php");
    exit;
}

$wishlistItems = [];
$stmt = $conn->prepare("
    SELECT p.product_id, p.name, p.price, p.image_url, p.quantity as stock
    FROM products p
    INNER JOIN wishlist w ON p.product_id = w.product_id
    WHERE w.user_id = ?
");
$stmt->execute([$userId]);
$wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>sKYS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="wishlist.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="wishlist-container">
        <div class="wishlist-header">
            <h1 class="wishlist-title">Your Wishlist</h1>
            <div class="wishlist-count">
                <?= count($wishlistItems) ?> <?= count($wishlistItems) === 1 ? 'item' : 'items' ?>
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

        <?php if (empty($wishlistItems)): ?>
            <div class="empty-wishlist">
            
                <div class="empty-wishlist-content">
                    <h2>Your wishlist is empty</h2>
                    <p class="empty-wishlist-message">Save items you love for later by clicking the heart icon</p>

                    <div class="empty-wishlist-actions">
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
            <div class="wishlist-items-container">
                <div class="wishlist-items">
                    <?php foreach ($wishlistItems as $item): ?>
                        <div class="wishlist-item">
                            <div class="wishlist-item-image">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                    alt="<?= htmlspecialchars($item['name']) ?>">
                                <form method="POST" action="wishlist.php" class="wishlist-remove-form">
                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                    <button type="submit" name="remove_item" class="wishlist-remove-btn"
                                        title="Remove from wishlist">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>

                            <div class="wishlist-item-details">
                                <h3 class="wishlist-item-name"><?= htmlspecialchars($item['name']) ?></h3>
                                <div class="wishlist-item-price">$<?= number_format($item['price'], 2) ?></div>

                                <?php if ($item['stock'] > 0): ?>
                                    <div class="wishlist-stock in-stock">
                                        <i class="fas fa-check-circle"></i> In Stock
                                    </div>
                                <?php else: ?>
                                    <div class="wishlist-stock out-of-stock">
                                        <i class="fas fa-exclamation-circle"></i> Out of Stock
                                    </div>
                                <?php endif; ?>

                                <div class="wishlist-item-actions">
                                    <form method="POST" action="add_to_cart.php" class="add-to-cart-form">
                                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="add-to-cart-btn">
                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>

<script>
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            const form = this.closest('form');
            const formData = new FormData(form);

            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCount = document.querySelector('.cart-count');
                        if (cartCount) {
                            cartCount.textContent = data.cart_count;
                        }
                        alert('Item added to cart successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while adding to cart.');
                });
        });
    });
</script>

</html>
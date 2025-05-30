v
<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $admin_id,
            'product_delete',
            "Deleted product: {$product['name']} (ID: $product_id)",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $_SESSION['success'] = "Product deleted successfully!";
        header("Location: admin_products.php");
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
        header("Location: admin_products.php");
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $product_ids = $_POST['product_ids'] ?? [];

    if (!empty($product_ids)) {
        try {
            $conn->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

            switch ($_POST['bulk_action']) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM products WHERE product_id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $action = 'product_bulk_delete';
                    $description = "Deleted " . count($product_ids) . " products";
                    $success_msg = count($product_ids) . " products deleted successfully!";
                    break;

                case 'feature':
                    $stmt = $conn->prepare("UPDATE products SET featured = 1 WHERE product_id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $action = 'product_bulk_feature';
                    $description = "Featured " . count($product_ids) . " products";
                    $success_msg = count($product_ids) . " products marked as featured!";
                    break;

                case 'unfeature':
                    $stmt = $conn->prepare("UPDATE products SET featured = 0 WHERE product_id IN ($placeholders)");
                    $stmt->execute($product_ids);
                    $action = 'product_bulk_unfeature';
                    $description = "Unfeatured " . count($product_ids) . " products";
                    $success_msg = count($product_ids) . " products unfeatured!";
                    break;

                default:
                    throw new Exception("Invalid bulk action");
            }

            $stmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $admin_id,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $conn->commit();
            $_SESSION['success'] = $success_msg;
            header("Location: admin_products.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error performing bulk action: " . $e->getMessage();
            header("Location: admin_products.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "No products selected for bulk action";
        header("Location: admin_products.php");
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $conn->beginTransaction();

        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $quantity = $_POST['quantity'];
        $category = $_POST['category'];
        $brand = $_POST['brand'];
        $discount = $_POST['discount'] ?? 0;
        $specs = $_POST['specs'] ?? '';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $image_url = 'images/default-product.png';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('product_') . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_url = $target_path;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO products (
                name, description, price, quantity, image_url, 
                category, brand, specs, discount, featured
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $description,
            $price,
            $quantity,
            $image_url,
            $category,
            $brand,
            $specs,
            $discount,
            $featured
        ]);

        $product_id = $conn->lastInsertId();

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $admin_id,
            'product_create',
            "Added new product: $name (ID: $product_id)",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $_SESSION['success'] = "Product added successfully!";
        header("Location: admin_products.php");
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error adding product: " . $e->getMessage();
        header("Location: admin_products.php");
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    try {
        $conn->beginTransaction();

        $product_id = $_POST['product_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $quantity = $_POST['quantity'];
        $category = $_POST['category'];
        $brand = $_POST['brand'];
        $discount = $_POST['discount'] ?? 0;
        $specs = $_POST['specs'] ?? '';
        $featured = isset($_POST['featured']) ? 1 : 0;

        $stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_image = $stmt->fetchColumn();
        $image_url = $current_image;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('product_') . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                if ($current_image && $current_image !== 'images/default-product.png') {
                    @unlink($current_image);
                }
                $image_url = $target_path;
            }
        }

        $stmt = $conn->prepare("
            UPDATE products SET
                name = ?, description = ?, price = ?, quantity = ?, image_url = ?,
                category = ?, brand = ?, specs = ?, discount = ?, featured = ?, updated_at = NOW()
            WHERE product_id = ?
        ");
        $stmt->execute([
            $name,
            $description,
            $price,
            $quantity,
            $image_url,
            $category,
            $brand,
            $specs,
            $discount,
            $featured,
            $product_id
        ]);

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $admin_id,
            'product_update',
            "Updated product: $name (ID: $product_id)",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $_SESSION['success'] = "Product updated successfully!";
        header("Location: admin_products.php");
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating product: " . $e->getMessage();
        header("Location: admin_products.php");
        exit;
    }
}


$edit_product = null;
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
}


$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$featured_filter = isset($_GET['featured']) ? (int) $_GET['featured'] : '';
$min_price = isset($_GET['min_price']) ? (float) $_GET['min_price'] : 0;


$maximum_price = $conn->query("SELECT MAX(price) FROM products")->fetchColumn();
$max_price = isset($_GET['max_price']) ? (float) $_GET['max_price'] : $maximum_price;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(name LIKE ? OR description LIKE ? OR brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where[] = "category = ?";
    $params[] = $category_filter;
}

if ($featured_filter !== '') {
    $where[] = "featured = ?";
    $params[] = $featured_filter;
}

if ($min_price > 0 || $max_price < PHP_INT_MAX) {
    $where[] = "price BETWEEN ? AND ?";
    $params[] = $min_price;
    $params[] = $max_price;
}
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";


$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products $where_clause");
$stmt->execute($params);
$total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $per_page);


$stmt = $conn->prepare("
    SELECT * FROM products 
    $where_clause 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->query("SELECT DISTINCT category FROM products");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);


$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel=" stylesheet" href="admin.css">
    <link rel="stylesheet" href="admin_products.css">
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-content">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">All Products</h3>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div class="filters-row">
                        <form method="GET" class="filter-form">
                            <div class="form-group">
                                <input type="text" name="search" placeholder="Search products..." class="form-control"
                                    value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="form-group">
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                            <?= ucfirst(htmlspecialchars($cat)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group price-range-group">
                                <label class="form-label">Price Range</label>
                                <div class="price-range-inputs">
                                    <div class="price-input">
                                        <span class="price-currency">$</span>
                                        <input type="number" name="min_price" placeholder="Min"
                                            class="form-control price-input-field" min="0" step="0.01"
                                            value="<?= htmlspecialchars($min_price) ?>">
                                    </div>
                                    <span class="price-range-separator">to</span>
                                    <div class="price-input">
                                        <span class="price-currency">$</span>
                                        <input type="number" name="max_price" placeholder="Max"
                                            class="form-control price-input-field" min="0" step="0.01"
                                            value="<?= htmlspecialchars($max_price) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <select name="featured" class="form-control">
                                    <option value="">All Products</option>
                                    <option value="1" <?= $featured_filter === 1 ? 'selected' : '' ?>>Featured Only
                                    </option>
                                    <option value="0" <?= $featured_filter === 0 ? 'selected' : '' ?>>Non-Featured</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-outline">
                                <i class="fas fa-filter"></i> Filter
                            </button>

                            <a href="admin_products.php" class="btn btn-outline">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </form>
                    </div>

                    <form method="POST" id="bulk-action-form">
                        <div class="bulk-actions">
                            <div class="form-group">
                                <select name="bulk_action" class="form-control" required>
                                    <option value="">Bulk Actions</option>
                                    <option value="delete">Delete Selected</option>
                                    <option value="feature">Mark as Featured</option>
                                    <option value="unfeature">Remove Featured</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline">
                                <i class="fas fa-check"></i> Apply
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" id="select-all"></th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Featured</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No products found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td><input type="checkbox" name="product_ids[]"
                                                        value="<?= $product['product_id'] ?>"></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="product-thumbnail">
                                                            <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                                                alt="<?= htmlspecialchars($product['name']) ?>" width="50"
                                                                height="50" style="object-fit: cover;">
                                                        </div>
                                                        <div class="product-info ml-3">
                                                            <div class="product-name"><?= htmlspecialchars($product['name']) ?>
                                                            </div>
                                                            <div class="product-brand text-muted small">
                                                                <?= htmlspecialchars($product['brand']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= ucfirst(htmlspecialchars($product['category'])) ?></td>
                                                <td>
                                                    $<?= number_format($product['price'], 2) ?>
                                                    <?php if ($product['discount'] > 0): ?>
                                                        <span class="text-danger small">(-<?= $product['discount'] ?>%)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="<?= $product['quantity'] < 10 ? 'stock-low' : 'stock-ok' ?>">
                                                    <?= $product['quantity'] ?>
                                                </td>
                                                <td>
                                                    <?php if ($product['featured']): ?>
                                                        <span class="badge badge-success">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($product['created_at'])) ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="admin_products.php?edit_id=<?= $product['product_id'] ?>"
                                                            class="action-btn" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="action-btn text-danger" title="Delete"
                                                            onclick="confirmDelete(<?= $product['product_id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <?php foreach ($products as $product): ?>
                        <form method="POST" id="delete-form-<?= $product['product_id'] ?>" class="delete-form">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <input type="hidden" name="delete_product" value="1">
                        </form>
                    <?php endforeach; ?>

                    <?php if ($total_pages > 1): ?>
                        <nav class="pagination-container">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                            aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                            aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="close" onclick="closeModal('addProductModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="addProductForm">
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Price</label>
                                <input type="number" name="price" step="0.01" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-control" required>
                                    <option value="">Select category</option>
                                    <option value="phone">Phone</option>
                                    <option value="tablet">Tablet</option>
                                    <option value="laptop">Laptop</option>
                                    <option value="accessories">Accessories</option>
                                    <option value="tv">TV</option>
                                    <option value="headphones">Headphones</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="quantity" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Discount (%)</label>
                                <input type="number" name="discount" min="0" max="100" class="form-control" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Featured</label>
                                <label class="checkbox-container">
                                    <input type="checkbox" name="featured" value="1">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <div class="image-preview-container" id="addImagePreviewContainer" style="display:none;">
                                <img id="addImagePreview" class="image-preview" src="#" alt="Preview">
                            </div>
                            <div class="file-upload">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose an image file</span>
                                    <input type="file" name="image" id="addImageInput" accept="image/*">
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Specifications</label>
                            <textarea name="specs" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('addProductModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="add_product" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal"
        style="<?= isset($_GET['edit_id']) ? 'display:block;' : 'display:none;' ?>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="close" onclick="closeModal('editProductModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php if ($edit_product): ?>
                    <form method="POST" enctype="multipart/form-data" id="editProductForm">
                        <input type="hidden" name="product_id" value="<?= $edit_product['product_id'] ?>">
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" name="name" class="form-control"
                                        value="<?= htmlspecialchars($edit_product['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Price</label>
                                    <input type="number" name="price" step="0.01" class="form-control"
                                        value="<?= $edit_product['price'] ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control" required>
                                        <option value="">Select category</option>
                                        <option value="phone" <?= $edit_product['category'] === 'phone' ? 'selected' : '' ?>>
                                            Phone</option>
                                        <option value="tablet" <?= $edit_product['category'] === 'tablet' ? 'selected' : '' ?>>
                                            Tablet</option>
                                        <option value="laptop" <?= $edit_product['category'] === 'laptop' ? 'selected' : '' ?>>
                                            Laptop</option>
                                        <option value="accessories" <?= $edit_product['category'] === 'accessories' ? 'selected' : '' ?>>Accessories</option>
                                        <option value="tv" <?= $edit_product['category'] === 'tv' ? 'selected' : '' ?>>TV
                                        </option>
                                        <option value="headphones" <?= $edit_product['category'] === 'headphones' ? 'selected' : '' ?>>Headphones</option>
                                        <option value="other" <?= $edit_product['category'] === 'other' ? 'selected' : '' ?>>
                                            Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Brand</label>
                                    <input type="text" name="brand" class="form-control"
                                        value="<?= htmlspecialchars($edit_product['brand']) ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" name="quantity" class="form-control"
                                        value="<?= $edit_product['quantity'] ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Discount (%)</label>
                                    <input type="number" name="discount" min="0" max="100" class="form-control"
                                        value="<?= $edit_product['discount'] ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Featured</label>
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="featured" value="1" <?= $edit_product['featured'] ? 'checked' : '' ?>>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4"
                                    required><?= htmlspecialchars($edit_product['description']) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Product Image</label>
                                <div class="image-preview-container">
                                    <img id="editImagePreview" class="image-preview"
                                        src="<?= htmlspecialchars($edit_product['image_url']) ?>" alt="Current Image">
                                </div>
                                <div class="file-upload">
                                    <label class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Change image file</span>
                                        <input type="file" name="image" id="editImageInput" accept="image/*">
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Specifications</label>
                                <textarea name="specs" class="form-control"
                                    rows="4"><?= htmlspecialchars($edit_product['specs']) ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal('editProductModal')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" name="edit_product" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>

        document.getElementById('select-all')?.addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('input[name="product_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });


        document.getElementById('addImageInput')?.addEventListener('change', function () {
            const preview = document.getElementById('addImagePreview');
            const container = document.getElementById('addImagePreviewContainer');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    container.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });


        document.getElementById('editImageInput')?.addEventListener('change', function () {
            const preview = document.getElementById('editImagePreview');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });


        function openAddModal() {
            document.getElementById('addProductModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';


            if (modalId === 'editProductModal' && window.location.search.includes('edit_id')) {
                const url = new URL(window.location);
                url.searchParams.delete('edit_id');
                window.history.replaceState({}, '', url);
            }
        }


        window.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });


        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal('addProductModal');
                closeModal('editProductModal');
            }
        });


        function confirmDelete(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                document.getElementById('delete-form-' + productId).submit();
            }
        }


        document.addEventListener('DOMContentLoaded', function () {

            document.getElementById('addProductForm')?.addEventListener('submit', function (e) {
                if (!validateProductForm(this)) {
                    e.preventDefault();
                }
            });


            document.getElementById('editProductForm')?.addEventListener('submit', function (e) {
                if (!validateProductForm(this)) {
                    e.preventDefault();
                }
            });


            document.getElementById('bulk-action-form')?.addEventListener('submit', function (e) {
                const selected = document.querySelectorAll('input[name="product_ids[]"]:checked').length;
                const action = this.querySelector('select[name="bulk_action"]').value;

                if (selected === 0) {
                    alert('Please select at least one product');
                    e.preventDefault();
                    return;
                }

                if (!action) {
                    alert('Please select a bulk action');
                    e.preventDefault();
                    return;
                }

                if (action === 'delete' && !confirm(`Are you sure you want to delete ${selected} product(s)?`)) {
                    e.preventDefault();
                }
            });
        });

        function validateProductForm(form) {
            let isValid = true;


            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });


            const price = form.querySelector('input[name="price"]');
            if (price && parseFloat(price.value) <= 0) {
                price.classList.add('is-invalid');
                isValid = false;
            }


            const quantity = form.querySelector('input[name="quantity"]');
            if (quantity && parseInt(quantity.value) < 0) {
                quantity.classList.add('is-invalid');
                isValid = false;
            }


            const discount = form.querySelector('input[name="discount"]');
            if (discount) {
                const val = parseInt(discount.value);
                if (val < 0 || val > 100) {
                    discount.classList.add('is-invalid');
                    isValid = false;
                }
            }

            if (!isValid) {
                const firstInvalid = form.querySelector('.is-invalid');
                firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            return isValid;
        }
    </script>
</body>

</html>
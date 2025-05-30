<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin_login.php");
  exit;
}

$conn = getDBConnection();

$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

$error = null;
$success = null;
$name = $price = $category = $brand = $quantity = $discount = $description = $specs = '';
$featured = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $category = trim($_POST['category']);
    $brand = trim($_POST['brand']);
    $quantity = (int)$_POST['quantity'];
    $discount = (int)$_POST['discount'];
    $description = trim($_POST['description']);
    $specs = trim($_POST['specs']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    if (empty($name) || empty($price) || empty($category) || empty($brand) || empty($quantity) || empty($description)) {
        $error = "Please fill in all required fields.";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0.";
    } elseif ($quantity < 0) {
        $error = "Quantity cannot be negative.";
    } elseif ($discount < 0 || $discount > 100) {
        $error = "Discount must be between 0 and 100.";
    } else {
        try {
            $image_url = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('product_') . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;
                
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($file_ext), $allowed_types)) {
                    $error = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
                } elseif ($_FILES['image']['size'] > 5000000) {
                    $error = "File size must be less than 5MB.";
                } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_url = $target_path;
                } else {
                    $error = "Failed to upload image.";
                }
            }
            
            if (empty($error)) {
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("
                    INSERT INTO products 
                    (name, price, category, brand, quantity, discount, description, specs, featured, image_url, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name, $price, $category, $brand, $quantity, $discount, 
                    $description, $specs, $featured, $image_url
                ]);
                
                $product_id = $conn->lastInsertId();
                
                $stmt = $conn->prepare("
                    INSERT INTO activity_logs 
                    (admin_id, action, description, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $admin_id,
                    'product_add',
                    "Added new product: $name (ID: $product_id)",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                $conn->commit();
                
                $name = $price = $category = $brand = $quantity = $discount = $description = $specs = '';
                $featured = 0;
                $success = "Product added successfully!";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error adding product: " . $e->getMessage();
            error_log($error);
        }
    }
}

$stats = [];
try {
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM orders");
    $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->query("SELECT COUNT(*) AS total FROM users");
    $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->query("SELECT COUNT(*) AS total FROM products");
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
    error_log($error);
}


$recent_orders = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching recent orders: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT order_id, status FROM orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$new_status, $order_id]);

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $admin_id,
            'order_update',
            "Updated order #$order_id status from {$order['status']} to $new_status",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $success = "Order status updated successfully!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error updating order: " . $e->getMessage();
    }
}

$view_order = null;
$order_items = [];
if (isset($_GET['view_id'])) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$_GET['view_id']]);
    $view_order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($view_order) {
        $stmt = $conn->prepare("
            SELECT oi.*, p.name, p.image_url 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$_GET['view_id']]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$view_order['user_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$status_options = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="admin_orders.css">
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total Orders</div>
                        <div class="stat-value"><?= number_format($stats['orders'] ?? 0) ?></div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total Customers</div>
                        <div class="stat-value"><?= number_format($stats['customers'] ?? 0) ?></div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total Products</div>
                        <div class="stat-value"><?= number_format($stats['products'] ?? 0) ?></div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Recent Orders</h3>
                <a href="admin_orders.php" class="btn btn-outline">
                    <i class="fas fa-list"></i> View All Orders
                </a>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                                <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($order['status']) ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="admin.php?view_id=<?= $order['order_id'] ?>" class="action-btn" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <button type="button" class="action-btn text-primary" title="Update Status"
                                                onclick="openStatusModal(<?= $order['order_id'] ?>, '<?= $order['status'] ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- View Order Modal -->
        <div id="viewOrderModal" class="modal" style="<?= isset($_GET['view_id']) ? 'display:block;' : 'display:none;' ?>">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Order #<?= $view_order['order_id'] ?? '' ?></h5>
            <button type="button" class="close" onclick="closeModal('viewOrderModal')">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php if ($view_order): ?>
            <div class="modal-body">
              <div class="order-details-container">
                <div class="order-section">
                  <h6 class="section-title">Order Information</h6>
                  <div class="order-info-grid">
                    <div class="info-item">
                      <span class="info-label">Order Date:</span>
                      <span class="info-value"><?= date('M j, Y H:i', strtotime($view_order['order_date'])) ?></span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Status:</span>
                      <span class="info-value status-badge status-<?= $view_order['status'] ?>">
                        <?= ucfirst($view_order['status']) ?>
                      </span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Payment Method:</span>
                      <span class="info-value"><?= ucfirst($view_order['payment_method']) ?></span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Total Amount:</span>
                      <span class="info-value">$<?= number_format($view_order['total_amount'], 2) ?></span>
                    </div>
                  </div>
                </div>

                <div class="order-section">
                  <h6 class="section-title">Customer Information</h6>
                  <div class="customer-info-grid">
                    <div class="info-item">
                      <span class="info-label">Name:</span>
                      <span
                        class="info-value"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Email:</span>
                      <span class="info-value"><?= htmlspecialchars($customer['email']) ?></span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Phone:</span>
                      <span class="info-value"><?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></span>
                    </div>
                  </div>
                </div>

                <div class="order-section">
                  <h6 class="section-title">Shipping Address</h6>
                  <div class="shipping-address">
                    <?= nl2br(htmlspecialchars($view_order['shipping_address'])) ?>
                  </div>
                </div>

                <div class="order-section">
                  <h6 class="section-title">Order Items</h6>
                  <div class="order-items-table">
                    <table>
                      <thead>
                        <tr>
                          <th>Product</th>
                          <th>Price</th>
                          <th>Quantity</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($order_items as $item): ?>
                          <tr>
                            <td>
                              <div class="product-info">
                                <?php if ($item['image_url']): ?>
                                  <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                    alt="<?= htmlspecialchars($item['name']) ?>" width="40" height="40">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($item['name']) ?></span>
                              </div>
                            </td>
                            <td>$<?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                          </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                          <td colspan="3" class="text-right">Subtotal:</td>
                          <td>$<?= number_format($view_order['total_amount'], 2) ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline" onclick="closeModal('viewOrderModal')">
                <i class="fas fa-times"></i> Close
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

        <!-- Status Update Modal -->
        <div id="statusModal" class="modal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Order Status</h5>
                        <button type="button" class="close" onclick="closeModal('statusModal')">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" id="statusForm">
                        <input type="hidden" name="order_id" id="statusOrderId">
                        <div class="modal-body">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control" id="statusSelect" required>
                                    <?php foreach ($status_options as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>"><?= ucfirst(htmlspecialchars($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Product Form -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Add New Product</h3>
            </div>

            <form method="POST" enctype="multipart/form-data" class="product-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Product Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price <span class="required">*</span></label>
                        <input type="number" name="price" step="0.01" class="form-control" value="<?= htmlspecialchars($price) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category <span class="required">*</span></label>
                        <div class="select-wrapper">
                            <select name="category" class="form-control" required>
                                <option value="">Select category</option>
                                <option value="phone" <?= $category === 'phone' ? 'selected' : '' ?>>Phone</option>
                                <option value="tablet" <?= $category === 'tablet' ? 'selected' : '' ?>>Tablet</option>
                                <option value="laptop" <?= $category === 'laptop' ? 'selected' : '' ?>>Laptop</option>
                                <option value="accessories" <?= $category === 'accessories' ? 'selected' : '' ?>>Accessories</option>
                                <option value="tv" <?= $category === 'tv' ? 'selected' : '' ?>>TV</option>
                                <option value="headphones" <?= $category === 'headphones' ? 'selected' : '' ?>>Headphones</option>
                                <option value="other" <?= $category === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand <span class="required">*</span></label>
                        <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($brand) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Stock Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($quantity) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" name="discount" min="0" max="100" class="form-control" value="<?= htmlspecialchars($discount) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Featured</label>
                        <label class="checkbox-container">
                            <input type="checkbox" name="featured" value="1" <?= $featured ? 'checked' : '' ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description <span class="required">*</span></label>
                    <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($description) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <div class="file-upload">
                        <label class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose an image file</span>
                            <input type="file" name="image" accept="image/*">
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Specifications</label>
                    <textarea name="specs" class="form-control" rows="4"><?= htmlspecialchars($specs) ?></textarea>
                </div>

                <div class="form-footer">
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

      const selectAll = document.getElementById('select-all');
      if (selectAll) {
        selectAll.addEventListener('change', function () {
          const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
          checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
          });
        });


        const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
        checkboxes.forEach(checkbox => {
          checkbox.addEventListener('change', function () {
            if (!this.checked) {
              selectAll.checked = false;
            } else {

              const allChecked = Array.from(checkboxes).every(cb => cb.checked);
              selectAll.checked = allChecked;
            }
          });
        });
      }


      const bulkActionSelect = document.getElementById('bulk-action-select');
      const bulkStatusContainer = document.getElementById('bulk-status-container');

      if (bulkActionSelect && bulkStatusContainer) {
        bulkActionSelect.addEventListener('change', function () {
          if (this.value === 'status') {
            bulkStatusContainer.style.display = 'block';
          } else {
            bulkStatusContainer.style.display = 'none';
          }
        });
      }


      const bulkForm = document.getElementById('bulk-action-form');
      if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
          const selectedOrders = document.querySelectorAll('input[name="order_ids[]"]:checked');
          const actionSelect = this.querySelector('select[name="bulk_action"]');

          if (selectedOrders.length === 0) {
            e.preventDefault();
            alert('Please select at least one order to perform bulk actions.');
            return;
          }

          if (!actionSelect.value) {
            e.preventDefault();
            alert('Please select a bulk action to perform.');
            return;
          }

          if (actionSelect.value === 'delete') {
            if (!confirm(`Are you sure you want to delete ${selectedOrders.length} selected order(s)? This action cannot be undone.`)) {
              e.preventDefault();
            }
          }
        });
      }
    });

    function openStatusModal(orderId, currentStatus) {
      document.getElementById('statusOrderId').value = orderId;
      document.getElementById('statusSelect').value = currentStatus;
      document.getElementById('statusModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';


        if (modalId === 'viewOrderModal' && window.location.search.includes('view_id')) {
          const url = new URL(window.location);
          url.searchParams.delete('view_id');
          window.history.replaceState({}, '', url);
        }
      }
    }


    document.addEventListener('click', function (e) {
      if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
      }
    });


    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal[style*="display: block"]');
        if (openModal) {
          closeModal(openModal.id);
        }
      }
    });
    </script>
</body>
</html>
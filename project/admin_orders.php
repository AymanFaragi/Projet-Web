<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];


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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $order_ids = $_POST['order_ids'] ?? [];

    if (!empty($order_ids)) {
        try {
            $conn->beginTransaction();

            switch ($_POST['bulk_action']) {
                case 'delete':
                    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                    
                    
                    $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
                    $stmt->execute($order_ids);
                    
                    
                    $stmt = $conn->prepare("DELETE FROM orders WHERE order_id IN ($placeholders)");
                    $stmt->execute($order_ids);

                    $stmt = $conn->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $admin_id,
                        'order_bulk_delete',
                        "Deleted " . count($order_ids) . " orders",
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);

                    $success = count($order_ids) . " orders deleted successfully!";
                    break;

                case 'status':
                    $new_status = $_POST['bulk_status'];
                    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id IN ($placeholders)");
                    $stmt->execute(array_merge([$new_status], $order_ids));

                    $stmt = $conn->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $admin_id,
                        'order_bulk_status',
                        "Updated " . count($order_ids) . " orders to status: $new_status",
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);

                    $success = count($order_ids) . " orders updated to $new_status!";
                    break;
            }

            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $error = "No orders selected for bulk action";
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


$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(orders.order_id LIKE ? OR users.first_name LIKE ? OR users.last_name LIKE ? OR users.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where[] = "orders.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where[] = "DATE(orders.order_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(orders.order_date) <= ?";
    $params[] = $date_to;
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";


$stmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM orders
    LEFT JOIN users ON orders.user_id = users.user_id
    $where_clause
");
$stmt->execute($params);
$total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_orders / $per_page);


$stmt = $conn->prepare("
    SELECT orders.*, 
           users.first_name, 
           users.last_name,
           users.email,
           users.profile_picture,
           users.phone
    FROM orders
    LEFT JOIN users ON orders.user_id = users.user_id
    $where_clause
    ORDER BY orders.order_date DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


$status_options = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="admin_orders.css">
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-content">
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

            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">All Orders</h3>
                </div>

                <div class="card-body">
                    <div class="filters-row">
                        <form method="GET" class="filter-form">
                            <div class="form-group">
                                <input type="text" name="search" placeholder="Search orders..." class="form-control"
                                    value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="form-group">
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($status_options as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                                            <?= ucfirst(htmlspecialchars($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">From</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">To</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                            </div>

                            <button type="submit" class="btn btn-outline">
                                <i class="fas fa-filter"></i> Filter
                            </button>

                            <a href="admin_orders.php" class="btn btn-outline">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </form>
                    </div>

                    <form method="POST" id="bulk-action-form">
                        <div class="bulk-actions">
                            <div class="form-group">
                                <select name="bulk_action" class="form-control" id="bulk-action-select" required>
                                    <option value="">Bulk Actions</option>
                                    <option value="delete">Delete Selected</option>
                                    <option value="status">Update Status</option>
                                </select>
                            </div>

                            <div class="form-group" id="bulk-status-container" style="display: none;">
                                <select name="bulk_status" class="form-control">
                                    <?php foreach ($status_options as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>"><?= ucfirst(htmlspecialchars($status)) ?></option>
                                    <?php endforeach; ?>
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
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No orders found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td><input type="checkbox" name="order_ids[]" value="<?= $order['order_id'] ?>"></td>
                                                <td>#<?= $order['order_id'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="customer-avatar">
                                                            <img src="<?= htmlspecialchars($order['profile_picture']) ?>"
                                                                    alt="<?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>">
                                                        </div>
                                                        <div class="customer-info ml-3">
                                                            <div class="customer-name">
                                                                <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                                            </div>
                                                            <div class="customer-email text-muted small">
                                                                <?= htmlspecialchars($order['email']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $order['status'] ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= ucfirst($order['payment_method']) ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="admin_orders.php?view_id=<?= $order['order_id'] ?>" class="action-btn" title="View">
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

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
                                        <span class="info-value"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></span>
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
                                                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" width="40" height="40">
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
                bulkActionSelect.addEventListener('change', function() {
                    if (this.value === 'status') {
                        bulkStatusContainer.style.display = 'block';
                    } else {
                        bulkStatusContainer.style.display = 'none';
                    }
                });
            }

            
            const bulkForm = document.getElementById('bulk-action-form');
            if (bulkForm) {
                bulkForm.addEventListener('submit', function(e) {
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

        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

        
        document.addEventListener('keydown', function(e) {
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
<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getDBConnection();
$admin_id = $_SESSION['admin_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $user_id = $_POST['user_id'];

    try {
        $conn->beginTransaction();

        
        $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);

        
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $admin_id,
            'customer_delete',
            "Deleted customer: {$customer['first_name']} {$customer['last_name']} (ID: $user_id)",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $_SESSION['success'] = "Customer deleted successfully!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting customer: " . $e->getMessage();
    }
    header("Location: admin_users.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $user_ids = $_POST['user_ids'] ?? [];

    if (!empty($user_ids)) {
        try {
            $conn->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));

            switch ($_POST['bulk_action']) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
                    $stmt->execute($user_ids);
                    $action = 'customer_bulk_delete';
                    $description = "Deleted " . count($user_ids) . " customers";
                    $success_msg = count($user_ids) . " customers deleted successfully!";
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
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error performing bulk action: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "No customers selected for bulk action";
    }
    header("Location: admin_users.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer'])) {
    try {
        $conn->beginTransaction();

        $user_id = $_POST['user_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $city = $_POST['city'];
        $state = $_POST['state'];
        $zip_code = $_POST['zip_code'];
        $country = $_POST['country'];

        $stmt = $conn->prepare("
            UPDATE users SET
                first_name = ?, last_name = ?, email = ?, phone = ?,
                address = ?, city = ?, state = ?, zip_code = ?, country = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone,
            $address,
            $city,
            $state,
            $zip_code,
            $country,
            $user_id
        ]);

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $admin_id,
            'customer_update',
            "Updated customer: $first_name $last_name (ID: $user_id)",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $_SESSION['success'] = "Customer updated successfully!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating customer: " . $e->getMessage();
    }
    header("Location: admin_users.php");
    exit;
}


$edit_customer = null;
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_customer = $stmt->fetch(PDO::FETCH_ASSOC);
}


$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$country_filter = isset($_GET['country']) ? $_GET['country'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($country_filter)) {
    $where[] = "country = ?";
    $params[] = $country_filter;
}


$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";


$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users $where_clause");
$stmt->execute($params);
$total_customers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_customers / $per_page);


$stmt = $conn->prepare("
    SELECT * FROM users 
    $where_clause 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->query("SELECT DISTINCT country FROM users WHERE country IS NOT NULL AND country != ''");
$countries = $stmt->fetchAll(PDO::FETCH_COLUMN);


$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="admin_users.css">
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
                    <h3 class="card-title">All Customers</h3>
                </div>

                <div class="card-body">
                    <div class="filters-row">
                        <form method="GET" class="filter-form">
                            <div class="form-group">
                                <input type="text" name="search" placeholder="Search customers..." class="form-control"
                                    value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="form-group">
                                <select name="country" class="form-control">
                                    <option value="">All Countries</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= htmlspecialchars($country) ?>" <?= $country_filter === $country ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($country) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-outline">
                                <i class="fas fa-filter"></i> Filter
                            </button>

                            <a href="admin_users.php" class="btn btn-outline">
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
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Location</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($customers)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No customers found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td><input type="checkbox" name="user_ids[]" value="<?= $customer['user_id'] ?>"></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="customer-avatar">
                                                            <?php if (!empty($customer['profile_picture'])): ?>
                                                                <img src="<?= htmlspecialchars($customer['profile_picture']) ?>"
                                                                    alt="<?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>"
                                                                    width="40" height="40"
                                                                    style="object-fit: cover; border-radius: 50%;">
                                                            <?php else: ?>
                                                                <div class="avatar-placeholder">
                                                                    <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="customer-info ml-3">
                                                            <div class="customer-name">
                                                                <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($customer['email']) ?></td>
                                                <td><?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php if ($customer['city'] && $customer['country']): ?>
                                                        <?= htmlspecialchars($customer['city'] . ', ' . $customer['country']) ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>

                                                <td><?= date('M j, Y', strtotime($customer['created_at'])) ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="admin_users.php?edit_id=<?= $customer['user_id'] ?>"
                                                            class="action-btn" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="action-btn text-danger" title="Delete" 
                                                            onclick="confirmDelete(<?= $customer['user_id'] ?>)">
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

                    <?php foreach ($customers as $customer): ?>
                        <form method="POST" id="delete-form-<?= $customer['user_id'] ?>" class="d-none">
                            <input type="hidden" name="user_id" value="<?= $customer['user_id'] ?>">
                            <input type="hidden" name="delete_customer" value="1">
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
    <div id="editCustomerModal" class="modal" style="<?= isset($_GET['edit_id']) ? 'display:block;' : 'display:none;' ?>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Customer</h5>
                    <button type="button" class="close" onclick="closeModal('editCustomerModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php if ($edit_customer): ?>
                    <form method="POST" id="editCustomerForm">
                        <input type="hidden" name="user_id" value="<?= $edit_customer['user_id'] ?>">
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['first_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['last_name']) ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['email']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control"
                                    value="<?= htmlspecialchars($edit_customer['address'] ?? '') ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['city'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" name="state" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['state'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ZIP/Postal Code</label>
                                    <input type="text" name="zip_code" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['zip_code'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" class="form-control"
                                        value="<?= htmlspecialchars($edit_customer['country'] ?? '') ?>">
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal('editCustomerModal')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" name="edit_customer" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script> 
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this customer? All their orders will also be deleted.')) {
                document.getElementById('delete-form-' + userId).submit();
            }
        }

        document.getElementById('bulk-action-form')?.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('input[name="user_ids[]"]:checked').length;
            const action = this.querySelector('select[name="bulk_action"]').value;
            
            if (selected === 0) {
                alert('Please select at least one customer');
                e.preventDefault();
                return;
            }
            
            if (!action) {
                alert('Please select a bulk action');
                e.preventDefault();
                return;
            }
            
            let confirmationMessage = '';
            switch(action) {
                case 'delete':
                    confirmationMessage = `Are you sure you want to delete ${selected} customer(s)? This will also delete all their orders.`;
                    break;
            }
            
            if (!confirm(confirmationMessage)) {
                e.preventDefault();
            }
        });
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            
            if (modalId === 'editCustomerModal' && window.location.search.includes('edit_id')) {
                const url = new URL(window.location);
                url.searchParams.delete('edit_id');
                window.history.replaceState({}, '', url);
            }
        }

        
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });

        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('editCustomerModal');
            }
        });
    </script>
</body>
</html>
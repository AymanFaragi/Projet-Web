<?php
require_once 'config.php';


if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

if ($_SESSION['admin_role'] !== 'superadmin') {
    header("Location: admin.php");
    exit;
}

$conn = getDBConnection();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $admin_id = (int) $_POST['admin_id'];
    $admin_number = trim($_POST['admin_number']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT admin_number FROM admin_users WHERE admin_number = ?");
        $stmt->execute([$admin_number]);
        if ($stmt->fetch()) {
            throw new Exception("Admin number already exists");
        }


        $stmt = $conn->prepare("SELECT admin_id FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        if ($stmt->fetch()) {
            throw new Exception("Admin id already exists");
        }

        $stmt = $conn->prepare("
            INSERT INTO admin_users (
                admin_id,
                admin_number, 
                full_name, 
                role
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$admin_id, $admin_number, $full_name, $role]);

        $new_admin_id = $conn->lastInsertId();


        $stmt = $conn->prepare("
            INSERT INTO activity_logs (
                admin_id, 
                action, 
                description, 
                ip_address, 
                user_agent
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            'admin_create',
            "Created new admin: $full_name (ID: $new_admin_id, Number: $admin_number)",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $success = "Admin account created successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error creating admin: " . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $admin_id = (int) $_POST['admin_id'];
    $admin_number = trim($_POST['admin_number']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];

    try {
        $conn->beginTransaction();


        $stmt = $conn->prepare("SELECT admin_number, full_name, role FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $old_admin = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($admin_number !== $old_admin['admin_number']) {
            $stmt = $conn->prepare("SELECT admin_id FROM admin_users WHERE admin_number = ? AND admin_id != ?");
            $stmt->execute([$admin_number, $admin_id]);
            if ($stmt->fetch()) {
                throw new Exception("Admin number already in use");
            }
        }


        $update_fields = [
            'admin_number' => $admin_number,
            'full_name' => $full_name,
            'role' => $role,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $set_clause = implode(', ', array_map(fn($field) => "$field = ?", array_keys($update_fields)));
        $values = array_values($update_fields);
        $values[] = $admin_id;

        $stmt = $conn->prepare("UPDATE admin_users SET $set_clause WHERE admin_id = ?");
        $stmt->execute($values);


        $description = "Updated admin: {$old_admin['full_name']} (ID: $admin_id). Changes: ";
        $changes = [];
        if ($old_admin['admin_number'] !== $admin_number)
            $changes[] = "admin number from {$old_admin['admin_number']} to $admin_number";
        if ($old_admin['full_name'] !== $full_name)
            $changes[] = "name from {$old_admin['full_name']} to $full_name";
        if ($old_admin['role'] !== $role)
            $changes[] = "role from {$old_admin['role']} to $role";

        $description .= implode(', ', $changes);

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (
                admin_id, 
                action, 
                description, 
                ip_address, 
                user_agent
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            'admin_update',
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $conn->commit();
        $success = "Admin account updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error updating admin: " . $e->getMessage();
    }
}


$stmt = $conn->query("
    SELECT 
        admin_users.*,
        (SELECT COUNT(*) FROM activity_logs WHERE admin_id = admin_users.admin_id) AS activity_count
    FROM admin_users
    ORDER BY created_at DESC
");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);


$admin_details = null;
$admin_activities = [];
if (isset($_GET['admin_id'])) {
    $admin_id = (int) $_GET['admin_id'];


    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $admin_details = $stmt->fetch(PDO::FETCH_ASSOC);


    $stmt = $conn->prepare("
        SELECT * FROM activity_logs 
        WHERE admin_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$admin_id]);
    $admin_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel=" stylesheet" href="admin.css">
    <link rel="stylesheet" href="admin_logs.css">
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

            <div class="admin-management-container">
                <div class="admin-list-section">
                    <div class="section-header">
                        <h3>All Admin Accounts</h3>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Admin
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Admin Number</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Activities</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr
                                        class="<?= isset($_GET['admin_id']) && $_GET['admin_id'] == $admin['admin_id'] ? 'active-row' : '' ?>">
                                        <td><?= htmlspecialchars($admin['admin_number']) ?></td>
                                        <td>
                                            <a href="admin_logs.php?admin_id=<?= $admin['admin_id'] ?>" class="admin-link">
                                                <?= htmlspecialchars($admin['full_name']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= $admin['role'] ?>">
                                                <?= ucfirst($admin['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= $admin['activity_count'] ?></td>
                                        <td>
                                            <?= $admin['last_login'] ? date('M j, Y H:i', strtotime($admin['last_login'])) : 'Never' ?>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($admin['created_at'])) ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="admin_logs.php?admin_id=<?= $admin['admin_id'] ?>"
                                                    class="action-btn" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="action-btn text-primary" title="Edit" onclick="openEditModal(
                                                        <?= $admin['admin_id'] ?>,
                                                        '<?= htmlspecialchars($admin['admin_number'], ENT_QUOTES) ?>',
                                                        '<?= htmlspecialchars($admin['full_name'], ENT_QUOTES) ?>',
                                                        '<?= htmlspecialchars($admin['role'], ENT_QUOTES) ?>'
                                                    )">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($admin_details): ?>
                    <div class="admin-details-section">
                        <div class="section-header">
                            <h3>Admin Details: <?= htmlspecialchars($admin_details['full_name']) ?></h3>
                            <div class="admin-meta">
                                <span><strong>Admin Number:</strong>
                                    <?= htmlspecialchars($admin_details['admin_number']) ?></span>
                                <span><strong>Created:</strong>
                                    <?= date('M j, Y', strtotime($admin_details['created_at'])) ?></span>
                                <span><strong>Last Updated:</strong>
                                    <?= date('M j, Y', strtotime($admin_details['updated_at'])) ?></span>
                                <span><strong>Last Login:</strong>
                                    <?= $admin_details['last_login'] ? date('M j, Y H:i', strtotime($admin_details['last_login'])) : 'Never' ?></span>
                            </div>
                        </div>

                        <div class="admin-activity-logs">
                            <h4>Recent Activities</h4>
                            <?php if (empty($admin_activities)): ?>
                                <div class="no-activities">
                                    <p>No activities found for this admin</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-timeline">
                                    <?php foreach ($admin_activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon">
                                                <?php switch ($activity['action']) {
                                                    case 'login':
                                                        echo '<i class="fas fa-sign-in-alt"></i>';
                                                        break;
                                                    case 'create':
                                                        echo '<i class="fas fa-plus-circle"></i>';
                                                        break;
                                                    case 'update':
                                                        echo '<i class="fas fa-edit"></i>';
                                                        break;
                                                    case 'delete':
                                                        echo '<i class="fas fa-trash-alt"></i>';
                                                        break;
                                                    default:
                                                        echo '<i class="fas fa-history"></i>';
                                                } ?>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-description">
                                                    <?= htmlspecialchars($activity['description']) ?>
                                                </div>
                                                <div class="activity-meta">
                                                    <span class="activity-time">
                                                        <?= date('M j, Y H:i', strtotime($activity['created_at'])) ?>
                                                    </span>
                                                    <span class="activity-ip">
                                                        <?= htmlspecialchars($activity['ip_address']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="addAdminModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Admin</h5>
                    <button type="button" class="close" onclick="closeModal('addAdminModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" id="addAdminForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Admin ID</label>
                            <input type="text" name="admin_id" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Admin Number</label>
                            <input type="text" name="admin_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('addAdminModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="add_admin" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editAdminModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Admin</h5>
                    <button type="button" class="close" onclick="closeModal('editAdminModal')">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" id="editAdminForm">
                    <input type="hidden" name="admin_id" id="editAdminId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Admin Number</label>
                            <input type="text" name="admin_number" id="editAdminNumber" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="editFullName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" id="editRole" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editAdminModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="update_admin" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addAdminModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function openEditModal(adminId, adminNumber, fullName, role) {
            document.getElementById('editAdminId').value = adminId;
            document.getElementById('editAdminNumber').value = adminNumber;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editRole').value = role;
            document.getElementById('editAdminModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
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
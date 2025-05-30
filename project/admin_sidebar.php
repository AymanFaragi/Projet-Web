<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getDBConnection();
$stats = [];

try {
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM products");
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->query("SELECT COUNT(*) AS total FROM users");
    $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->query("SELECT COUNT(*) AS total FROM orders");
    $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching sidebar stats: " . $e->getMessage());
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div>
            <i class="fas fa-cloud"></i>
        </div>
        <h2 class="sidebar-title">sKYS admin</h2>
    </div>
    
    <nav class="sidebar-menu">
        
        <a href="admin.php" class="menu-item <?= $current_page === 'admin.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        
        <a href="admin_products.php" class="menu-item <?= $current_page === 'admin_products.php' ? 'active' : '' ?>">
            <i class="fas fa-boxes"></i>
            <span>Products</span>
            <span class="badge"><?= number_format($stats['products'] ?? 0) ?></span>
        </a>
        <a href="admin_users.php" class="menu-item <?= $current_page === 'admin_users.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Customers</span>
            <span class="badge"><?= number_format($stats['customers'] ?? 0) ?></span>
        </a>
        <a href="admin_orders.php" class="menu-item <?= $current_page === 'admin_orders.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
            <span class="badge"><?= number_format($stats['orders'] ?? 0) ?></span>
        </a>
        <?php if ($_SESSION['admin_role']=== 'superadmin'): ?>

        <a href="admin_logs.php" class="menu-item <?= $current_page === 'admin_logs.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Activity Logs</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar" style="background-color: var(--accent-color); color: var(--primary-color);">
                <?= substr($_SESSION['admin_name'] ?? 'A', 0, 1) ?>
            </div>
            <div class="admin-details">
                <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                <span class="admin-role"><?= ucfirst($_SESSION['admin_role'] ?? 'admin') ?></span>
            </div>
        </div>
        <a href="admin_logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<style>
:root {
  --primary-color: #254336;
  --primary-color-rgba: #2543362d;
  --secondary-color: #6B8A7A;
  --accent-color: #B7B597;
  --dark-accent: #254336;
  --text-color: #212121;
  --light-text: #DAD3BE;
  --card-bg: #fafafa;
  --overlay-color: rgba(18, 18, 24, 0.7);
  --sidebar-width: 280px;
  --header-height: 70px;
  --border-radius: 8px;
  --transition: all 0.3s ease;
}

.admin-sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background-color: var(--primary-color);
    color: var(--light-text);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: var(--transition);
    box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.sidebar-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: var(--light-text);
}

.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    padding: 15px 0;
}

.menu-category {
    padding: 12px 20px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent-color);
    margin-top: 5px;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: var(--light-text);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    font-size: 14px;
    opacity: 0.8;
}

.menu-item:hover {
    background-color: var(--primary-color-rgba);
    opacity: 1;
    color: var(--accent-color);
}

.menu-item.active {
    background-color: var(--primary-color-rgba);
    color: var(--accent-color);
    opacity: 1;
    border-left: 3px solid var(--accent-color);
}

.menu-item i {
    width: 24px;
    text-align: center;
    margin-right: 10px;
    font-size: 16px;
}

.menu-item .badge {
    margin-left: auto;
    background-color: var(--accent-color);
    color: var(--primary-color);
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
}

.sidebar-footer {
    padding: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-info {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    gap: 10px;
}

.admin-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
}

.admin-details {
    display: flex;
    flex-direction: column;
}

.admin-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--light-text);
}

.admin-role {
    font-size: 12px;
    color: var(--accent-color);
    opacity: 0.8;
    background-color: var(--primary-color);
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    background-color: rgba(183, 181, 151, 0.1);
    color: var(--accent-color);
    border-radius: var(--border-radius);
    text-decoration: none;
    font-size: 13px;
    transition: var(--transition);
    gap: 8px;
}

.logout-btn:hover {
    background-color: rgba(183, 181, 151, 0.2);
}

@media (max-width: 992px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.active {
        transform: translateX(0);
    }
}
</style>


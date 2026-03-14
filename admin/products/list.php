<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Products Management';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    query("DELETE FROM products WHERE id = ?", [$delete_id]);
    header('Location: list.php?msg=deleted');
    exit();
}

// Get categories for filter
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Build query with filters
$where = "1=1";
$params = [];

if (!empty($_GET['category']) && is_numeric($_GET['category'])) {
    $where .= " AND p.category_id = ?";
    $params[] = $_GET['category'];
}

if (!empty($_GET['search'])) {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (isset($_GET['stock']) && $_GET['stock'] === 'low') {
    $where .= " AND p.stock <= p.low_stock_threshold AND p.stock > 0";
}

if (isset($_GET['stock']) && $_GET['stock'] === 'out') {
    $where .= " AND p.stock = 0";
}

if (isset($_GET['visibility']) && $_GET['visibility'] === 'hidden') {
    $where .= " AND p.visible = 0";
} elseif (!isset($_GET['visibility'])) {
    $where .= " AND p.visible = 1";
}

$sql = "SELECT p.*, c.name as category_name, 
        (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $where 
        ORDER BY p.id DESC";

$products = fetchAll($sql, $params);

// Get admin name for welcome message
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #0d6efd; --dark: #212529; }
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .sidebar a i { width: 25px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .table-actions .btn { padding: 5px 10px; margin: 0 2px; }
        .stock-badge { font-size: 12px; }
        .product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="list.php" class="active"><i class="fas fa-box"></i> Products</a>
        <a href="../services/list.php"><i class="fas fa-tools"></i> Services</a>
        <a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="../users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="../service-bookings/list.php"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="../chatbot/conversations.php"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="../coupons/manage.php"><i class="fas fa-ticket"></i> Coupons</a>
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../reviews/manage.php"><i class="fas fa-star"></i> Reviews</a>
        <a href="../system-status.php"><i class="fas fa-server"></i> System</a>
        <a href="../logs.php"><i class="fas fa-file-lines"></i> Logs</a>
        <a href="../delivery-map.php"><i class="fas fa-map"></i> Map</a>
        <a href="../logout.php" style="margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="bg-primary bg-gradient text-white p-4 rounded mb-4">
            <h4 class="mb-1">Products Management</h4>
            <p class="mb-0 opacity-75">Manage your product inventory, add new products, or edit existing ones.</p>
        </div>

        <?php if ($msg === 'deleted'): ?>
            <div class="alert alert-success">Product deleted successfully!</div>
        <?php elseif ($msg === 'saved'): ?>
            <div class="alert alert-success">Product saved successfully!</div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="stock" class="form-select">
                            <option value="">All Stock</option>
                            <option value="low" <?= ($_GET['stock'] ?? '') == 'low' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out" <?= ($_GET['stock'] ?? '') == 'out' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="visibility" class="form-select">
                            <option value="1" <?= ($_GET['visibility'] ?? '1') == '1' ? 'selected' : '' ?>>Visible</option>
                            <option value="0" <?= ($_GET['visibility'] ?? '') == '0' ? 'selected' : '' ?>>Hidden</option>
                            <option value="" <?= !isset($_GET['visibility']) ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">Filter</button>
                    </div>
                    <div class="col-md-1">
                        <a href="list.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted">
                Total products: <?= count($products) ?>
            </div>
            <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Products List</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Image</th>
                                <th>ID</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">No products found</td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="http://localhost/outsourced/assets/images/products/<?= htmlspecialchars($p['image']) ?>" class="product-thumb" alt="<?= htmlspecialchars($p['name']) ?>">
                                            <?php else: ?>
                                                <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="width:50px;height:50px;border-radius:4px;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $p['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($p['short_description'] ?? '', 0, 50)) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($p['sku']) ?></td>
                                        <td><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                                        <td>KSh <?= number_format($p['price']) ?></td>
                                        <td>
                                            <?php if ($p['stock'] == 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($p['stock'] <= $p['low_stock_threshold']): ?>
                                                <span class="badge bg-warning text-dark">Low (<?= $p['stock'] ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $p['stock'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['visible']): ?>
                                                <span class="badge bg-primary">Visible</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Hidden</span>
                                            <?php endif; ?>
                                            <?php if ($p['featured']): ?>
                                                <span class="badge bg-warning"><i class="fas fa-star"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this product?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Add Product';

// Get categories
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $compare_at_price = !empty($_POST['compare_at_price']) ? (float)$_POST['compare_at_price'] : null;
    $stock = (int)($_POST['stock'] ?? 0);
    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 5);
    $short_description = trim($_POST['short_description'] ?? '');
    $full_description = trim($_POST['full_description'] ?? '');
    $weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
    $visible = isset($_POST['visible']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^a-z0-9-]/', '-', preg_replace('/\s+/', '-', $name))));
    
    if (empty($name) || empty($sku) || $category_id === 0 || $price === 0) {
        $error = 'Please fill in all required fields';
    } else {
        // Check for duplicate SKU
        $existing = fetchOne("SELECT id FROM products WHERE sku = ?", [$sku]);
        if ($existing) {
            $error = 'A product with this SKU already exists';
        } else {
            query(
                "INSERT INTO products (category_id, sku, name, slug, short_description, full_description, price, compare_at_price, stock, low_stock_threshold, weight_kg, visible, featured) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$category_id, $sku, $name, $slug, $short_description, $full_description, $price, $compare_at_price, $stock, $low_stock_threshold, $weight_kg, $visible, $featured]
            );
            
            global $db;
            $new_product_id = $db->lastInsertId();
            
            header('Location: edit.php?id=' . $new_product_id . '&msg=saved');
            exit();
        }
    }
}
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
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
    </style>
</head>
<body>
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
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Add Product</h2>
            <a href="list.php" class="btn btn-secondary">Back to Products</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">SKU *</label>
                                <input type="text" name="sku" class="form-control" required value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Price (KSh) *</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Compare Price (KSh)</label>
                                <input type="number" name="compare_at_price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['compare_at_price'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="stock" class="form-control" min="0" value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" class="form-control" min="0" value="<?= htmlspecialchars($_POST['low_stock_threshold'] ?? '5') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" name="weight_kg" class="form-control" step="0.001" min="0" value="<?= htmlspecialchars($_POST['weight_kg'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="2"><?= htmlspecialchars($_POST['short_description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Description</label>
                        <textarea name="full_description" class="form-control" rows="5"><?= htmlspecialchars($_POST['full_description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="visible" class="form-check-input" id="visible" <?= !isset($_POST['visible']) || $_POST['visible'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="visible">Visible (show on website)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="featured" class="form-check-input" id="featured" <?= isset($_POST['featured']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">Featured product</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

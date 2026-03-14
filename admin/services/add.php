<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Add Service';
$service = null;
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $service = fetchOne("SELECT * FROM services WHERE id = ?", [$id]);
    if ($service) {
        $page_title = 'Edit Service - ' . $service['name'];
    }
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = !empty($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
    $visible = isset($_POST['visible']) ? 1 : 0;
    
    $slug = strtolower(trim(preg_replace('/[^a-z0-9-]/', '-', preg_replace('/\s+/', '-', $name))));
    
    if (empty($name) || $price === 0) {
        $error = 'Please fill in name and price';
    } else {
        if ($id > 0) {
            query(
                "UPDATE services SET name = ?, slug = ?, price = ?, description = ?, duration_minutes = ?, visible = ? WHERE id = ?",
                [$name, $slug, $price, $description, $duration_minutes, $visible, $id]
            );
        } else {
            query(
                "INSERT INTO services (name, slug, price, description, duration_minutes, visible) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $slug, $price, $description, $duration_minutes, $visible]
            );
        }
        header('Location: list.php?msg=saved');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
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
        <a href="../products/list.php"><i class="fas fa-box"></i> Products</a>
        <a href="list.php" class="active"><i class="fas fa-tools"></i> Services</a>
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
            <h2><?= $page_title ?></h2>
            <a href="list.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Service Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $service['name'] ?? '') ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price (KSh) *</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['price'] ?? $service['price'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="number" name="duration_minutes" class="form-control" min="0" value="<?= htmlspecialchars($_POST['duration_minutes'] ?? $service['duration_minutes'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($_POST['description'] ?? $service['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="visible" class="form-check-input" id="visible" <?= (isset($_POST['visible']) ? $_POST['visible'] : ($service['visible'] ?? 1)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="visible">Visible on website</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Service</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

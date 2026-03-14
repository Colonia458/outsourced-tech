<?php
// admin/delivery-zones/manage.php

require_once '../../src/config.php';
require_once '../../src/database.php';

// Security check
if (!isset($_SESSION['admin_user'])) {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $max_distance_km = !empty($_POST['max_distance_km']) ? (float)$_POST['max_distance_km'] : null;
        $fee = (float)$_POST['fee'] ?? 0;
        $min_order_for_free = !empty($_POST['min_order_for_free']) ? (float)$_POST['min_order_for_free'] : null;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        
        if (empty($name)) {
            $message = 'Zone name is required.';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO delivery_zones (name, max_distance_km, fee, min_order_for_free, sort_order, active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$name, $max_distance_km, $fee, $min_order_for_free, $sort_order]);
                $message = 'Zone added successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error adding zone: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $max_distance_km = !empty($_POST['max_distance_km']) ? (float)$_POST['max_distance_km'] : null;
        $fee = (float)$_POST['fee'] ?? 0;
        $min_order_for_free = !empty($_POST['min_order_for_free']) ? (float)$_POST['min_order_for_free'] : null;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name) || $id <= 0) {
            $message = 'Invalid data provided.';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE delivery_zones 
                    SET name = ?, max_distance_km = ?, fee = ?, min_order_for_free = ?, 
                        sort_order = ?, active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $max_distance_km, $fee, $min_order_for_free, $sort_order, $active, $id]);
                $message = 'Zone updated successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating zone: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_GET['toggle'];
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE delivery_zones SET active = NOT active WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Zone status updated!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating zone: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_GET['delete'];
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM delivery_zones WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Zone deleted successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting zone: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch all zones
$zones = fetchAll("SELECT * FROM delivery_zones ORDER BY sort_order ASC, fee ASC");
?>

<?php $page_title = "Delivery Zones Management"; ?>
<?php require_once '../../templates/admin/header.php'; ?>
<?php require_once '../../templates/admin/sidebar.php'; ?>

<div class="admin-content p-4" style="margin-left: 250px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Delivery Zones</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add New Zone Form -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Zone</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Zone Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Mlolongo Central, Nairobi CBD" required>
                            <small class="text-muted">Keywords in address to match this zone</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Distance (km)</label>
                            <input type="number" name="max_distance_km" class="form-control" step="0.1" min="0" placeholder="Optional">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery Fee (KSh)</label>
                            <input type="number" name="fee" class="form-control" min="0" step="0.01" value="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Free Delivery Min Order (KSh)</label>
                            <input type="number" name="min_order_for_free" class="form-control" min="0" step="0.01" placeholder="Optional - leave empty for no free threshold">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add Zone</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Zones -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Max Distance</th>
                                    <th>Fee</th>
                                    <th>Free From</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($zones)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No zones configured yet. Add your first delivery zone!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($zones as $zone): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($zone['name']) ?></strong></td>
                                            <td><?= $zone['max_distance_km'] ? $zone['max_distance_km'] . ' km' : '-' ?></td>
                                            <td>KSh <?= number_format($zone['fee']) ?></td>
                                            <td><?= $zone['min_order_for_free'] ? 'KSh ' . number_format($zone['min_order_for_free']) : '-' ?></td>
                                            <td>
                                                <?php if ($zone['active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $zone['id'] ?>">
                                                    Edit
                                                </button>
                                                <a href="?toggle=<?= $zone['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    <?= $zone['active'] ? 'Disable' : 'Enable' ?>
                                                </a>
                                                <a href="?delete=<?= $zone['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this zone?')">
                                                    Delete
                                                </a>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?= $zone['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit <?= htmlspecialchars($zone['name']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update">
                                                            <input type="hidden" name="id" value="<?= $zone['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Zone Name</label>
                                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($zone['name']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Max Distance (km)</label>
                                                                <input type="number" name="max_distance_km" class="form-control" step="0.1" value="<?= $zone['max_distance_km'] ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Delivery Fee (KSh)</label>
                                                                <input type="number" name="fee" class="form-control" min="0" step="0.01" value="<?= $zone['fee'] ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Free Delivery Min Order</label>
                                                                <input type="number" name="min_order_for_free" class="form-control" min="0" step="0.01" value="<?= $zone['min_order_for_free'] ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Sort Order</label>
                                                                <input type="number" name="sort_order" class="form-control" value="<?= $zone['sort_order'] ?>" min="0">
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input type="checkbox" name="active" class="form-check-input" id="active_edit<?= $zone['id'] ?>" <?= $zone['active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="active_edit<?= $zone['id'] ?>">Active</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Seed Default Zones -->
            <?php if (empty($zones)): ?>
                <form method="post" class="mt-3">
                    <input type="hidden" name="action" value="seed">
                    <button type="submit" class="btn btn-outline-secondary">
                        Seed Default Zones
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Handle seeding default zones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    $default_zones = [
        ['Mlolongo Central', 2, 0, 5000, 1],
        ['Mlolongo West', 5, 0, 8000, 2],
        ['Syokimau', 5, 0, 5000, 3],
        ['Nairobi CBD', 15, 150, 15000, 4],
        ['Nairobi Suburbs', 25, 300, 20000, 5],
        ['Outside Nairobi', null, 500, 50000, 6],
    ];
    
    foreach ($default_zones as $zone) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO delivery_zones (name, max_distance_km, fee, min_order_for_free, sort_order, active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($zone);
        } catch (PDOException $e) {
            // Ignore duplicates
        }
    }
    echo "<meta http-equiv='refresh' content='0'>";
}
?>

<?php require_once '../../templates/admin/footer.php'; ?>

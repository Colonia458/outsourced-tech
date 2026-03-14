<?php
// admin/loyalty-tiers/manage.php

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
        $min_points = (int)($_POST['min_points'] ?? 0);
        $discount_percent = (float)($_POST['discount_percent'] ?? 0);
        $free_delivery = isset($_POST['free_delivery']) ? 1 : 0;
        $other_benefits = trim($_POST['other_benefits'] ?? '');
        $badge_color = $_POST['badge_color'] ?? '#cccccc';
        $badge_icon = $_POST['badge_icon'] ?? '🏅';
        
        if (empty($name) || $min_points < 0) {
            $message = 'Name and minimum points are required.';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO loyalty_tiers (name, min_points, discount_percent, free_delivery, other_benefits, badge_color, badge_icon)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $min_points, $discount_percent, $free_delivery, $other_benefits, $badge_color, $badge_icon]);
                $message = 'Tier added successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error adding tier: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $min_points = (int)($_POST['min_points'] ?? 0);
        $discount_percent = (float)($_POST['discount_percent'] ?? 0);
        $free_delivery = isset($_POST['free_delivery']) ? 1 : 0;
        $other_benefits = trim($_POST['other_benefits'] ?? '');
        $badge_color = $_POST['badge_color'] ?? '#cccccc';
        $badge_icon = $_POST['badge_icon'] ?? '🏅';
        
        if (empty($name) || $min_points < 0 || $id <= 0) {
            $message = 'Invalid data provided.';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE loyalty_tiers 
                    SET name = ?, min_points = ?, discount_percent = ?, free_delivery = ?, 
                        other_benefits = ?, badge_color = ?, badge_icon = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $min_points, $discount_percent, $free_delivery, $other_benefits, $badge_color, $badge_icon, $id]);
                $message = 'Tier updated successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating tier: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_GET['delete'];
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM loyalty_tiers WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Tier deleted successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting tier: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch all tiers
$tiers = fetchAll("SELECT * FROM loyalty_tiers ORDER BY min_points ASC");
?>

<?php $page_title = "Loyalty Tiers Management"; ?>
<?php require_once '../../templates/admin/header.php'; ?>
<?php require_once '../../templates/admin/sidebar.php'; ?>

<div class="admin-main">
<div class="admin-content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Loyalty Tiers</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add New Tier Form -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Tier</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Tier Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Bronze, Silver, Gold" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minimum Points</label>
                            <input type="number" name="min_points" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Discount (%)</label>
                            <input type="number" name="discount_percent" class="form-control" min="0" max="100" step="0.01" value="0">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="free_delivery" class="form-check-input" id="free_delivery_add">
                                <label class="form-check-label" for="free_delivery_add">Free Delivery</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Badge Color</label>
                            <input type="color" name="badge_color" class="form-control form-control-color" value="#cd7f32">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Badge Icon</label>
                            <input type="text" name="badge_icon" class="form-control" value="🥉" placeholder="e.g. 🥉">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Other Benefits</label>
                            <textarea name="other_benefits" class="form-control" rows="2" placeholder="Priority support, birthday gift, etc."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add Tier</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Tiers -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Badge</th>
                                    <th>Name</th>
                                    <th>Min Points</th>
                                    <th>Discount</th>
                                    <th>Free Delivery</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tiers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No tiers configured yet. Add your first tier!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tiers as $tier): ?>
                                        <tr>
                                            <td>
                                                <span class="fs-4"><?= htmlspecialchars($tier['badge_icon'] ?? '') ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($tier['name']) ?></strong>
                                            </td>
                                            <td><?= number_format($tier['min_points']) ?></td>
                                            <td><?= $tier['discount_percent'] ?>%</td>
                                            <td>
                                                <?php if ($tier['free_delivery']): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $tier['id'] ?>">
                                                    Edit
                                                </button>
                                                <a href="?delete=<?= $tier['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this tier?')">
                                                    Delete
                                                </a>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?= $tier['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit <?= htmlspecialchars($tier['name']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update">
                                                            <input type="hidden" name="id" value="<?= $tier['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Tier Name</label>
                                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($tier['name']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Minimum Points</label>
                                                                <input type="number" name="min_points" class="form-control" min="0" value="<?= $tier['min_points'] ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Discount (%)</label>
                                                                <input type="number" name="discount_percent" class="form-control" min="0" max="100" step="0.01" value="<?= $tier['discount_percent'] ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input type="checkbox" name="free_delivery" class="form-check-input" id="free_delivery_edit<?= $tier['id'] ?>" <?= $tier['free_delivery'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="free_delivery_edit<?= $tier['id'] ?>">Free Delivery</label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Badge Color</label>
                                                                <input type="color" name="badge_color" class="form-control form-control-color" value="<?= $tier['badge_color'] ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Badge Icon</label>
                                                                <input type="text" name="badge_icon" class="form-control" value="<?= htmlspecialchars($tier['badge_icon']) ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Other Benefits</label>
                                                                <textarea name="other_benefits" class="form-control" rows="2"><?= htmlspecialchars($tier['other_benefits'] ?? '') ?></textarea>
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
            
            <!-- Seed Default Tiers Button -->
            <?php if (empty($tiers)): ?>
                <form method="post" class="mt-3">
                    <input type="hidden" name="action" value="seed">
                    <button type="submit" class="btn btn-outline-secondary">
                        Seed Default Tiers (Bronze, Silver, Gold, Platinum)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div><!-- End admin-content -->
</div><!-- End admin-main -->

<?php
// Handle seeding default tiers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    $default_tiers = [
        ['Bronze', 0, 0, 0, '#cd7f32', '🥉', 'Entry level membership'],
        ['Silver', 500, 5, 1, '#c0c0c0', '🥈', '5% discount on all orders'],
        ['Gold', 2000, 10, 1, '#ffd700', '🥇', '10% discount + priority support'],
        ['Platinum', 5000, 15, 1, '#e5e4e2', '💎', '15% discount + exclusive deals'],
    ];
    
    foreach ($default_tiers as $tier) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO loyalty_tiers (name, min_points, discount_percent, free_delivery, badge_color, badge_icon, other_benefits)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tier[0], $tier[1], $tier[2], $tier[3], $tier[4], $tier[5], $tier[6]]);
        } catch (PDOException $e) {
            // Ignore duplicates
        }
    }
    echo "<meta http-equiv='refresh' content='0'>";
}
?>

<?php require_once '../../templates/admin/footer.php'; ?>

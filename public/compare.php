<?php
/**
 * Product Comparison Page
 * Outsourced Technologies E-Commerce Platform
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/compare.php';
require_once __DIR__ . '/../src/cart.php';

$products = get_comparison_products();
$comparison_count = count($products);

// Handle remove action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id > 0) {
        remove_from_comparison($product_id);
        header('Location: compare.php');
        exit;
    }
}

$page_title = 'Compare Products - ' . APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .compare-table { overflow-x: auto; }
        .compare-table table { min-width: 600px; }
        .compare-table th { min-width: 180px; background: #f8f9fa; }
        .compare-img { width: 100%; max-width: 150px; height: auto; }
        .rating-stars { color: #ffc107; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../templates/header.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-1"><i class="fas fa-balance-scale text-primary me-2"></i>Compare Products</h1>
                <p class="text-muted mb-0"><?= $comparison_count ?> product(s) selected (max 4)</p>
            </div>
            <?php if ($comparison_count > 0): ?>
                <div>
                    <a href="products.php" class="btn btn-outline-primary"><i class="fas fa-plus me-2"></i>Add More</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-balance-scale fa-5x text-muted"></i>
                </div>
                <h3 class="mb-3">No products to compare</h3>
                <p class="text-muted mb-4">Add products to compare their features and prices</p>
                <a href="products.php" class="btn btn-primary btn-lg">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th></th>
                            <?php foreach ($products as $p): ?>
                                <th>
                                    <?php if (!empty($p['image'])): ?>
                                        <?php $img_url = strpos($p['image'], 'http') === 0 ? $p['image'] : BASE_URL . 'assets/images/products/' . $p['image']; ?>
                                        <img src="<?= htmlspecialchars($img_url) ?>" class="img-fluid mb-2" style="max-height:120px;" alt="<?= htmlspecialchars($p['name']) ?>">
                                    <?php else: ?>
                                        <div class="bg-light p-3 mb-2 text-center"><i class="fas fa-image fa-2x text-muted"></i></div>
                                    <?php endif; ?>
                                    <a href="product.php?id=<?= $p['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($p['name']) ?></a>
                                    <form method="post" class="mt-2">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="action" value="remove" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Price</strong></td>
                            <?php foreach ($products as $p): ?>
                                <td><span class="text-success fw-bold fs-5">KSh <?= number_format($p['price']) ?></span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td><strong>Availability</strong></td>
                            <?php foreach ($products as $p): ?>
                                <td>
                                    <?php if ($p['stock'] > 0): ?>
                                        <span class="badge bg-success">In Stock (<?= $p['stock'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td><strong>Rating</strong></td>
                            <?php foreach ($products as $p): ?>
                                <td>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= round($p['avg_rating']) ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted">(<?= $p['review_count'] ?>)</small>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td><strong>Actions</strong></td>
                            <?php foreach ($products as $p): ?>
                                <td>
                                    <?php if ($p['stock'] > 0): ?>
                                        <button class="btn btn-success btn-sm add-to-cart w-100 mb-2" data-id="<?= $p['id'] ?>"><i class="fas fa-shopping-cart me-1"></i> Add to Cart</button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100 mb-2" disabled>Out of Stock</button>
                                    <?php endif; ?>
                                    <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm w-100">View Details</a>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center mt-4">
                <form method="post">
                    <button type="submit" name="action" value="clear" class="btn btn-outline-danger"><i class="fas fa-trash-alt me-2"></i>Clear All</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include __DIR__ . '/../templates/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/cart.js"></script>
</body>
</html>

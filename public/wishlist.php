<?php
/**
 * Customer Wishlist Page
 * Outsourced Technologies E-Commerce Platform
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/wishlist.php';
require_once __DIR__ . '/../src/cart.php';

// Require login
require_login('login.php');

$user_id = current_user_id();
$wishlist_items = get_wishlist($user_id);
$wishlist_count = count($wishlist_items);

// Handle add to wishlist via GET
if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    if ($product_id > 0) {
        $result = add_to_wishlist($user_id, $product_id);
        if ($result['success']) {
            $message = 'Product added to wishlist';
            $message_type = 'success';
        } else {
            $message = $result['message'] ?? 'Failed to add to wishlist';
            $message_type = 'danger';
        }
        $wishlist_items = get_wishlist($user_id);
        $wishlist_count = count($wishlist_items);
    }
}

// Handle actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            if ($_POST['action'] === 'remove') {
                if (remove_from_wishlist($user_id, $product_id)) {
                    $message = 'Product removed from wishlist';
                    $message_type = 'success';
                    // Refresh the list
                    $wishlist_items = get_wishlist($user_id);
                    $wishlist_count = count($wishlist_items);
                }
            } elseif ($_POST['action'] === 'move_to_cart') {
                $result = move_to_cart($user_id, $product_id);
                if ($result['success']) {
                    $message = 'Product moved to cart';
                    $message_type = 'success';
                    // Refresh the list
                    $wishlist_items = get_wishlist($user_id);
                    $wishlist_count = count($wishlist_items);
                } else {
                    $message = $result['message'];
                    $message_type = 'danger';
                }
            }
        }
    }
}

$page_title = 'My Wishlist - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .wishlist-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .wishlist-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
        }
        .wishlist-img {
            height: 180px;
            object-fit: cover;
        }
        .price-tag {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .out-of-stock {
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../templates/header.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-1">
                    <i class="fas fa-heart text-danger me-2"></i>My Wishlist
                </h1>
                <p class="text-muted mb-0"><?= $wishlist_count ?> product<?= $wishlist_count !== 1 ? 's' : '' ?> saved</p>
            </div>
            <?php if ($wishlist_count > 0): ?>
                <a href="products.php" class="btn btn-outline-primary">
                    <i class="fas fa-plus me-2"></i>Add More
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($wishlist_items)): ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-heart-broken fa-5x text-muted"></i>
                </div>
                <h3 class="mb-3">Your wishlist is empty</h3>
                <p class="text-muted mb-4">Save products you love to see them here</p>
                <a href="products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card wishlist-card h-100 shadow-sm border-0 <?= $item['stock'] < 1 ? 'out-of-stock' : '' ?>">
                            <?php if (!empty($item['image'])): ?>
                                <?php 
                                    $img_url = strpos($item['image'], 'http') === 0 
                                        ? $item['image'] 
                                        : BASE_URL . 'assets/images/products/' . $item['image'];
                                ?>
                                <img src="<?= htmlspecialchars($img_url) ?>" 
                                     class="card-img-top wishlist-img" 
                                     alt="<?= htmlspecialchars($item['product_name']) ?>">
                            <?php else: ?>
                                <div class="bg-light wishlist-img d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <?php if (!empty($item['category_name'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($item['category_name']) ?></small>
                                <?php endif; ?>
                                
                                <h5 class="card-title mt-2">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </h5>
                                
                                <div class="d-flex align-items-center mb-2">
                                    <?php if ($item['avg_rating'] > 0): ?>
                                        <div class="me-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= round($item['avg_rating']) ? '' : '-o' ?> text-warning"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted">(<?= $item['review_count'] ?>)</small>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="card-text text-muted small">
                                    <?= htmlspecialchars(substr($item['short_description'] ?? '', 0, 80)) ?>...
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price-tag text-success">
                                        KSh <?= number_format($item['price']) ?>
                                    </span>
                                    <span class="badge bg-<?= $item['stock'] > 0 ? 'success' : 'danger' ?>">
                                        <?= $item['stock'] > 0 ? $item['stock'] . ' in stock' : 'Out of stock' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-white border-0">
                                <div class="d-grid gap-2">
                                    <?php if ($item['stock'] > 0): ?>
                                        <form method="post">
                                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                            <button type="submit" name="action" value="move_to_cart" 
                                                    class="btn btn-success w-100">
                                                <i class="fas fa-shopping-cart me-2"></i>Move to Cart
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2">
                                        <a href="product.php?id=<?= $item['product_id'] ?>" 
                                           class="btn btn-outline-primary flex-grow-1">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <form method="post" class="flex-grow-1">
                                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                            <button type="submit" name="action" value="remove" 
                                                    class="btn btn-outline-danger w-100">
                                                <i class="fas fa-trash me-1"></i>Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($wishlist_count > 1): ?>
                <div class="text-center mt-4">
                    <form method="post" id="clearForm">
                        <input type="hidden" name="action" value="clear">
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="if(confirm('Clear entire wishlist?')) document.getElementById('clearForm').submit();">
                            <i class="fas fa-trash-alt me-2"></i>Clear All
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include __DIR__ . '/../templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

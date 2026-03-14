<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';

// Handle compare action
if (isset($_GET['compare']) && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $action = $_GET['compare'];
    
    if ($action === 'add' && $product_id > 0) {
        $product = fetchOne("SELECT id FROM products WHERE id = ? AND visible = 1", [$product_id]);
        if ($product) {
            if (!isset($_SESSION['comparison'])) {
                $_SESSION['comparison'] = [];
            }
            if (!in_array($product_id, $_SESSION['comparison']) && count($_SESSION['comparison']) < 4) {
                $_SESSION['comparison'][] = $product_id;
            }
        }
    } elseif ($action === 'remove' && $product_id > 0) {
        if (isset($_SESSION['comparison'])) {
            $_SESSION['comparison'] = array_filter($_SESSION['comparison'], function($id) use ($product_id) {
                return $id != $product_id;
            });
            $_SESSION['comparison'] = array_values($_SESSION['comparison']);
        }
    }
    
    // Clean URL and redirect
    $redirect = 'product.php?id=' . $product_id;
    header('Location: ' . $redirect);
    exit;
}
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/reviews.php';

$id = (int)($_GET['id'] ?? 0);
$slug = $_GET['slug'] ?? '';

if ($id > 0) {
    $product = fetchOne(
        "SELECT p.*, c.name as category_name,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.id = ? AND p.visible = 1", 
        [$id]
    );
} elseif (!empty($slug)) {
    $product = fetchOne(
        "SELECT p.*, c.name as category_name,
                (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.slug = ? AND p.visible = 1", 
        [$slug]
    );
    $id = $product['id'] ?? 0;
} else {
    $product = null;
}

if (!$product) {
    http_response_code(404);
    echo "<h1>404 - Product Not Found</h1>";
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

// Get related products
$related_products = fetchAll(
    "SELECT p.id, p.name, p.slug, p.price,
            (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
     FROM products p
     WHERE p.category_id = ? AND p.id != ? AND p.visible = 1
     ORDER BY p.created_at DESC LIMIT 4",
    [$product['category_id'], $id]
);

// Get all product images for gallery
$product_images = fetchAll(
    "SELECT filename, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC",
    [$id]
);

// Get related services for upsell
$related_services = fetchAll(
    "SELECT * FROM services WHERE visible = 1 ORDER BY created_at DESC LIMIT 3"
);

$rating = get_product_rating($id);
$reviews = get_product_reviews($id);

$review_message = '';
$review_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) {
        header('Location: login.php?redirect=product.php?id=' . $id);
        exit;
    }
    
    $rating_val = (int)$_POST['rating'];
    $review_text = sanitize($_POST['review_text'] ?? '');
    
    if ($rating_val < 1 || $rating_val > 5) {
        $review_message = 'Please select a rating';
        $review_type = 'danger';
    } else {
        $result = add_review($id, $_SESSION['user_id'], $rating_val, $review_text);
        $review_message = $result['message'];
        $review_type = $result['success'] ? 'success' : 'danger';
        
        if ($result['success']) {
            $reviews = get_product_reviews($id);
            $rating = get_product_rating($id);
        }
    }
}

$page_title = $product['name'];
require_once __DIR__ . '/../templates/header.php';
?>

<!-- Page Header -->
<section class="bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                <?php if (!empty($product['category_name'])): ?>
                    <p class="text-muted mb-0">
                        <a href="products.php?category=<?= htmlspecialchars(strtolower($product['category_name'])) ?>" class="text-decoration-none">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-md-end mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <!-- Product Details -->
    <div class="row g-5">
        <div class="col-lg-6">
            <!-- Product Image Gallery -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (!empty($product_images)): ?>
                        <!-- Main Image -->
                        <div class="main-image-container mb-3" style="height: 400px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px;">
                            <img id="mainImage" src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($product_images[0]['filename']) ?>" 
                                 class="img-fluid" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 style="max-height: 100%; max-width: 100%; object-fit: contain;">
                        </div>
                        <!-- Thumbnail Gallery -->
                        <?php if (count($product_images) > 1): ?>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <?php foreach ($product_images as $index => $img): ?>
                                    <button class="thumbnail-btn p-1 border rounded" 
                                            onclick="document.getElementById('mainImage').src='<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($img['filename']) ?>';"
                                            style="width: 70px; height: 70px; cursor: pointer; background: #f8f9fa; border: 2px solid transparent;">
                                        <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($img['filename']) ?>"
                                             class="img-fluid"
                                             style="width: 100%; height: 100%; object-fit: contain;"
                                             alt="Thumbnail <?= $index + 1 ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!empty($product['image'])): ?>
                        <div class="main-image-container" style="height: 400px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px;">
                            <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($product['image']) ?>" 
                                 class="img-fluid" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 style="max-height: 100%; max-width: 100%; object-fit: contain;">
                        </div>
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 350px;">
                            <i class="fas fa-image fs-1 text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Rating -->
            <div class="d-flex align-items-center mb-3">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?= $i <= round($rating['average']) ? '' : '-o' ?> text-warning"></i>
                <?php endfor; ?>
                <span class="text-muted ms-2">(<?= $rating['count'] ?> reviews)</span>
            </div>
            
            <!-- Price -->
            <div class="mb-3">
                <?php if (!empty($product['compare_at_price']) && $product['compare_at_price'] > $product['price']): ?>
                    <span class="fs-2 fw-bold text-primary">KSh <?= number_format($product['price']) ?></span>
                    <span class="fs-5 text-muted text-decoration-line-through ms-2">KSh <?= number_format($product['compare_at_price']) ?></span>
                <?php else: ?>
                    <span class="fs-2 fw-bold text-primary">KSh <?= number_format($product['price']) ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Stock -->
            <div class="mb-4">
                <?php if ($product['stock'] > 0): ?>
                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> In Stock (<?= $product['stock'] ?>)</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><i class="fas fa-times-circle me-1"></i> Out of Stock</span>
                <?php endif; ?>
            </div>
            
            <!-- Description -->
            <div class="mb-4">
                <h5>Description</h5>
                <p class="text-muted"><?= nl2br(htmlspecialchars($product['short_description'] ?? $product['full_description'] ?? 'No description available.')) ?></p>
            </div>
            
            <!-- SKU -->
            <p class="text-muted small mb-4"><strong>SKU:</strong> <?= htmlspecialchars($product['sku']) ?></p>
            
            <!-- Add to Cart -->
            <?php if ($product['stock'] > 0): ?>
                <div class="d-flex gap-3 align-items-center mb-3">
                    <div class="input-group" style="width: 130px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="decreaseQty()">-</button>
                        <input type="number" class="form-control text-center" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="increaseQty()">+</button>
                    </div>
                    <button class="btn btn-primary btn-lg flex-grow-1 add-to-cart" data-id="<?= $product['id'] ?>">
                        <i class="fas fa-cart-plus me-2"></i> Add to Cart
                    </button>
                </div>
                <?php 
                // Check if in comparison
                $in_compare = isset($_SESSION['comparison']) && in_array($product['id'], $_SESSION['comparison']);
                ?>
                <div class="d-flex gap-2">
                    <a href="wishlist.php?add=<?= $product['id'] ?>" class="btn btn-outline-danger flex-grow-1">
                        <i class="fas fa-heart me-2"></i> Wishlist
                    </a>
                    <a href="?compare=add&id=<?= $product['id'] ?>" class="btn <?= $in_compare ? 'btn-success' : 'btn-outline-secondary' ?> flex-grow-1">
                        <i class="fas fa-balance-scale me-2"></i> <?= $in_compare ? 'In Compare' : 'Compare' ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle me-2"></i> This product is currently out of stock.
                </div>
                <button class="btn btn-secondary btn-lg" disabled>Out of Stock</button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Full Description & Tabs -->
    <?php if (!empty($product['full_description'])): ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#description" type="button">
                                <i class="fas fa-align-left me-2"></i>Description
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#specs" type="button">
                                <i class="fas fa-list-alt me-2"></i>Specifications
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="description">
                            <p><?= nl2br(htmlspecialchars($product['full_description'])) ?></p>
                        </div>
                        <div class="tab-pane fade" id="specs">
                            <table class="table table-striped">
                                <tbody>
                                    <tr><th>SKU</th><td><?= htmlspecialchars($product['sku']) ?></td></tr>
                                    <tr><th>Category</th><td><?= htmlspecialchars($product['category_name']) ?></td></tr>
                                    <tr><th>Stock</th><td><?= $product['stock'] > 0 ? 'Available (' . $product['stock'] . ')' : 'Out of Stock' ?></td></tr>
                                    <?php if (!empty($product['brand'])): ?>
                                    <tr><th>Brand</th><td><?= htmlspecialchars($product['brand']) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!empty($product['weight'])): ?>
                                    <tr><th>Weight</th><td><?= htmlspecialchars($product['weight']) ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Service Upsell Section -->
    <?php if (!empty($related_services)): ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h5 class="mb-4"><i class="fas fa-tools me-2"></i>Professional Services Available</h5>
                    <div class="row g-3">
                        <?php foreach ($related_services as $service): ?>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h6><?= htmlspecialchars($service['name']) ?></h6>
                                    <p class="text-muted small mb-2"><?= htmlspecialchars(substr($service['description'], 0, 80)) ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-primary">KSh <?= number_format($service['price']) ?></span>
                                        <a href="services.php?book=<?= $service['id'] ?>" class="btn btn-sm btn-outline-primary">Book Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Reviews Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-star text-warning me-2"></i>Reviews (<?= $rating['count'] ?>)</h5>
                </div>
                <div class="card-body">
                    <!-- Rating Summary -->
                    <div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
                        <div class="me-4 text-center">
                            <span class="display-4 fw-bold text-primary"><?= $rating['average'] ?></span>/5
                            <div class="mt-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($rating['average']) ? '' : '-o' ?> text-warning"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted"><?= $rating['count'] ?> reviews</small>
                        </div>
                        <div class="flex-grow-1">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <?php 
                                $percent = $rating['count'] > 0 ? 
                                    (count(array_filter($reviews, fn($r) => $r['rating'] == $i)) / $rating['count'] * 100) : 0;
                                ?>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="me-2" style="width: 20px;"><?= $i ?></span>
                                    <i class="fas fa-star text-warning"></i>
                                    <div class="progress flex-grow-1 mx-2" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <small class="text-muted" style="width: 30px;"><?= round($percent) ?>%</small>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Submit Review -->
                    <?php if (is_logged_in()): ?>
                        <div class="border rounded p-3 mb-4">
                            <h6>Write a Review</h6>
                            <?php if ($review_message): ?>
                                <div class="alert alert-<?= $review_type ?> py-2"><?= htmlspecialchars($review_message) ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-2">
                                    <label class="form-label">Rating</label>
                                    <div class="star-rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" <?= $i == 5 ? 'checked' : '' ?>>
                                            <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <textarea name="review_text" class="form-control" rows="2" placeholder="Share your experience..."></textarea>
                                </div>
                                <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-4">
                            <a href="login.php?redirect=product.php?id=<?= $id ?>">Login</a> to write a review
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reviews List -->
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted text-center py-3">No reviews yet. Be the first to review this product!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="border-bottom py-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong><?= htmlspecialchars($review['full_name'] ?? $review['username'] ?? 'Anonymous') ?></strong>
                                    <span class="text-muted small"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                                </div>
                                <div class="mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?> text-warning"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
    <div class="mt-5">
        <h4 class="mb-4">Related Products</h4>
        <div class="row g-4">
            <?php foreach ($related_products as $rp): ?>
            <div class="col-6 col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <?php if (!empty($rp['image'])): ?>
                        <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($rp['image']) ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($rp['name']) ?>" style="height: 180px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                            <i class="fas fa-image text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h6 class="mb-1" style="font-size: 14px;"><?= htmlspecialchars($rp['name']) ?></h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-primary">KSh <?= number_format($rp['price']) ?></span>
                        </div>
                        <a href="product.php?id=<?= $rp['id'] ?>" class="btn btn-outline-primary btn-sm w-100 mt-2">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.star-rating { display: flex; flex-direction: row-reverse; gap: 5px; }
.star-rating input { display: none; }
.star-rating label { cursor: pointer; color: #ddd; font-size: 20px; }
.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label { color: #ffc107; }
</style>

<script>
function decreaseQty() {
    const qty = document.getElementById('quantity');
    if (qty.value > 1) qty.value = parseInt(qty.value) - 1;
}
function increaseQty() {
    const qty = document.getElementById('quantity');
    if (parseInt(qty.value) < parseInt(qty.max)) qty.value = parseInt(qty.value) + 1;
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<!-- Floating Compare Bar -->
<?php 
$comparison_items = [];
if (!empty($_SESSION['comparison'])) {
    $ids = $_SESSION['comparison'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $comparison_items = fetchAll("
        SELECT id, name, price, 
               (SELECT filename FROM product_images WHERE product_images.product_id = p.id AND is_main = 1 LIMIT 1) as image
        FROM products p 
        WHERE id IN ($placeholders)
    ", $ids);
}
$comparison_count = count($comparison_items);
?>

<?php if ($comparison_count > 0): ?>
<div class="compare-bar show">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div class="compare-bar-items flex-grow-1">
                <?php foreach ($comparison_items as $item): ?>
                <div class="compare-bar-item">
                    <?php $remove_url = 'product.php?id=' . ($_GET['id'] ?? '') . '&compare=remove&id=' . $item['id']; ?>
                    <a href="<?= $remove_url ?>" class="remove-compare">&times;</a>
                    <?php if (!empty($item['image'])): ?>
                    <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="width:60px;height:60px;border-radius:6px;"><i class="fas fa-image text-muted"></i></div>
                    <?php endif; ?>
                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-2 align-items-center ms-3">
                <a href="products.php?clear_compare=1" class="btn btn-outline-danger btn-sm">Clear</a>
                <a href="compare.php" class="btn btn-primary btn-compare">
                    Compare (<?= $comparison_count ?>)
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.compare-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
    padding: 15px 20px;
    z-index: 1000;
}

.compare-bar-items {
    display: flex;
    gap: 10px;
    align-items: center;
    overflow-x: auto;
    padding-bottom: 5px;
}

.compare-bar-item {
    min-width: 80px;
    max-width: 80px;
    text-align: center;
    position: relative;
}

.compare-bar-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.compare-bar-item .remove-compare {
    position: absolute;
    top: -8px;
    right: 8px;
    background: red;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.compare-bar-item .item-name {
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.compare-bar .btn-compare {
    white-space: nowrap;
}
</style>

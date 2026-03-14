<?php
/**
 * Wishlist Button Component
 * Include this in product cards and detail pages
 * 
 * Usage: 
 *   $is_logged_in = is_logged_in();
 *   $in_wishlist = $is_logged_in ? is_in_wishlist(current_user_id(), $product_id) : false;
 *   include __DIR__ . '/wishlist-button.php';
 * 
 * Required variables:
 *   - $product_id (int)
 *   - $is_logged_in (bool)
 *   - $in_wishlist (bool)
 */

$product_id = $product_id ?? 0;
$is_logged_in = $is_logged_in ?? false;
$in_wishlist = $in_wishlist ?? false;
?>

<?php if ($is_logged_in): ?>
    <button class="btn btn-sm wishlist-btn <?= $in_wishlist ? 'btn-danger' : 'btn-outline-danger' ?>"
            data-product-id="<?= $product_id ?>"
            data-in-wishlist="<?= $in_wishlist ? '1' : '0' ?>"
            title="<?= $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>">
        <i class="<?= $in_wishlist ? 'fas' : 'far' ?> fa-heart"></i>
    </button>
<?php else: ?>
    <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? 'products.php') ?>" 
       class="btn btn-sm btn-outline-danger"
       title="Login to add to wishlist">
        <i class="far fa-heart"></i>
    </a>
<?php endif; ?>

<style>
.wishlist-btn {
    transition: all 0.3s ease;
}
.wishlist-btn:hover {
    transform: scale(1.1);
}
.wishlist-btn.adding {
    animation: pulse 0.5s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
</style>

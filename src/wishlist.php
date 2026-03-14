<?php
/**
 * Wishlist functionality
 * Outsourced Technologies E-Commerce Platform
 */

// Add product to wishlist
function add_to_wishlist(int $user_id, int $product_id): array {
    global $pdo;
    
    // Check if product exists and is visible
    $product = fetchOne("SELECT id, name, price FROM products WHERE id = ? AND visible = 1", [$product_id]);
    
    if (!$product) {
        return [
            'success' => false,
            'message' => 'Product not found or unavailable'
        ];
    }
    
    // Check if already in wishlist
    $existing = fetchOne(
        "SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?",
        [$user_id, $product_id]
    );
    
    if ($existing) {
        // Update timestamp (move to top)
        query("UPDATE wishlists SET created_at = NOW() WHERE user_id = ? AND product_id = ?", 
            [$user_id, $product_id]);
        
        return [
            'success' => true,
            'message' => 'Product moved to top of wishlist'
        ];
    }
    
    // Add to wishlist
    try {
        query("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)", 
            [$user_id, $product_id]);
        
        return [
            'success' => true,
            'message' => 'Product added to wishlist'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to add to wishlist'
        ];
    }
}

// Remove product from wishlist
function remove_from_wishlist(int $user_id, int $product_id): bool {
    $result = query(
        "DELETE FROM wishlists WHERE user_id = ? AND product_id = ?",
        [$user_id, $product_id]
    );
    return $result->rowCount() > 0;
}

// Get user's wishlist with product details
function get_wishlist(int $user_id): array {
    return fetchAll("
        SELECT 
            w.id AS wishlist_id,
            w.created_at AS added_at,
            p.id AS product_id,
            p.name AS product_name,
            p.price,
            (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) AS image,
            p.stock,
            p.short_description,
            c.name AS category_name,
            COALESCE((
                SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id
            ), 0) AS avg_rating,
            COALESCE((
                SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id
            ), 0) AS review_count
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ? AND p.visible = 1
        ORDER BY w.created_at DESC
    ", [$user_id]);
}

// Check if product is in user's wishlist
function is_in_wishlist(int $user_id, int $product_id): bool {
    $result = fetchOne(
        "SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?",
        [$user_id, $product_id]
    );
    return !empty($result);
}

// Get wishlist count for user
function get_wishlist_count(int $user_id): int {
    $result = fetchOne(
        "SELECT COUNT(*) as count FROM wishlists WHERE user_id = ?",
        [$user_id]
    );
    return $result['count'] ?? 0;
}

// Clear entire wishlist
function clear_wishlist(int $user_id): bool {
    query("DELETE FROM wishlists WHERE user_id = ?", [$user_id]);
    return true;
}

// Move product from wishlist to cart
function move_to_cart(int $user_id, int $product_id): array {
    global $pdo;
    
    // Check if product is in wishlist
    if (!is_in_wishlist($user_id, $product_id)) {
        return [
            'success' => false,
            'message' => 'Product not in wishlist'
        ];
    }
    
    // Get product details
    $product = fetchOne("SELECT id, name, price, stock FROM products WHERE id = ? AND visible = 1", [$product_id]);
    
    if (!$product) {
        return [
            'success' => false,
            'message' => 'Product no longer available'
        ];
    }
    
    if ($product['stock'] < 1) {
        return [
            'success' => false,
            'message' => 'Product out of stock'
        ];
    }
    
    // Add to cart
    $add_result = cart_add($product_id, 1);
    
    if ($add_result) {
        // Remove from wishlist
        remove_from_wishlist($user_id, $product_id);
        
        return [
            'success' => true,
            'message' => 'Product moved to cart'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to add to cart'
    ];
}

// Get wishlist items as JSON (for AJAX)
function get_wishlist_json(int $user_id): string {
    $items = get_wishlist($user_id);
    return json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
}

// Add to recently viewed products
function add_to_recently_viewed(?int $user_id, int $product_id): void {
    global $pdo;
    
    if ($user_id) {
        // For logged in users, add to database
        try {
            query(
                "INSERT INTO recently_viewed (user_id, product_id) VALUES (?, ?)",
                [$user_id, $product_id]
            );
            
            // Keep only last 50 items per user
            query("
                DELETE FROM recently_viewed 
                WHERE user_id = ? 
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM recently_viewed 
                        WHERE user_id = ? 
                        ORDER BY viewed_at DESC 
                        LIMIT 50
                    ) AS temp
                )
            ", [$user_id, $user_id]);
        } catch (Exception $e) {
            // Silently fail - not critical
        }
    }
    
    // Also store in session for guest users
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    
    // Remove if already exists (to move to front)
    $_SESSION['recently_viewed'] = array_filter(
        $_SESSION['recently_viewed'], 
        function($id) use ($product_id) { 
            return $id != $product_id; 
        }
    );
    
    // Add to front
    array_unshift($_SESSION['recently_viewed'], $product_id);
    
    // Keep only last 20 in session
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 20);
}

// Get recently viewed products
function get_recently_viewed(?int $user_id, int $limit = 10): array {
    $products = [];
    
    if ($user_id) {
        // Get from database for logged in users
        $items = fetchAll("
            SELECT DISTINCT
                p.id,
                p.name,
                p.price,
                p.image,
                p.short_description,
                r.viewed_at
            FROM recently_viewed r
            JOIN products p ON r.product_id = p.id
            WHERE r.user_id = ? AND p.visible = 1
            ORDER BY r.viewed_at DESC
            LIMIT ?
        ", [$user_id, $limit]);
        
        $products = $items;
    } elseif (isset($_SESSION['recently_viewed']) && !empty($_SESSION['recently_viewed'])) {
        // Get from session for guest users
        $ids = array_slice($_SESSION['recently_viewed'], 0, $limit);
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $products = fetchAll("
                SELECT id, name, price, image, short_description
                FROM products 
                WHERE id IN ($placeholders) AND visible = 1
            ", $ids);
            
            // Reorder to match session order
            $ordered = [];
            foreach ($ids as $id) {
                foreach ($products as $p) {
                    if ($p['id'] == $id) {
                        $ordered[] = $p;
                        break;
                    }
                }
            }
            $products = $ordered;
        }
    }
    
    return $products;
}

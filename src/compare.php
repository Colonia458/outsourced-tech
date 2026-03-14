<?php
/**
 * Product Comparison Functions
 * Outsourced Technologies E-Commerce Platform
 */

// Get comparison items from session
function get_comparison_items(): array {
    if (!isset($_SESSION['comparison'])) {
        $_SESSION['comparison'] = [];
    }
    return $_SESSION['comparison'];
}

// Add product to comparison
function add_to_comparison(int $product_id): array {
    // Verify product exists and is visible
    $product = fetchOne("SELECT id, name FROM products WHERE id = ? AND visible = 1", [$product_id]);
    
    if (!$product) {
        return [
            'success' => false,
            'message' => 'Product not found'
        ];
    }
    
    $items = get_comparison_items();
    
    // Check if already in comparison
    if (in_array($product_id, $items)) {
        return [
            'success' => false,
            'message' => 'Product already in comparison',
            'count' => count($items)
        ];
    }
    
    // Limit to 4 products
    if (count($items) >= 4) {
        return [
            'success' => false,
            'message' => 'Maximum 4 products can be compared at once',
            'count' => count($items)
        ];
    }
    
    $_SESSION['comparison'][] = $product_id;
    
    return [
        'success' => true,
        'message' => 'Product added to comparison',
        'count' => count($items)
    ];
}

// Remove product from comparison
function remove_from_comparison(int $product_id): bool {
    $items = get_comparison_items();
    $items = array_filter($items, function($id) use ($product_id) {
        return $id != $product_id;
    });
    $_SESSION['comparison'] = array_values($items);
    return true;
}

// Clear all comparison items
function clear_comparison(): void {
    $_SESSION['comparison'] = [];
}

// Get comparison products with details
function get_comparison_products(): array {
    $items = get_comparison_items();
    
    if (empty($items)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($items), '?'));
    
    $products = fetchAll("
        SELECT 
            p.id, p.name, p.price, p.image, p.stock, p.short_description, p.visible, p.category_id,
            c.name as category_name,
            COALESCE((
                SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id
            ), 0) AS avg_rating,
            COALESCE((
                SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id
            ), 0) AS review_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.visible = 1
    ", $items);
    
    // Reorder to match session order
    $ordered = [];
    foreach ($items as $id) {
        foreach ($products as $p) {
            if ($p['id'] == $id) {
                $ordered[] = $p;
                break;
            }
        }
    }
    
    return $ordered;
}

// Check if product is in comparison
function is_in_comparison(int $product_id): bool {
    return in_array($product_id, get_comparison_items());
}

// Get comparison count
function get_comparison_count(): int {
    return count(get_comparison_items());
}

// Toggle comparison (add if not, remove if is)
function toggle_comparison(int $product_id): array {
    if (is_in_comparison($product_id)) {
        remove_from_comparison($product_id);
        return [
            'success' => true,
            'action' => 'removed',
            'count' => get_comparison_count()
        ];
    } else {
        return add_to_comparison($product_id);
    }
}

// Get comparison JSON for AJAX
function get_comparison_json(): string {
    $items = get_comparison_products();
    return json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
}

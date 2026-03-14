<?php
// src/reviews.php - Product reviews and ratings

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get reviews for a product
 */
function get_product_reviews($product_id) {
    return fetchAll(
        "SELECT r.*, u.username, u.full_name 
         FROM product_reviews r 
         LEFT JOIN users u ON r.user_id = u.id 
         WHERE r.product_id = ? AND r.is_approved = 1 
         ORDER BY r.created_at DESC",
        [$product_id]
    );
}

/**
 * Get average rating for a product
 */
function get_product_rating($product_id) {
    $result = fetchOne(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
         FROM product_reviews 
         WHERE product_id = ? AND is_approved = 1",
        [$product_id]
    );
    
    return [
        'average' => round($result['avg_rating'] ?? 0, 1),
        'count' => $result['total_reviews'] ?? 0
    ];
}

/**
 * Add a review (pending approval)
 */
function add_review($product_id, $user_id, $rating, $review_text, $order_id = null) {
    // Check if user already reviewed this product
    $existing = fetchOne(
        "SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?",
        [$product_id, $user_id]
    );
    
    if ($existing) {
        return ['success' => false, 'message' => 'You have already reviewed this product'];
    }
    
    $id = db_insert('product_reviews', [
        'product_id' => $product_id,
        'user_id' => $user_id,
        'order_id' => $order_id,
        'rating' => $rating,
        'review_text' => $review_text,
        'is_approved' => 0 // Requires admin approval
    ]);
    
    if ($id) {
        return ['success' => true, 'message' => 'Review submitted! It will appear after approval.'];
    }
    
    return ['success' => false, 'message' => 'Failed to submit review'];
}

/**
 * Get all reviews (for admin)
 */
function get_all_reviews($approved = null) {
    $sql = "SELECT r.*, u.username, u.full_name, p.name as product_name 
            FROM product_reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            LEFT JOIN products p ON r.product_id = p.id";
    
    if ($approved !== null) {
        $sql .= " WHERE r.is_approved = " . ($approved ? '1' : '0');
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    return fetchAll($sql);
}

/**
 * Approve or reject a review
 */
function moderate_review($review_id, $approve = true) {
    query(
        "UPDATE product_reviews SET is_approved = ? WHERE id = ?",
        [$approve ? 1 : 0, $review_id]
    );
}

/**
 * Delete a review
 */
function delete_review($review_id) {
    query("DELETE FROM product_reviews WHERE id = ?", [$review_id]);
}

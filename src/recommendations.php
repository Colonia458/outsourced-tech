<?php
// src/recommendations.php - Product Recommendation Engine

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Log product interaction
 */
function log_product_interaction($data) {
    $db = Database::getInstance()->getConnection();
    
    $userId = $data['user_id'] ?? null;
    $sessionId = $data['session_id'] ?? null;
    $productId = $data['product_id'];
    $interactionType = $data['interaction_type'];
    $rating = $data['rating'] ?? null;
    $priceAtTime = $data['price_at_time'] ?? null;
    $referrer = $data['referrer'] ?? null;
    $ipAddress = $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    
    $stmt = $db->prepare("
        INSERT INTO product_interactions 
        (user_id, session_id, product_id, interaction_type, rating, price_at_time, referrer, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('isisdsss', $userId, $sessionId, $productId, $interactionType, $rating, $priceAtTime, $referrer, $ipAddress);
    $stmt->execute();
    
    // Update recommendations after new interaction
    if ($userId && in_array($interactionType, ['purchase', 'wishlist'])) {
        generate_personalized_recommendations($userId);
    }
    
    return $db->insert_id;
}

/**
 * Get personalized recommendations for user
 */
function get_personalized_recommendations($userId, $limit = 10) {
    $db = Database::getInstance()->getConnection();
    
    // Check cache first
    $stmt = $db->prepare("
        SELECT pr.*, p.name, p.price, p.image, p.category_id
        FROM product_recommendations pr
        JOIN products p ON pr.product_id = p.id
        WHERE pr.user_id = ? 
        AND pr.recommendation_type = 'personalized'
        AND pr.generated_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ORDER BY pr.score DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $cached = [];
    
    while ($row = $result->fetch_assoc()) {
        $cached[] = $row;
    }
    
    if (!empty($cached)) {
        return $cached;
    }
    
    // Generate fresh recommendations
    generate_personalized_recommendations($userId);
    
    // Try again
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recommendations = [];
    while ($row = $result->fetch_assoc()) {
        $recommendations[] = $row;
    }
    
    return $recommendations;
}

/**
 * Generate personalized recommendations for user
 */
function generate_personalized_recommendations($userId) {
    $db = Database::getInstance()->getConnection();
    
    // Get user's purchase history
    $stmt = $db->prepare("
        SELECT DISTINCT product_id FROM product_interactions 
        WHERE user_id = ? AND interaction_type = 'purchase'
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $purchased = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($purchased)) {
        // No history - return popular products
        return get_popular_products($userId, 10);
    }
    
    // Get categories of purchased products
    $productIds = array_column($purchased, 'product_id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    
    $stmt = $db->prepare("
        SELECT DISTINCT category_id FROM products 
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param(str_repeat('i', count($productIds)), ...$productIds);
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($categories)) {
        return get_popular_products($userId, 10);
    }
    
    $categoryIds = array_column($categories, 'category_id');
    
    // Get similar products from same categories
    $catPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $prodPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
    
    $sql = "
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as interaction_count,
            AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id
        WHERE p.category_id IN ($catPlaceholders)
        AND p.id NOT IN ($prodPlaceholders)
        AND p.status = 'active'
        GROUP BY p.id
        ORDER BY interaction_count DESC, avg_rating DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($sql);
    $allParams = array_merge($categoryIds, $productIds);
    $stmt->bind_param(str_repeat('i', count($allParams)), ...$allParams);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $recommendations = [];
    $score = 1.0;
    
    while ($row = $result->fetch_assoc()) {
        $recommendations[] = [
            'product_id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'image' => $row['image'],
            'category_id' => $row['category_id'],
            'score' => $score,
            'recommendation_type' => 'personalized',
        ];
        
        $score -= 0.05;
    }
    
    // Cache recommendations
    cache_recommendations($userId, $recommendations, 'personalized');
    
    return $recommendations;
}

/**
 * Get similar products
 */
function get_similar_products($productId, $limit = 6) {
    $db = Database::getInstance()->getConnection();
    
    // Check precomputed similar products
    $stmt = $db->prepare("
        SELECT sp.similarity_score, p.id, p.name, p.price, p.image, p.category_id
        FROM similar_products sp
        JOIN products p ON sp.product_b_id = p.id
        WHERE sp.product_a_id = ?
        ORDER BY sp.similarity_score DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $productId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $similar = [];
    while ($row = $result->fetch_assoc()) {
        $similar[] = $row;
    }
    
    if (!empty($similar)) {
        return $similar;
    }
    
    // Compute similar products on the fly
    return compute_similar_products($productId, $limit);
}

/**
 * Compute similar products based on category and interactions
 */
function compute_similar_products($productId, $limit = 6) {
    $db = Database::getInstance()->getConnection();
    
    // Get the product's category
    $stmt = $db->prepare("SELECT category_id FROM products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if (!$product) {
        return [];
    }
    
    // Get products from same category
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as common_interactions,
            0.5 as similarity_score
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id 
            AND pi.user_id IN (
                SELECT DISTINCT user_id FROM product_interactions 
                WHERE product_id = ? AND user_id IS NOT NULL
            )
        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
        GROUP BY p.id
        ORDER BY common_interactions DESC
        LIMIT ?
    ");
    $stmt->bind_param('iiii', $productId, $product['category_id'], $productId, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $similar = [];
    
    while ($row = $result->fetch_assoc()) {
        $similar[] = $row;
    }
    
    return $similar;
}

/**
 * Get frequently bought together
 */
function get_frequently_bought_together($productId, $limit = 6) {
    $db = Database::getInstance()->getConnection();
    
    // Find orders that contain this product
    $stmt = $db->prepare("
        SELECT DISTINCT oi.order_id FROM order_items oi
        WHERE oi.product_id = ?
    ");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($orders)) {
        return get_similar_products($productId, $limit);
    }
    
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    
    // Find other products in same orders
    $stmt = $db->prepare("
        SELECT oi.product_id, COUNT(*) as co_occurrence,
            p.name, p.price, p.image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
        AND oi.product_id != ?
        AND p.status = 'active'
        GROUP BY oi.product_id
        ORDER BY co_occurrence DESC
        LIMIT ?
    ");
    
    $allParams = array_merge($orderIds, [$productId, $limit]);
    $stmt->bind_param(str_repeat('i', count($allParams)), ...$allParams);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    return $products;
}

/**
 * Get popular products
 */
function get_popular_products($userId = null, $limit = 10) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as view_count,
            SUM(CASE WHEN pi.interaction_type = 'purchase' THEN 1 ELSE 0 END) as purchase_count,
            AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY purchase_count DESC, view_count DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $products = [];
    $score = 1.0;
    
    while ($row = $result->fetch_assoc()) {
        $row['score'] = $score;
        $products[] = $row;
        $score -= 0.1;
    }
    
    // Cache if user provided
    if ($userId) {
        cache_recommendations($userId, $products, 'popular');
    }
    
    return $products;
}

/**
 * Get category-based recommendations
 */
function get_category_recommendations($categoryId, $limit = 10) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as interactions,
            AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id
        WHERE p.category_id = ? AND p.status = 'active'
        GROUP BY p.id
        ORDER BY interactions DESC, avg_rating DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $categoryId, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    return $products;
}

/**
 * Cache recommendations
 */
function cache_recommendations($userId, $products, $type) {
    $db = Database::getInstance()->getConnection();
    
    // Delete old cached recommendations
    $stmt = $db->prepare("
        DELETE FROM product_recommendations 
        WHERE user_id = ? AND recommendation_type = ?
    ");
    $stmt->bind_param('is', $userId, $type);
    $stmt->execute();
    
    // Insert new recommendations
    foreach ($products as $product) {
        $stmt = $db->prepare("
            INSERT INTO product_recommendations 
            (user_id, product_id, recommendation_type, score)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iisd', $userId, $product['product_id'], $type, $product['score']);
        $stmt->execute();
    }
}

/**
 * Compute similar products (cron job)
 */
function compute_all_similar_products() {
    $db = Database::getInstance()->getConnection();
    
    // Get all products
    $result = $db->query("SELECT id, category_id FROM products WHERE status = 'active'");
    $products = $result->fetch_all(MYSQLI_ASSOC);
    
    foreach ($products as $product) {
        // Find users who viewed this product
        $stmt = $db->prepare("
            SELECT DISTINCT user_id FROM product_interactions 
            WHERE product_id = ? AND user_id IS NOT NULL
        ");
        $stmt->bind_param('i', $product['id']);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($users)) {
            continue;
        }
        
        $userIds = array_column($users, 'user_id');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        
        // Find other products these users interacted with
        $stmt = $db->prepare("
            SELECT product_id, COUNT(DISTINCT user_id) as user_count
            FROM product_interactions
            WHERE user_id IN ($placeholders)
            AND product_id != ?
            GROUP BY product_id
            ORDER BY user_count DESC
            LIMIT 20
        ");
        
        $allParams = array_merge($userIds, [$product['id']]);
        $stmt->bind_param(str_repeat('i', count($allParams)), ...$allParams);
        $stmt->execute();
        
        $similar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate similarity score and save
        $maxUsers = max(array_column($similar, 'user_count'));
        
        foreach ($similar as $item) {
            $score = $item['user_count'] / $maxUsers;
            
            // Check if exists
            $stmt2 = $db->prepare("
                SELECT id FROM similar_products 
                WHERE product_a_id = ? AND product_b_id = ?
            ");
            $stmt2->bind_param('ii', $product['id'], $item['product_id']);
            $stmt2->execute();
            
            if ($stmt2->get_result()->num_rows > 0) {
                $stmt2 = $db->prepare("
                    UPDATE similar_products SET similarity_score = ? 
                    WHERE product_a_id = ? AND product_b_id = ?
                ");
                $stmt2->bind_param('dii', $score, $product['id'], $item['product_id']);
            } else {
                $stmt2 = $db->prepare("
                    INSERT INTO similar_products (product_a_id, product_b_id, similarity_score)
                    VALUES (?, ?, ?)
                ");
                $stmt2->bind_param('iid', $product['id'], $item['product_id'], $score);
            }
            $stmt2->execute();
        }
    }
    
    return ['success' => true, 'message' => 'Similar products computed'];
}

/**
 * Get recommendations for API
 */
function get_recommendations_api($userId, $type = 'personalized', $limit = 10) {
    switch ($type) {
        case 'personalized':
            return get_personalized_recommendations($userId, $limit);
        case 'popular':
            return get_popular_products($userId, $limit);
        case 'similar':
            $productId = $_GET['product_id'] ?? null;
            if ($productId) {
                return get_similar_products($productId, $limit);
            }
            return [];
        case 'frequently_bought':
            $productId = $_GET['product_id'] ?? null;
            if ($productId) {
                return get_frequently_bought_together($productId, $limit);
            }
            return [];
        default:
            return get_personalized_recommendations($userId, $limit);
    }
}

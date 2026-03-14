<?php
/**
 * Advanced Search & Filter Functions
 * Outsourced Technologies E-Commerce Platform
 */

/**
 * Build search query with filters
 * 
 * @param array $params Search parameters
 * @return array [sql, params, total_count]
 */
function build_search_query(array $params): array {
    global $pdo;
    
    $sql = "SELECT p.id, p.name, p.price, p.image, p.stock, p.short_description, p.visible, p.category_id, c.name as category_name,
            COALESCE((
                SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id
            ), 0) AS avg_rating,
            COALESCE((
                SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id
            ), 0) AS review_count,
            COALESCE((
                SELECT SUM(oi.quantity) FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = p.id AND o.payment_status = 'paid'
            ), 0) AS sales_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.visible = 1";
    
    $conditions = [];
    $params = [];
    
    // Text search
    if (!empty($params['search'])) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
        $search_term = '%' . $params['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Category filter
    if (!empty($params['category'])) {
        if (is_array($params['category'])) {
            $placeholders = implode(',', array_fill(0, count($params['category']), '?'));
            $conditions[] = "p.category_id IN ($placeholders)";
            $params = array_merge($params, $params['category']);
        } else {
            $conditions[] = "p.category_id = ?";
            $params[] = $params['category'];
        }
    }
    
    // Price range
    if (isset($params['min_price']) && is_numeric($params['min_price'])) {
        $conditions[] = "p.price >= ?";
        $params[] = $params['min_price'];
    }
    
    if (isset($params['max_price']) && is_numeric($params['max_price'])) {
        $conditions[] = "p.price <= ?";
        $params[] = $params['max_price'];
    }
    
    // Brand filter
    if (!empty($params['brand'])) {
        if (is_array($params['brand'])) {
            $placeholders = implode(',', array_fill(0, count($params['brand']), '?'));
            $conditions[] = "p.brand IN ($placeholders)";
            $params = array_merge($params, $params['brand']);
        } else {
            $conditions[] = "p.brand = ?";
            $params[] = $params['brand'];
        }
    }
    
    // Rating filter
    if (isset($params['min_rating']) && is_numeric($params['min_rating'])) {
        $conditions[] = "(
            SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id
        ) >= ?";
        $params[] = $params['min_rating'];
    }
    
    // Stock filter
    if (isset($params['in_stock'])) {
        if ($params['in_stock']) {
            $conditions[] = "p.stock > 0";
        }
    }
    
    // Combine conditions
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // Get total count before sorting
    $count_sql = str_replace("SELECT p.*, c.name as category_name,
            COALESCE((
                SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id
            ), 0) AS avg_rating,
            COALESCE((
                SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id
            ), 0) AS review_count,
            COALESCE((
                SELECT SUM(oi.quantity) FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = p.id AND o.payment_status = 'paid'
            ), 0) AS sales_count", "SELECT COUNT(*)", $sql);
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    
    // Sorting
    $sort = $params['sort'] ?? 'newest';
    switch ($sort) {
        case 'price_asc':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'name_asc':
            $sql .= " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY p.name DESC";
            break;
        case 'rating':
            $sql .= " ORDER BY avg_rating DESC";
            break;
        case 'popular':
            $sql .= " ORDER BY sales_count DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY p.created_at DESC";
    }
    
    // Pagination
    $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
    $per_page = isset($params['per_page']) ? max(1, min(100, (int)$params['per_page'])) : 12;
    $offset = ($page - 1) * $per_page;
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    return [
        'sql' => $sql,
        'params' => $params,
        'total_count' => $total_count,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ];
}

/**
 * Get products with filters
 */
function search_products(array $params): array {
    $query = build_search_query($params);
    
    global $pdo;
    $stmt = $pdo->prepare($query['sql']);
    $stmt->execute($query['params']);
    $products = $stmt->fetchAll();
    
    return [
        'products' => $products,
        'total' => $query['total_count'],
        'page' => $query['page'],
        'total_pages' => $query['total_pages'],
        'per_page' => $query['per_page']
    ];
}

/**
 * Get available filter options based on current filters
 */
function get_filter_options(array $current_filters = []): array {
    global $pdo;
    
    // Get all categories
    $categories = fetchAll("
        SELECT c.id, c.name, COUNT(p.id) as product_count 
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.visible = 1
        GROUP BY c.id, c.name
        HAVING product_count > 0
        ORDER BY c.name
    ");
    
    // Get price range
    $price_range = fetchOne("
        SELECT MIN(price) as min_price, MAX(price) as max_price 
        FROM products 
        WHERE visible = 1
    ");
    
    // Get all brands
    $brands = fetchAll("
        SELECT DISTINCT brand, COUNT(*) as product_count 
        FROM products 
        WHERE visible = 1 AND brand IS NOT NULL AND brand != ''
        GROUP BY brand
        ORDER BY brand
    ");
    
    // Get rating distribution
    $ratings = fetchAll("
        SELECT 
            CEIL(AVG(rating)) as rating,
            COUNT(DISTINCT product_id) as product_count
        FROM product_reviews
        GROUP BY CEIL(rating)
        ORDER BY rating DESC
    ");
    
    return [
        'categories' => $categories,
        'price_range' => $price_range,
        'brands' => $brands,
        'ratings' => $ratings
    ];
}

/**
 * Get search suggestions (autocomplete)
 */
function get_search_suggestions(string $term, int $limit = 10): array {
    $term = '%' . $term . '%';
    
    // Get matching products
    $products = fetchAll("
        SELECT id, name, price, image 
        FROM products 
        WHERE visible = 1 AND name LIKE ?
        ORDER BY name
        LIMIT ?
    ", [$term, $limit]);
    
    // Get matching categories
    $categories = fetchAll("
        SELECT id, name 
        FROM categories 
        WHERE name LIKE ?
        ORDER BY name
        LIMIT ?
    ", [$term, 5]);
    
    return [
        'products' => $products,
        'categories' => $categories
    ];
}

/**
 * Get popular/searched terms (from session)
 */
function get_popular_searches(): array {
    if (!isset($_SESSION['search_history'])) {
        return [];
    }
    
    // Get unique searches and their counts
    $searches = array_count_values($_SESSION['search_history']);
    arsort($searches);
    
    return array_slice(array_keys($searches), 0, 10);
}

/**
 * Save search to history
 */
function save_search_history(string $term): void {
    if (strlen($term) >= 2) {
        if (!isset($_SESSION['search_history'])) {
            $_SESSION['search_history'] = [];
        }
        $_SESSION['search_history'][] = strtolower(trim($term));
        
        // Keep only last 50 searches
        $_SESSION['search_history'] = array_slice($_SESSION['search_history'], -50);
    }
}

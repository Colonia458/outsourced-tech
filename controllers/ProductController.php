<?php
// controllers/ProductController.php - MVC Controller for Products

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/security.php';

class ProductController {
    
    /**
     * List all products
     */
    public function index() {
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        $sql = "SELECT * FROM products WHERE visible = 1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($category) {
            $sql .= " AND category_id = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $products = fetchAll($sql, $params);
        
        // JSON API response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
    }
    
    /**
     * Get single product
     */
    public function show($id) {
        $product = fetchOne(
            "SELECT * FROM products WHERE id = ? AND visible = 1",
            [$id]
        );
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }
        
        // Get reviews
        require_once __DIR__ . '/../src/reviews.php';
        $reviews = get_product_reviews($id);
        $rating = get_product_rating($id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'product' => $product,
            'reviews' => $reviews,
            'rating' => $rating
        ]);
    }
    
    /**
     * Create new product (admin)
     */
    public function store() {
        // Verify admin
        if (!isset($_SESSION['admin_user'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        // Verify CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        
        // Validate input
        $name = sanitize($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $stock = (int)($_POST['stock'] ?? 0);
        
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Product name is required";
        }
        if ($price <= 0) {
            $errors[] = "Price must be greater than 0";
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['errors' => $errors]);
            exit;
        }
        
        // Handle image upload
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../assets/images/products/';
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $image = uniqid('product_') . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
            }
        }
        
        // Insert product
        $id = db_insert('products', [
            'name' => $name,
            'price' => $price,
            'description' => $description,
            'stock' => $stock,
            'image' => $image,
            'visible' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'product_id' => $id
        ]);
    }
    
    /**
     * Update product (admin)
     */
    public function update($id) {
        if (!isset($_SESSION['admin_user'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        
        $name = sanitize($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $stock = (int)($_POST['stock'] ?? 0);
        
        query(
            "UPDATE products SET name = ?, price = ?, description = ?, stock = ? WHERE id = ?",
            [$name, $price, $description, $stock, $id]
        );
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
    
    /**
     * Delete product (admin)
     */
    public function destroy($id) {
        if (!isset($_SESSION['admin_user'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        
        // Soft delete - just hide
        query("UPDATE products SET visible = 0 WHERE id = ?", [$id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
}

<?php
/**
 * Product Image Upload Handler
 * Used by admin/products/add.php and admin/products/edit.php
 */

session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$product_id = (int)($_POST['product_id'] ?? 0);
$is_main = isset($_POST['is_main']) ? 1 : 0;

$response = ['success' => false, 'message' => '', 'filename' => ''];

// Check if product exists
if ($product_id > 0) {
    $product = fetchOne("SELECT id, sku FROM products WHERE id = ?", [$product_id]);
    if (!$product) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit;
    }
} else {
    $response['message'] = 'Invalid product ID';
    echo json_encode($response);
    exit;
}

// Handle file upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $response['message'] = 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
        echo json_encode($response);
        exit;
    }
    
    // Get file extension
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Generate filename: use SKU if available, otherwise use product ID
    $sku = $product['sku'] ?? 'product-' . $product_id;
    $sku_for_filename = strtolower(str_replace(' ', '-', $sku));
    
    // Always add timestamp to avoid collisions
    $filename = $sku_for_filename . '-' . time() . '.' . $extension;
    
    // Upload directory
    $upload_dir = '../../../assets/images/products/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Insert into database
        $existing_main = fetchOne(
            "SELECT id FROM product_images WHERE product_id = ? AND is_main = 1",
            [$product_id]
        );
        
        // If setting as main and there's already a main, unset it
        if ($is_main && $existing_main) {
            query("UPDATE product_images SET is_main = 0 WHERE product_id = ?", [$product_id]);
        }
        
        // If not setting as main but there's no main image, set this as main
        if (!$is_main && !$existing_main) {
            $is_main = 1;
        }
        
        query(
            "INSERT INTO product_images (product_id, filename, is_main, sort_order) VALUES (?, ?, ?, 0)",
            [$product_id, $filename, $is_main]
        );
        
        $response['success'] = true;
        $response['message'] = 'Image uploaded successfully';
        $response['filename'] = $filename;
    } else {
        $response['message'] = 'Failed to upload file';
    }
} else {
    $response['message'] = 'No file uploaded or upload error';
}

echo json_encode($response);

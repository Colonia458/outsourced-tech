<?php
/**
 * Product Image Delete Handler
 * Used by admin/products/edit.php
 */

session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$image_id = (int)($_POST['image_id'] ?? 0);

$response = ['success' => false, 'message' => ''];

if ($image_id > 0) {
    // Get image info before deleting
    $image = fetchOne("SELECT * FROM product_images WHERE id = ?", [$image_id]);
    
    if ($image) {
        // Sanitize filename to prevent path traversal
        $filename = basename($image['filename']);
        
        // Delete file from disk
        $file_path = '../../../assets/images/products/' . $filename;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        query("DELETE FROM product_images WHERE id = ?", [$image_id]);
        
        // If deleted image was main, set another image as main
        if ($image['is_main']) {
            $next_image = fetchOne(
                "SELECT id FROM product_images WHERE product_id = ? LIMIT 1",
                [$image['product_id']]
            );
            if ($next_image) {
                query("UPDATE product_images SET is_main = 1 WHERE id = ?", [$next_image['id']]);
            }
        }
        
        $response['success'] = true;
        $response['message'] = 'Image deleted successfully';
    } else {
        $response['message'] = 'Image not found';
    }
} else {
    $response['message'] = 'Invalid image ID';
}

echo json_encode($response);

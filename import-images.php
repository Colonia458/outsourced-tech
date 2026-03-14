<?php
/**
 * Product Images Database Importer
 * 
 * This script imports downloaded product images into the database.
 * It matches images by SKU filename to products and creates records
 * in the product_images table.
 * 
 * Usage: php import-images.php
 * 
 * IMPORTANT: Run this from the web root directory after running download-images.php
 */

echo "=======================================\n";
echo "Product Images Database Importer\n";
echo "=======================================\n\n";

// Load configuration
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/database.php';

global $db;

// Image directory
$image_dir = __DIR__ . '/assets/images/products/';

// Get all image files from directory
$image_files = glob($image_dir . '*.*');
$image_files = array_filter($image_files, function($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
});

echo "Found " . count($image_files) . " image files\n\n";

// Map SKU to product ID
$sku_to_product = [];
$products = fetchAll("SELECT id, sku FROM products");
foreach ($products as $p) {
    $sku_to_product[strtoupper($p['sku'])] = $p['id'];
}

echo "Loaded " . count($sku_to_product) . " products from database\n\n";

$imported_count = 0;
$skipped_count = 0;
$failed_count = 0;

foreach ($image_files as $filepath) {
    $filename = basename($filepath);
    
    // Extract SKU from filename (remove extension)
    $sku = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
    // Handle timestamp suffix (e.g., LAP-DELL-001-1234567890.jpg)
    $sku = preg_replace('/-[0-9]+$/', '', $sku);
    
    echo "Processing: $filename (SKU: $sku)... ";
    
    // Find product by SKU
    if (!isset($sku_to_product[$sku])) {
        echo "SKIPPED (product not found)\n";
        $skipped_count++;
        continue;
    }
    
    $product_id = $sku_to_product[$sku];
    
    // Check if main image already exists for this product
    $existing_main = fetchOne(
        "SELECT id FROM product_images WHERE product_id = ? AND is_main = 1",
        [$product_id]
    );
    
    $is_main = $existing_main ? 0 : 1;
    
    // Check if this exact filename already exists
    $existing = fetchOne(
        "SELECT id FROM product_images WHERE product_id = ? AND filename = ?",
        [$product_id, $filename]
    );
    
    if ($existing) {
        echo "SKIPPED (already imported)\n";
        $skipped_count++;
        continue;
    }
    
    // Insert into database
    try {
        query(
            "INSERT INTO product_images (product_id, filename, is_main, sort_order) VALUES (?, ?, ?, 0)",
            [$product_id, $filename, $is_main]
        );
        echo "OK (product_id: $product_id, is_main: $is_main)\n";
        $imported_count++;
    } catch (Exception $e) {
        echo "FAILED (" . $e->getMessage() . ")\n";
        $failed_count++;
    }
}

echo "\n=======================================\n";
echo "Import Summary\n";
echo "=======================================\n";
echo "Imported: $imported_count\n";
echo "Skipped: $skipped_count\n";
echo "Failed: $failed_count\n\n";

if ($imported_count > 0) {
    echo "Images have been imported successfully!\n";
    echo "You can now view them in the admin panel at:\n";
    echo "Admin > Products > Edit Product > Product Images\n";
} else {
    echo "No images were imported. Make sure you have:\n";
    echo "1. Run download-images.php first\n";
    echo "2. Products exist in the database\n";
    echo "3. Image filenames match product SKUs\n";
}

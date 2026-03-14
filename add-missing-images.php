<?php
// Add missing product images
require_once 'src/config.php';
require_once 'src/database.php';

echo "<h1>Adding Missing Product Images</h1>";

// Map products to available images
$product_image_map = [
    // Product ID => Image filename
    4 => 'net-cisc-001.jpg',  // Cisco Catalyst 9200L 24-Port
    5 => 'net-tp-003.jpg',   // TP-Link TL-SG1048 48-Port
    22 => 'acc-mou-001.png', // Logitech MX Master 3S
    1 => 'net-tp-002.jpg',   // Test Router
];

foreach ($product_image_map as $product_id => $image) {
    // Check if image already exists for this product
    $existing = fetchOne("SELECT id FROM product_images WHERE product_id = ?", [$product_id]);
    
    if (!$existing) {
        query("INSERT INTO product_images (product_id, filename, is_main, sort_order) VALUES (?, ?, 1, 0)", [$product_id        echo "Added, $image]);
 image for product ID $product_id: $image<br>";
    } else {
        echo "Product ID $product_id already has an image<br>";
    }
}

echo "<h2>Done!</h2>";
echo "<p>Refresh your browser to see the updated images.</p>";

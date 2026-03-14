<?php
// Debug image paths
require_once 'src/config.php';
require_once 'src/database.php';

echo "<h1>Image Path Debug</h1>";

// Get a product with image
$product = fetchOne("
    SELECT p.id, p.name, 
           (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
    FROM products p 
    WHERE p.visible = 1 
    LIMIT 1
");

if (!$product) {
    die("No products with images found!");
}

echo "<h2>Product: {$product['name']}</h2>";
echo "<p>Image filename: {$product['image']}</p>";

// Test different possible paths
$paths = [
    '../../assets/images/products/' . $product['image'],
    '../assets/images/products/' . $product['image'],
    'assets/images/products/' . $product['image'],
    __DIR__ . '/assets/images/products/' . $product['image'],
];

echo "<h2>Path Tests</h2>";
foreach ($paths as $path) {
    echo "$path - " . (file_exists($path) ? "EXISTS" : "NOT FOUND") . "<br>";
}

// Show what URL would be generated
echo "<h2>Generated URLs (for public/products.php)</h2>";
$base = 'http://localhost/outsourced/';
echo "Base: $base<br>";
echo "Full URL: {$base}assets/images/products/{$product['image']}<br>";
echo "<img src='{$base}assets/images/products/{$product['image']}' style='max-width:200px; border:2px solid red;'><br>";

// Also check from public folder perspective
echo "<h2>Direct File Access Check</h2>";
$fullPath = __DIR__ . '/assets/images/products/' . $product['image'];
echo "Full server path: $fullPath<br>";
echo "Is readable: " . (is_readable($fullPath) ? "YES" : "NO") . "<br>";

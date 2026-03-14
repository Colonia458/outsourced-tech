<?php
// Test page to verify images
require_once 'src/config.php';
require_once 'src/database.php';

echo "<h1>Image Test Page</h1>";

// Test 1: Check if images directory exists
echo "<h2>1. Directory Check</h2>";
$dir = __DIR__ . '/assets/images/products/';
if (is_dir($dir)) {
    echo "✓ Directory exists: $dir<br>";
    $files = glob($dir . '*.*');
    echo "✓ Files count: " . count($files) . "<br>";
} else {
    echo "✗ Directory does NOT exist<br>";
}

// Test 2: Check database for images
echo "<h2>2. Database Check</h2>";
$images = fetchAll("SELECT pi.*, p.name as product_name FROM product_images pi JOIN products p ON pi.product_id = p.id LIMIT 5");
echo "Images in database: " . count($images) . "<br>";
foreach ($images as $img) {
    echo "- Product: " . $img['product_name'] . " -> Image: " . $img['filename'] . "<br>";
}

// Test 3: Direct image access
echo "<h2>3. Direct Image URLs</h2>";
$base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/outsourced/assets/images/products/';
echo "BASE_URL equivalent: $base<br>";

if (!empty($images)) {
    $testImg = $images[0]['filename'];
    echo "Testing: $base$testImg<br>";
    echo "<img src='$base$testImg' style='max-width:200px; border:1px solid red;'><br>";
}

// Test 4: Products with images
echo "<h2>4. Products Query Test</h2>";
$products = fetchAll("
    SELECT p.id, p.name, 
           (SELECT pi.filename FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image
    FROM products p 
    WHERE p.visible = 1 
    LIMIT 5
");

foreach ($products as $p) {
    echo "Product {$p['id']}: {$p['name']} -> " . ($p['image'] ?: 'NO IMAGE') . "<br>";
}

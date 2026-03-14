<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: list.php');
    exit();
}

// Get product
$product = fetchOne("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    header('Location: list.php');
    exit();
}

$page_title = 'Edit Product - ' . $product['name'];

// Get categories
$categories = fetchAll("SELECT * FROM categories ORDER BY name");

// Get product images
$product_images = fetchAll("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC, id ASC", [$id]);

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $compare_at_price = !empty($_POST['compare_at_price']) ? (float)$_POST['compare_at_price'] : null;
    $stock = (int)($_POST['stock'] ?? 0);
    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 5);
    $short_description = trim($_POST['short_description'] ?? '');
    $full_description = trim($_POST['full_description'] ?? '');
    $weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
    $visible = isset($_POST['visible']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^a-z0-9-]/', '-', preg_replace('/\s+/', '-', $name))));
    
    if (empty($name) || empty($sku) || $category_id === 0 || $price === 0) {
        $error = 'Please fill in all required fields';
    } else {
        // Check for duplicate SKU (excluding current product)
        $existing = fetchOne("SELECT id FROM products WHERE sku = ? AND id != ?", [$sku, $id]);
        if ($existing) {
            $error = 'Another product with this SKU already exists';
        } else {
            query(
                "UPDATE products SET category_id = ?, sku = ?, name = ?, slug = ?, short_description = ?, full_description = ?, price = ?, compare_at_price = ?, stock = ?, low_stock_threshold = ?, weight_kg = ?, visible = ?, featured = ? WHERE id = ?",
                [$category_id, $sku, $name, $slug, $short_description, $full_description, $price, $compare_at_price, $stock, $low_stock_threshold, $weight_kg, $visible, $featured, $id]
            );
            
            // Refresh product data
            $product = fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
            $error = '';
            $success = 'Product updated successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .product-image { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; }
        .image-gallery { display: flex; flex-wrap: wrap; gap: 10px; }
        .image-item { position: relative; }
        .image-item img { border: 2px solid #dee2e6; }
        .image-item .badge { position: absolute; top: 5px; left: 5px; }
        .image-delete-btn { position: absolute; top: 5px; right: 5px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="list.php" class="active"><i class="fas fa-box"></i> Products</a>
        <a href="../services/list.php"><i class="fas fa-tools"></i> Services</a>
        <a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="../users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="../service-bookings/list.php"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="../chatbot/conversations.php"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Product</h2>
            <a href="list.php" class="btn btn-secondary">Back to Products</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $product['name']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">SKU *</label>
                                <input type="text" name="sku" class="form-control" required value="<?= htmlspecialchars($_POST['sku'] ?? $product['sku']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? $product['category_id']) == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Price (KSh) *</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['price'] ?? $product['price']) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Compare Price (KSh)</label>
                                <input type="number" name="compare_at_price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['compare_at_price'] ?? $product['compare_at_price']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="stock" class="form-control" min="0" value="<?= htmlspecialchars($_POST['stock'] ?? $product['stock']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" class="form-control" min="0" value="<?= htmlspecialchars($_POST['low_stock_threshold'] ?? $product['low_stock_threshold']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" name="weight_kg" class="form-control" step="0.001" min="0" value="<?= htmlspecialchars($_POST['weight_kg'] ?? $product['weight_kg']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="2"><?= htmlspecialchars($_POST['short_description'] ?? $product['short_description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Description</label>
                        <textarea name="full_description" class="form-control" rows="5"><?= htmlspecialchars($_POST['full_description'] ?? $product['full_description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="visible" class="form-check-input" id="visible" <?= (isset($_POST['visible']) ? $_POST['visible'] : $product['visible']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="visible">Visible (show on website)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="featured" class="form-check-input" id="featured" <?= (isset($_POST['featured']) ? $_POST['featured'] : $product['featured']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">Featured product</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
                </form>
            </div>
        </div>

        <!-- Product Images Section -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-images"></i> Product Images</h5>
            </div>
            <div class="card-body">
                <!-- Image Upload Form -->
                <form id="imageUploadForm" enctype="multipart/form-data" class="mb-4">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="file" name="image" class="form-control" accept="image/*" required>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_main" class="form-check-input" id="is_main" value="1">
                                <label class="form-check-label" for="is_main">Set as main image</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-upload"></i> Upload</button>
                        </div>
                    </div>
                </form>

                <!-- Upload Status -->
                <div id="uploadStatus" class="alert d-none"></div>

                <!-- Existing Images -->
                <h6>Existing Images</h6>
                <?php if (empty($product_images)): ?>
                    <p class="text-muted">No images uploaded yet. Use the form above to add product images.</p>
                <?php else: ?>
                    <div class="image-gallery">
                        <?php foreach ($product_images as $img): ?>
                            <div class="image-item" data-image-id="<?= $img['id'] ?>">
                                <?php if ($img['is_main']): ?>
                                    <span class="badge bg-success">Main</span>
                                <?php endif; ?>
                                <img src="http://localhost/outsourced/assets/images/products/<?= htmlspecialchars($img['filename']) ?>" 
                                     class="product-image" alt="Product image">
                                <button type="button" class="btn btn-sm btn-danger image-delete-btn" 
                                        onclick="deleteImage(<?= $img['id'] ?>)" title="Delete image">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Image upload handler
    document.getElementById('imageUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var statusDiv = document.getElementById('uploadStatus');
        
        statusDiv.className = 'alert alert-info';
        statusDiv.textContent = 'Uploading...';
        statusDiv.classList.remove('d-none');
        
        fetch('upload-image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.className = 'alert alert-success';
                statusDiv.textContent = data.message;
                // Reload page to show new image
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                statusDiv.className = 'alert alert-danger';
                statusDiv.textContent = data.message;
            }
        })
        .catch(error => {
            statusDiv.className = 'alert alert-danger';
            statusDiv.textContent = 'Upload failed: ' + error;
        });
    });

    // Delete image handler
    function deleteImage(imageId) {
        if (!confirm('Are you sure you want to delete this image?')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('image_id', imageId);
        
        fetch('delete-image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove image from UI
                var imageItem = document.querySelector('[data-image-id="' + imageId + '"]');
                if (imageItem) {
                    imageItem.remove();
                }
                // Show success message
                var statusDiv = document.getElementById('uploadStatus');
                statusDiv.className = 'alert alert-success';
                statusDiv.textContent = data.message;
                statusDiv.classList.remove('d-none');
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert('Delete failed: ' + error);
        });
    }
    </script>
</body>
</html>

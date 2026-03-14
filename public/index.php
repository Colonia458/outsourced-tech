<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';

$page_title = 'Home';

// Get featured products from database
$featured_products = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
        FROM products p 
        WHERE p.featured = 1 AND p.visible = 1 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching featured products: " . $e->getMessage());
}

// Get categories
$categories = [];
try {
    $stmt = $db->query("SELECT * FROM categories ORDER BY display_order ASC LIMIT 6");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get services
$services = [];
try {
    $stmt = $db->query("SELECT * FROM services WHERE visible = 1 ORDER BY id ASC LIMIT 4");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Hero Section - Split Layout -->
<section class="bg-light py-5">
    <div class="container">
        <div class="row align-items-center">
            <!-- Left Side - Content -->
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">
                    Premium Tech Products & <span class="text-primary">Expert Services</span>
                </h1>
                <p class="lead text-muted mb-4">
                    Your one-stop shop for laptops, networking equipment, phones, and professional repair services in Mlolongo.
                </p>
                
                <!-- Dual CTAs -->
                <div class="d-flex gap-3 flex-wrap">
                    <a href="products.php" class="btn btn-primary btn-lg px-4 py-3">
                        <i class="fas fa-shopping-bag me-2"></i>Shop Best Sellers
                    </a>
                    <a href="services.php" class="btn btn-outline-primary btn-lg px-4 py-3">
                        <i class="fas fa-calendar-check me-2"></i>Book a Service
                    </a>
                </div>
                
                <!-- Trust Badges -->
                <div class="d-flex gap-4 mt-4 flex-wrap">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shipping-fast text-primary fa-lg me-2"></i>
                        <span>Free Delivery</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt text-success fa-lg me-2"></i>
                        <span>Warranty</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-headset text-info fa-lg me-2"></i>
                        <span>24/7 Support</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Image/Graphic -->
            <div class="col-lg-6 text-center mt-4 mt-lg-0">
                <div class="position-relative">
                    <div class="bg-primary bg-opacity-10 rounded-4 d-flex align-items-center justify-content-center" style="height: 350px;">
                        <div class="text-center">
                            <i class="fas fa-microchip fa-5x text-primary mb-3"></i>
                            <h5 class="text-muted">Tech Products & Services</h5>
                        </div>
                    </div>
                    <!-- Floating Cards -->
                    <div class="position-absolute bg-white p-3 rounded-3 shadow-lg" style="bottom: -20px; left: -20px;">
                        <div class="d-flex align-items-center">
                            <div class="bg-success rounded-circle p-2 me-2">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <div>
                                <strong>500+</strong>
                                <small class="text-muted d-block">Happy Customers</small>
                            </div>
                        </div>
                    </div>
                    <div class="position-absolute bg-white p-3 rounded-3 shadow-lg" style="top: 20px; right: -20px;">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary rounded-circle p-2 me-2">
                                <i class="fas fa-star text-white"></i>
                            </div>
                            <div>
                                <strong>4.9/5</strong>
                                <small class="text-muted d-block">Rating</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Service Spotlight Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Our Services</h2>
            <p class="text-muted">Professional tech services you can trust</p>
        </div>
        
        <div class="row g-4">
            <!-- Service Card 1 -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm hover-card">
                    <div class="card-body text-center p-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-laptop-medical fa-2x text-primary"></i>
                        </div>
                        <h5 class="card-title">Laptop Repairs</h5>
                        <p class="card-text text-muted small">Screen replacement, keyboard fixes, battery issues, and hardware diagnostics.</p>
                        
                        <!-- Expandable Details -->
                        <div class="service-details text-start" id="details-laptop" style="display: none;">
                            <hr>
                            <h6 class="fw-bold">What's Included:</h6>
                            <ul class="small text-muted mb-2" style="padding-left: 20px;">
                                <li>Screen replacement (LCD/LED/OLED)</li>
                                <li>Keyboard repair & cleaning</li>
                                <li>Battery replacement</li>
                                <li>Hard drive/SSD upgrade</li>
                                <li>RAM upgrade & installation</li>
                                <li>Motherboard diagnostics</li>
                                <li>Fan cleaning & replacement</li>
                                <li>Water damage recovery</li>
                            </ul>
                            <p class="small mb-1"><strong>Turnaround:</strong> 24-48 hours</p>
                            <p class="small mb-2"><strong>Warranty:</strong> 30 days on parts</p>
                            <p class="small mb-0"><strong>Starting at:</strong> KSh 500</p>
                        </div>
                        
                        <button class="btn btn-outline-primary btn-sm toggle-details" data-target="details-laptop">
                            Learn More <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Service Card 2 -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm hover-card">
                    <div class="card-body text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-mobile-alt fa-2x text-success"></i>
                        </div>
                        <h5 class="card-title">Phone Repairs</h5>
                        <p class="card-text text-muted small">Screen fixes, battery replacement, water damage recovery, and software issues.</p>
                        
                        <!-- Expandable Details -->
                        <div class="service-details text-start" id="details-phone" style="display: none;">
                            <hr>
                            <h6 class="fw-bold">What's Included:</h6>
                            <ul class="small text-muted mb-2" style="padding-left: 20px;">
                                <li>Screen replacement (all brands)</li>
                                <li>Battery replacement</li>
                                <li>Charging port repair</li>
                                <li>Camera repair & replacement</li>
                                <li>Speaker & microphone repair</li>
                                <li>Water damage treatment</li>
                                <li>Software troubleshooting</li>
                                <li>Unlocking & FRP bypass</li>
                            </ul>
                            <p class="small mb-1"><strong>Turnaround:</strong> 1-24 hours</p>
                            <p class="small mb-2"><strong>Warranty:</strong> 30 days on parts</p>
                            <p class="small mb-0"><strong>Starting at:</strong> KSh 300</p>
                        </div>
                        
                        <button class="btn btn-outline-success btn-sm toggle-details" data-target="details-phone">
                            Learn More <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Service Card 3 -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm hover-card">
                    <div class="card-body text-center p-4">
                        <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-wifi fa-2x text-info"></i>
                        </div>
                        <h5 class="card-title">ISP Services</h5>
                        <p class="card-text text-muted small">WiFi installation, router configuration, network setup, and troubleshooting.</p>
                        
                        <!-- Expandable Details -->
                        <div class="service-details text-start" id="details-isp" style="display: none;">
                            <hr>
                            <h6 class="fw-bold">What's Included:</h6>
                            <ul class="small text-muted mb-2" style="padding-left: 20px;">
                                <li>Home WiFi installation</li>
                                <li>Router configuration</li>
                                <li>Network setup & cabling</li>
                                <li>Mesh WiFi systems</li>
                                <li>IP camera setup</li>
                                <li>Network troubleshooting</li>
                                <li>Signal boost installation</li>
                                <li>VPN & security setup</li>
                            </ul>
                            <p class="small mb-1"><strong>Turnaround:</strong> Same day</p>
                            <p class="small mb-2"><strong>Warranty:</strong> 90 days on installation</p>
                            <p class="small mb-0"><strong>Starting at:</strong> KSh 1,500</p>
                        </div>
                        
                        <button class="btn btn-outline-info btn-sm toggle-details" data-target="details-isp">
                            Learn More <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Service Card 4 -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm hover-card">
                    <div class="card-body text-center p-4">
                        <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-virus-slash fa-2x text-warning"></i>
                        </div>
                        <h5 class="card-title">Software Services</h5>
                        <p class="card-text text-muted small">Virus removal, OS installation, driver updates, and system optimization.</p>
                        
                        <!-- Expandable Details -->
                        <div class="service-details text-start" id="details-software" style="display: none;">
                            <hr>
                            <h6 class="fw-bold">What's Included:</h6>
                            <ul class="small text-muted mb-2" style="padding-left: 20px;">
                                <li>Virus & malware removal</li>
                                <li>Windows installation</li>
                                <li>macOS installation</li>
                                <li>Linux installation</li>
                                <li>Driver updates</li>
                                <li>System optimization</li>
                                <li>Data backup & recovery</li>
                                <li>Software installation</li>
                            </ul>
                            <p class="small mb-1"><strong>Turnaround:</strong> 2-4 hours</p>
                            <p class="small mb-2"><strong>Warranty:</strong> 7 days service guarantee</p>
                            <p class="small mb-0"><strong>Starting at:</strong> KSh 800</p>
                        </div>
                        
                        <button class="btn btn-outline-warning btn-sm toggle-details" data-target="details-software">
                            Learn More <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="services.php" class="btn btn-primary">
                View All Services <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Categories Section -->
<?php if (!empty($categories)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Shop by Category</h2>
            <p class="text-muted">Browse our wide range of products</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($categories as $index => $category): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="products.php?category=<?= urlencode($category['slug']) ?>" 
                   class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm text-center category-card">
                        <div class="card-body py-4">
                            <?php
                            $icons = ['fa-laptop', 'fa-mobile-alt', 'fa-network-wired', 'fa-headphones', 'fa-hdd', 'fa-print'];
                            $icon = $icons[$index % count($icons)] ?? 'fa-box';
                            ?>
                            <i class="fas <?= $icon ?> fa-2x text-primary mb-3 d-block"></i>
                            <h6 class="mb-0 text-dark"><?= htmlspecialchars($category['name']) ?></h6>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1">Featured Products</h2>
                <p class="text-muted mb-0">Handpicked bestsellers just for you</p>
            </div>
            <a href="products.php" class="btn btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        
        <?php if (!empty($featured_products)): ?>
        <div class="row g-4">
            <?php foreach ($featured_products as $index => $product): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card h-100 border-0 shadow-sm product-card">
                    <?php if (!empty($product['image'])): ?>
                    <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($product['image']) ?>" 
                         class="card-img-top" 
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         style="height: 150px; object-fit: cover;">
                    <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                        <i class="fas fa-image text-muted fa-3x"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h6 class="card-title mb-1" style="font-size: 14px; line-height: 1.3;">
                            <?= htmlspecialchars($product['name']) ?>
                        </h6>
                        <p class="text-muted small mb-2" style="font-size: 12px;">
                            <?= htmlspecialchars($product['short_description'] ?? '') ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-primary">KSh <?= number_format($product['price'], 0) ?></span>
                            <?php if ($product['stock'] > 0): ?>
                                <span class="badge bg-success" style="font-size: 10px;">In Stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger" style="font-size: 10px;">Out</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Expandable Product Details -->
                        <div class="product-details text-start mt-2" id="product-details-<?= $index ?>" style="display: none;">
                            <hr class="my-2">
                            <?php if (!empty($product['description'])): ?>
                                <p class="small text-muted mb-1" style="font-size: 11px;">
                                    <strong>Description:</strong> <?= htmlspecialchars(substr($product['description'], 0, 150)) ?><?php if (strlen($product['description']) > 150) echo '...'; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($product['specifications'])): ?>
                                <p class="small text-muted mb-1" style="font-size: 11px;">
                                    <strong>Specs:</strong> <?= htmlspecialchars(substr($product['specifications'], 0, 100)) ?><?php if (strlen($product['specifications']) > 100) echo '...'; ?>
                                </p>
                            <?php endif; ?>
                            <div class="d-flex gap-1 mt-2">
                                <?php if ($product['stock'] > 0): ?>
                                    <button class="btn btn-sm btn-success flex-grow-1" onclick="addToCart(<?= $product['id'] ?>)">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                <?php endif; ?>
                                <a href="product.php?slug=<?= htmlspecialchars($product['slug']) ?>" class="btn btn-sm btn-outline-secondary">
                                    Details
                                </a>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-2">
                            <button class="btn btn-sm btn-outline-primary toggle-product-details" data-target="product-details-<?= $index ?>">
                                Learn More <i class="fas fa-chevron-down ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
            <p class="text-muted">No featured products available at the moment.</p>
            <a href="products.php" class="btn btn-primary">Browse All Products</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Trust Signals / Why Shop With Us -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Why Shop With Us</h2>
            <p class="text-muted">Experience the difference of shopping with experts</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center">
                    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="fas fa-shipping-fast fa-2x text-primary"></i>
                    </div>
                    <h5>Fast Delivery</h5>
                    <p class="text-muted small mb-0">Same-day delivery in Mlolongo area. Cash on delivery available.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="text-center">
                    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="fas fa-shield-alt fa-2x text-success"></i>
                    </div>
                    <h5>Quality Warranty</h5>
                    <p class="text-muted small mb-0">All products come with manufacturer warranty. 30-day returns.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="text-center">
                    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="fas fa-headset fa-2x text-info"></i>
                    </div>
                    <h5>Expert Support</h5>
                    <p class="text-muted small mb-0">Need help? Our team is just a call or message away.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5">
    <div class="container">
        <div class="card bg-primary text-white border-0">
            <div class="card-body text-center py-5">
                <h2 class="fw-bold mb-3">Ready to Get Started?</h2>
                <p class="mb-4" style="opacity: 0.9;">Join hundreds of satisfied customers. Register now to earn loyalty points and get exclusive deals!</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="register.php" class="btn btn-light btn-lg">Create Account</a>
                    <a href="products.php" class="btn btn-outline-light btn-lg">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.hover-card {
    transition: transform 0.3s, box-shadow 0.3s;
}
.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}
.category-card {
    transition: transform 0.3s, box-shadow 0.3s;
}
.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}
.category-card:hover i {
    transform: scale(1.1);
}
.product-card {
    transition: transform 0.3s;
}
.product-card:hover {
    transform: translateY(-3px);
}
.service-details {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.toggle-details {
    transition: all 0.3s ease;
}
.toggle-details:hover {
    transform: scale(1.02);
}
.toggle-details i {
    transition: transform 0.3s ease;
}
.toggle-details.active i {
    transform: rotate(180deg);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle service details functionality
    const toggleButtons = document.querySelectorAll('.toggle-details');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const detailsSection = document.getElementById(targetId);
            const isHidden = detailsSection.style.display === 'none';
            
            // Toggle the details visibility
            detailsSection.style.display = isHidden ? 'block' : 'none';
            
            // Update button text and icon
            if (isHidden) {
                this.innerHTML = 'Show Less <i class="fas fa-chevron-up ms-1"></i>';
                this.classList.add('active');
            } else {
                this.innerHTML = 'Learn More <i class="fas fa-chevron-down ms-1"></i>';
                this.classList.remove('active');
            }
        });
    });
    
    // Toggle product details functionality
    const toggleProductButtons = document.querySelectorAll('.toggle-product-details');
    
    toggleProductButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const detailsSection = document.getElementById(targetId);
            const isHidden = detailsSection.style.display === 'none';
            
            // Toggle the details visibility
            detailsSection.style.display = isHidden ? 'block' : 'none';
            
            // Update button text and icon
            if (isHidden) {
                this.innerHTML = 'Show Less <i class="fas fa-chevron-up ms-1"></i>';
                this.classList.add('active');
            } else {
                this.innerHTML = 'Learn More <i class="fas fa-chevron-down ms-1"></i>';
                this.classList.remove('active');
            }
        });
    });
});

// Add to cart function
function addToCart(productId) {
    fetch('<?= BASE_URL ?>api/v1/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=add&product_id=' + productId + '&quantity=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added to cart!');
            // Update cart count if available
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge && data.cart_count !== undefined) {
                cartBadge.textContent = data.cart_count;
            }
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

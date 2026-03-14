# Final Year Project - Complete Implementation Roadmap

## Project: Outsourced Technologies E-Commerce Platform

---

## Phase 1: Documentation (Week 1-2)

### 1.1 Technical Specification Document
Create `docs/technical-specification.md`:
- **1. Introduction**
  - Project background
  - Objectives
  - Scope
  
- **2. System Requirements**
  - Functional requirements
  - Non-functional requirements
  - User characteristics. System Architecture**

  
- **3  - Architecture diagram (MVC pattern)
  - Technology stack
  - Database design overview
  
- **4. Module Design**
  - Authentication module
  - Product catalog module
  - Cart & checkout module
  - Payment module
  - Admin module
  - Loyalty module

### 1.2 Database ERD
Create `docs/database-erd.md` with visual diagram showing all tables and relationships:
- users
- products
- categories
- orders
- order_items
- services
- service_bookings
- payments
- delivery_zones
- loyalty_tiers
- reviews
- coupons
- wishlists (new)
- activity_logs

### 1.3 Use Case Diagrams
Create diagrams for:
- Customer use cases (browse, cart, checkout, track)
- Admin use cases (manage products, orders, users)
- System use cases (payments, notifications)

### 1.4 UML Diagrams
- **Class Diagram**: Product, User, Order, Payment classes
- **Sequence Diagrams**: Login flow, Checkout flow, Payment flow
- **Activity Diagrams**: Order processing, User registration

---

## Phase 2: Database Enhancements (Week 2-3)

### 2.1 Add New Tables
```sql
-- wishlists table
CREATE TABLE IF NOT EXISTS wishlists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- recently_viewed table
CREATE TABLE IF NOT EXISTS recently_viewed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- newsletter_subscribers table
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- admin_activity_logs table
CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
);
```

---

## Phase 3: Feature Implementation (Week 3-5)

### 3.1 Wishlist Feature
**Files to create/modify:**
- `api/v1/wishlist.php` - API endpoints
- `public/wishlist.php` - Customer wishlist page
- `src/wishlist.php` - Backend functions
- `templates/wishlist-button.php` - Add to wishlist button

**Implementation:**
```php
// src/wishlist.php
function add_to_wishlist($user_id, $product_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO wishlists (user_id, product_id) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE created_at = NOW()
    ");
    return $stmt->execute([$user_id, $product_id]);
}

function get_wishlist($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT w.*, p.name, p.price, p.image, p.stock
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
```

### 3.2 Advanced Search & Filters
**Files to modify:**
- `public/products.php` - Add filter sidebar
- `api/v1/search.php` - Add filter logic
- `api/v1/products.php` - Add query parameters

**Filter options:**
- Category filter (multi-select)
- Price range slider
- Brand filter
- Rating filter (stars)
- Sort by: price (asc/desc), newest, popularity

### 3.3 Product Comparison
**Files to create:**
- `public/compare.php` - Comparison page
- `assets/js/compare.js` - JavaScript logic
- `api/v1/compare.php` - API endpoint

### 3.4 PDF Invoice Generation
**Files to create:**
- `src/invoice_pdf.php` - PDF generation
- `public/invoice.php` - Download page

**Installation:**
```bash
composer require tecnickcom/tcpdf
```

### 3.5 Inventory Alerts
**Files to modify:**
- `api/cron.php` - Add low stock check
- `admin/products/list.php` - Add stock indicators

**SQL Enhancement:**
```sql
ALTER TABLE products ADD COLUMN reorder_level INT DEFAULT 10;
```

---

## Phase 4: API & Security (Week 5-6)

### 4.1 Swagger/OpenAPI Documentation
**Files to create:**
- `api/swagger.php` - Swagger UI
- Annotations in each API file

**Installation:**
```bash
composer require swagger-api/swagger-ui
```

**Example annotation:**
```php
/**
 * @OA\Get(
 *     path="/api/v1/products.php",
 *     summary="Get all products",
 *     @OA\Parameter(
 *         name="category",
 *         in="query",
 *         description="Filter by category ID"
 *     ),
 *     @OA\Response(response="200", description="Success")
 * )
 */
```

### 4.2 JWT Authentication
**Files to create/modify:**
- `src/jwt.php` - JWT helper class
- `api/v1/auth.php` - Add JWT endpoints

**Installation:**
```bash
composer require firebase/php-jwt
```

**Implementation:**
```php
// src/jwt.php
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generate_jwt($user_id, $email) {
    $payload = [
        'user_id' => $user_id,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ];
    return JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
}

function verify_jwt($token) {
    try {
        return JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}
```

### 4.3 Two-Factor Authentication (2FA)
**Files to create:**
- `src/twofactor.php` - 2FA functions
- `public/verify-2fa.php` - Verification page
- Modify `public/login.php` - Add 2FA step

**Installation:**
```bash
composer require sonata-project/google-authenticator
```

### 4.4 Enhanced Admin Logging
**Files to modify:**
- Add logging to all admin CRUD operations

---

## Phase 5: Testing (Week 6-7)

### 5.1 Test Cases Document
Create `docs/test-cases.md`:
- Unit test cases
- Integration test cases
- User acceptance test cases
- Test results

### 5.2 Unit Tests
**Files to create:**
- `tests/ProductTest.php`
- `tests/UserTest.php`
- `tests/OrderTest.php`

**Installation:**
```bash
composer require phpunit/phpunit --dev
```

---

## Phase 6: Final Polish (Week 7-8)

### 6.1 User Manuals
- `docs/user-manual.md` - For customers
- `docs/admin-manual.md` - For administrators

### 6.2 Final Review
- Check all functionality
- Fix bugs
- Optimize performance
- Final documentation

---

## File Structure After Implementation

```
outsourced/
├── docs/
│   ├── technical-specification.md
│   ├── database-erd.md
│   ├── use-cases.md
│   ├── uml-diagrams.md
│   ├── test-cases.md
│   ├── user-manual.md
│   └── admin-manual.md
├── diagrams/
│   ├── architecture.png
│   ├── erd.png
│   ├── use-cases.png
│   ├── sequence-checkout.png
│   └── deployment.png
├── tests/
│   ├── ProductTest.php
│   ├── UserTest.php
│   └── OrderTest.php
├── src/
│   ├── jwt.php (NEW)
│   ├── twofactor.php (NEW)
│   ├── wishlist.php (NEW)
│   ├── invoice_pdf.php (NEW)
│   └── ... (existing)
├── api/v1/
│   ├── wishlist.php (NEW)
│   ├── compare.php (NEW)
│   └── ... (existing)
├── public/
│   ├── wishlist.php (NEW)
│   ├── compare.php (NEW)
│   ├── invoice.php (NEW)
│   └── ... (existing)
└── vendor/ (composer dependencies)
```

---

## Implementation Checklist

### Week 1-2: Documentation
- [ ] Technical specification document
- [ ] Database ERD diagram
- [ ] Use case diagrams
- [ ] UML diagrams

### Week 2-3: Database
- [ ] Add wishlists table
- [ ] Add recently_viewed table
- [ ] Add newsletter table
- [ ] Add admin_activity_logs table
- [ ] Add reorder_level column to products

### Week 3-4: Core Features
- [ ] Implement wishlist functionality
- [ ] Add advanced search & filters
- [ ] Create product comparison
- [ ] Add PDF invoice generation

### Week 5-6: API & Security
- [ ] Add Swagger documentation
- [ ] Implement JWT authentication
- [ ] Add 2FA support
- [ ] Enhance admin logging

### Week 7-8: Testing & Polish
- [ ] Create test cases document
- [ ] Write unit tests
- [ ] Create user manual
- [ ] Create admin manual
- [ ] Final testing and bug fixes

# Technical Documentation
## Outsourced Technologies E-Commerce Platform
### Final Year Project

---

## 1. System Overview

### 1.1 Project Description
**Project Name:** Outsourced Technologies E-Commerce Platform  
**Project Type:** Full-Stack Web Application  
**Domain:** Electronics & IT Services E-Commerce  

### 1.2 Problem Statement
This project addresses the need for a local electronics and IT services provider in Mlolongo, Kenya to:
- Sell networking equipment (hubs, switches, routers)
- Offer computer and phone sales
- Provide repair and diagnostic services
- Enable online payments via M-Pesa
- Build customer loyalty through a rewards program

### 1.3 Objectives
1. Provide an intuitive online shopping experience for tech products
2. Enable online service booking for repairs and diagnostics
3. Integrate M-Pesa for mobile money payments
4. Build customer loyalty through a tiered rewards system
5. Provide comprehensive admin management tools

---

## 2. System Architecture

### 2.1 Technology Stack

| Layer | Technology |
|-------|------------|
| **Frontend** | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5 |
| **Backend** | PHP 7.4+ |
| **Database** | MySQL / PostgreSQL (Supabase) |
| **Payment** | M-Pesa Daraja API |
| **Server** | Apache/Nginx |
| **Authentication** | Session-based + JWT |

### 2.2 Architecture Pattern
The system follows the **MVC (Model-View-Controller)** pattern:

```
┌─────────────────────────────────────────────────────────┐
│                    VIEW (PHP/HTML)                      │
│  public/*.php, admin/*.php, templates/*.php            │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────┐
│                  CONTROLLER                            │
│  controllers/*.php, api/*.php                          │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────┐
│                    MODEL (src/)                        │
│  config.php, database.php, auth.php, cart.php         │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────┐
│                  DATABASE                              │
│  MySQL / PostgreSQL                                   │
└─────────────────────────────────────────────────────────┘
```

### 2.3 Directory Structure
```
outsourced/
├── admin/                 # Admin panel pages
│   ├── dashboard.php
│   ├── products/
│   ├── orders/
│   ├── users/
│   └── ...
├── api/                   # API endpoints
│   ├── v1/               # API version 1
│   ├── mpesa/           # M-Pesa integration
│   └── ...
├── assets/                # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── controllers/           # MVC Controllers
├── database/             # SQL scripts
├── docs/                 # Documentation
├── logs/                 # System logs
├── plans/                # Project plans
├── public/               # Customer-facing pages
├── src/                  # Core application logic
│   ├── config.php
│   ├── auth.php
│   ├── cart.php
│   ├── wishlist.php     # NEW: Wishlist feature
│   ├── compare.php      # NEW: Product comparison
│   ├── search.php       # NEW: Advanced search
│   └── ...
└── templates/            # Reusable templates
```

---

## 3. Database Design

### 3.1 Entity Relationship Diagram

```
┌──────────────┐       ┌──────────────┐
│    users     │       │  categories  │
├──────────────┤       ├──────────────┤
│ id (PK)     │◄─────►│ id (PK)     │
│ email       │       │ name        │
│ username    │       │ description │
│ password    │       │ parent_id   │
│ full_name   │       └──────────────┘
│ phone       │             │
│ loyalty_pts │             ▼
└──────────────┘       ┌──────────────┐
       │               │  products    │
       │               ├──────────────┤
       │               │ id (PK)     │
       ▼               │ category_id │
┌──────────────┐       │ name        │
│    orders    │       │ price       │
├──────────────┤       │ stock       │
│ id (PK)     │◄──────│ image       │
│ user_id (FK)│       │ description │
│ order_num   │       │ visible     │
│ total       │       └──────────────┘
│ status      │             │
│ payment     │             ▼
└──────────────┘       ┌──────────────┐
       │               │ order_items  │
       │               ├──────────────┤
       ▼               │ id (PK)     │
┌──────────────┐       │ order_id (FK│
│  payments    │◄─────►│ product_id  │
├──────────────┤       │ quantity    │
│ id (PK)     │       │ price       │
│ order_id    │       └──────────────┘
│ amount      │
│ status      │
│ trans_id    │
└──────────────┘
```

### 3.2 Core Tables

#### Users Table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    loyalty_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Products Table
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    short_description VARCHAR(500),
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    image VARCHAR(255),
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

#### Orders Table
```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','processing','ready_for_delivery','delivered','cancelled') DEFAULT 'pending',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    delivery_type ENUM('pickup','home_delivery') DEFAULT 'pickup',
    delivery_address TEXT,
    transaction_id VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 4. Feature Documentation

### 4.1 User Authentication
- Registration with email, username, password
- Login with email/password
- Password reset via email
- Session-based authentication

### 4.2 Product Catalog
- Category-based product organization
- Product search and filtering
- Product detail pages with reviews
- Stock management

### 4.3 Shopping Cart
- Add/remove products
- Update quantities
- Apply coupons
- Calculate delivery fees

### 4.4 Checkout & Payments
- Guest checkout option
- Delivery type selection (pickup/delivery)
- M-Pesa STK Push integration
- Order confirmation

### 4.5 Loyalty Program
| Tier | Points Required | Discount |
|------|-----------------|----------|
| Bronze | 0 | 0% |
| Silver | 500 | 5% |
| Gold | 1,500 | 10% |
| Platinum | 3,000 | 15% |
| Diamond | 5,000 | 20% |

### 4.6 Order Tracking
- Real-time order status updates
- Live map tracking (driver location)
- Delivery status notifications

### 4.7 Admin Panel
- Dashboard with sales analytics
- Product/Service management
- Order management
- User management
- System monitoring

---

## 5. API Documentation

### 5.1 Authentication
```
POST /api/v1/auth.php
Body: { "email": "user@example.com", "password": "password" }
Response: { "access_token": "...", "refresh_token": "...", "user": {...} }
```

### 5.2 Products
```
GET /api/v1/products.php
Query: ?category=1&search=laptop&page=1&per_page=12

GET /api/v1/products.php?id=1
Response: { "id": 1, "name": "...", "price": ..., ... }
```

### 5.3 Cart
```
GET /api/v1/cart.php
Headers: Authorization: Bearer <token>

POST /api/v1/cart.php
Body: { "product_id": 1, "quantity": 2 }
```

### 5.4 Orders
```
POST /api/v1/orders.php
Body: { "delivery_type": "home_delivery", "phone": "07...", "address": "..." }

GET /api/v1/orders.php
Headers: Authorization: Bearer <token>
```

### 5.5 Search (New)
```
GET /api/v1/search.php?action=search&search=laptop&min_price=1000&max_price=50000&category=1&sort=price_asc
```

### 5.6 Wishlist (New)
```
GET /api/v1/wishlist.php
Headers: Authorization: Bearer <token>

POST /api/v1/wishlist.php
Body: { "product_id": 1 }

DELETE /api/v1/wishlist.php?product_id=1
```

### 5.7 Compare (New)
```
GET /api/v1/compare.php?action=list
POST /api/v1/compare.php?action=add
Body: { "product_id": 1 }
```

---

## 6. Security Features

### 6.1 Implemented Security Measures
- **Password Hashing**: bcrypt via `password_hash()`
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Input sanitization with `htmlspecialchars()`
- **CSRF Protection**: Token-based form validation
- **Session Security**: Secure session configuration
- **Rate Limiting**: API request throttling
- **Security Headers**: X-Content-Type-Options, X-Frame-Options

### 6.2 Recommendations for Production
- Enable HTTPS/SSL
- Implement 2FA
- Add IP-based access control
- Regular security audits
- Database backup automation

---

## 7. Testing

### 7.1 Test Cases Summary
| Feature | Test Cases | Status |
|---------|------------|--------|
| User Registration | 5 | ✅ |
| User Login | 4 | ✅ |
| Product Browsing | 8 | ✅ |
| Cart Operations | 6 | ✅ |
| Checkout | 7 | ✅ |
| Payment | 5 | ✅ |
| Order Tracking | 4 | ✅ |
| Admin Functions | 10 | ✅ |

### 7.2 Test Environment
- **PHP Version**: 7.4+
- **Database**: MySQL 8.0
- **Browser Testing**: Chrome, Firefox, Safari, Edge
- **Responsive**: Mobile, Tablet, Desktop

---

## 8. Deployment

### 8.1 Requirements
- PHP 7.4 or higher
- MySQL 5.7+ or PostgreSQL
- Composer (for dependencies)
- M-Pesa Daraja API credentials

### 8.2 Installation Steps
1. Clone repository
2. Configure database in `src/config.php`
3. Run SQL setup scripts
4. Configure M-Pesa credentials
5. Set up cron jobs
6. Configure web server

### 8.3 Cron Jobs
```bash
# Maintenance (every 5 minutes)
*/5 * * * * curl -s https://yoursite.com/api/cron.php?key=YOUR_KEY

# Daily backup (midnight)
0 0 * * * curl -s https://yoursite.com/api/backup.php?key=YOUR_KEY
```

---

## 9. Conclusion

This e-commerce platform provides a comprehensive solution for selling electronics and IT services in Kenya. Key achievements include:

✅ Full product catalog with categories  
✅ M-Pesa payment integration  
✅ Service booking system  
✅ Loyalty rewards program  
✅ Real-time order tracking  
✅ Admin management panel  
✅ RESTful API  
✅ Advanced search & filters (NEW)  
✅ Wishlist functionality (NEW)  
✅ Product comparison (NEW)  
✅ PDF invoice generation (NEW)  
✅ JWT authentication (NEW)  
✅ Swagger documentation (NEW)  

---

## 10. References

- [M-Pesa Daraja API Documentation](https://developer.safaricom.co.ke/)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/)
- [PHP Documentation](https://www.php.net/docs.php)
- [Supabase Documentation](https://supabase.com/docs)

---

*Document Version: 1.0*  
*Last Updated: <?php echo date('F j, Y'); ?>*

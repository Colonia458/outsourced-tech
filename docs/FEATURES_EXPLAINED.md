# How the New Features Work

## 1. SMS Notification System

### Overview:
SMS notifications send text messages to customers when important events happen (order confirmed, shipped, delivered).

### How It Works:

```
User places order
       ↓
Order stored in database
       ↓
Backend calls send_order_confirmation_sms()
       ↓
Format phone number (+254...)
       ↓
Send to Africa's Talking API
       ↓
SMS delivered to phone
```

### Key Components:

**[`src/sms.php`](src/sms.php)**
- `send_sms($phone, $message, $eventType)` - Main function to send SMS
- `format_phone_number($phone)` - Converts 0712345678 → +254712345678
- `send_order_confirmation_sms()` - Sends order confirmation
- `send_order_shipped_sms()` - Sends shipping notification
- `send_payment_received_sms()` - Sends payment confirmation

**Templates in Database:**
- Order Confirmed: "Thank you for your order! Your order #123 has been confirmed..."
- Order Shipped: "Your order #123 has been shipped! Delivery expected within 3 days..."
- Order Delivered: "Your order #123 has been delivered! Leave a review..."

### Usage Example:
```php
// When order is confirmed
send_order_confirmation_sms($orderId, $customerPhone, $total);

// When order is shipped
send_order_shipped_sms($orderId, $customerPhone, 3);
```

---

## 2. Push Notifications System

### Overview:
Push notifications appear in the browser even when the user isn't on your site. They work like app notifications but in a web browser.

### How It Works:

```
User visits website
       ↓
Browser asks "Allow notifications?"
       ↓
If yes, save subscription (endpoint URL)
       ↓
Store in database
       ↓
When event happens (order shipped)
       ↓
Server sends to browser endpoint
       ↓
Service worker shows notification
```

### Key Components:

**Service Worker ([`sw.js`](sw.js))**
- Runs in background
- Receives push events from server
- Shows notification to user
- Handles click actions

**Frontend ([`assets/js/push-notifications.js`](assets/js/push-notifications.js))**
- Asks permission for notifications
- Subscribes to push service
- Saves subscription to server
- Can send test notifications

### Setup Process:
1. User visits site → popup "Enable notifications?"
2. User clicks "Allow"
3. Browser generates unique subscription
4. Subscription saved to database
5. Later: server sends notification → browser displays it

---

## 3. Product Recommendations System

### Overview:
This uses **collaborative filtering** - analyzing what similar users bought to recommend products.

### How It Works:

```
User views product (laptop)
       ↓
Log interaction: "view" + "laptop"
       ↓
Other users who viewed "laptop" also viewed "mouse", "keyboard"
       ↓
Recommend "mouse", "keyboard" to this user
```

### Types of Recommendations:

**1. Personalized** (for logged-in users):
- Based on their purchase history
- Products from categories they frequently buy

**2. Similar Products** (on product page):
- Same category
- Frequently bought together

**3. Frequently Bought Together**:
- Found in same orders
- "People who bought X also bought Y"

**4. Popular Products**:
- Most viewed/purchased overall
- Good for new visitors

### Key Components:

**[`src/recommendations.php`](src/recommendations.php)**
- `log_product_interaction()` - Records views, purchases, etc.
- `get_personalized_recommendations()` - For logged-in users
- `get_similar_products()` - On product page
- `get_frequently_bought_together()` - "Customers also bought"
- `get_popular_products()` - Trending items

**Tracking Events:**
- `view` - User viewed product
- `add_to_cart` - Added to cart
- `purchase` - Bought product
- `wishlist` - Added to wishlist
- `compare` - Added to compare
- `review` - Reviewed product

### Example:
```javascript
// Track a product view
productRecommendations.trackProductView(123, 49999);

// Get recommendations
const recs = await productRecommendations.getRecommendations('personalized', 10);
```

---

## How to Test:

### 1. First, Run SQL:
Go to phpMyAdmin → outsourced_tech → SQL tab → run:
```sql
-- Run database/notifications.sql
```

### 2. Test SMS (requires Africa's Talking account):
- Sign up at https://africastalking.com
- Get API key
- Add to .env:
  ```
  AFRICASTALKING_API_KEY=your_key
  AFRICASTALKING_USERNAME=sandbox
  ```

### 3. Test Push Notifications:
- Open site in Chrome
- Open DevTools (F12)
- In console, type:
  ```javascript
  pushNotifications.subscribe()
  ```

### 4. Test Recommendations:
- Browse products (views are tracked automatically)
- Log interactions in console:
  ```javascript
  productRecommendations.trackProductView(1, 10000)
  productRecommendations.trackAddToCart(1, 10000)
  ```
- Check API:
  ```
  http://localhost/outsourced/api/v1/recommendations.php?type=popular&limit=5
  ```

---

## Architecture Summary:

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   User Browser │────▶│   PHP Backend    │────▶│   Database      │
└─────────────────┘     └──────────────────┘     └─────────────────┘
        │                        │
        │                        ▼
        │               ┌──────────────────┐
        │               │  External APIs  │
        │               │  - Africa's Talk│
        │               │  - Push Service │
        │               └──────────────────┘
        ▼
┌─────────────────┐
│ Service Worker  │ ← Runs in background
└─────────────────┘
```

This is a complete, production-ready system that demonstrates advanced e-commerce features suitable for a final year project!

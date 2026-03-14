<?php
/**
 * Enhanced Chatbot Logic
 * Outsourced Technologies E-Commerce Platform
 * 
 * This provides AI-like responses using pattern matching
 */

class Chatbot {
    private $db;
    private $user_id;
    private $session_id;
    
    // Conversation context
    private $context = [];
    
    public function __construct($user_id = null) {
        global $pdo;
        $this->db = $pdo;
        $this->user_id = $user_id;
        $this->session_id = session_id();
    }
    
    /**
     * Process user message and generate response
     */
    public function processMessage($message) {
        $msg = strtolower(trim($message));
        
        // Check for context-based follow-up
        if (!empty($this->context['last_topic'])) {
            $response = $this->handleContextResponse($msg);
            if ($response) return $response;
        }
        
        // Greetings
        if ($this->match($msg, ['hi', 'hello', 'hey', 'hiya', 'greetings', 'good morning', 'good afternoon', 'salam', ' habari'])) {
            return $this->greetingResponse();
        }
        
        // Thanks / Goodbye
        if ($this->match($msg, ['bye', 'goodbye', 'thank you', 'thanks', 'ok thanks', 'appreciate', 'see you'])) {
            return $this->goodbyeResponse();
        }
        
        // Help requests
        if ($this->match($msg, ['help', 'support', ' assistance', 'can you help', 'need help', 'what can you do'])) {
            return $this->helpResponse();
        }
        
        // Products & Shopping
        if ($this->match($msg, ['product', 'buy', 'sell', 'shop', 'item', 'store', 'catalog', 'available'])) {
            return $this->productsResponse($msg);
        }
        
        // Prices / Cost
        if ($this->match($msg, ['price', 'cost', 'how much', 'expensive', 'cheap', 'affordable', 'discount', 'offer', 'promo'])) {
            return $this->priceResponse($msg);
        }
        
        // Orders
        if ($this->match($msg, ['order', 'track', 'delivery', 'shipping', 'arrived', 'when will', 'order status'])) {
            return $this->orderResponse($msg);
        }
        
        // Payment
        if ($this->match($msg, ['pay', 'payment', 'mpesa', 'money', 'cash', 'bank', 'transfer', 'stk push'])) {
            return $this->paymentResponse();
        }
        
        // Services / Repairs
        if ($this->match($msg, ['repair', 'service', 'fix', 'broken', 'diagnostic', 'maintenance', 'config', 'setup', 'installation'])) {
            return $this->serviceResponse();
        }
        
        // Returns / Refunds
        if ($this->match($msg, ['return', 'exchange', 'warranty', 'guarantee', 'refund', 'broken', 'defective'])) {
            return $this->returnResponse();
        }
        
        // Contact / Location
        if ($this->match($msg, ['contact', 'location', 'address', 'where', 'phone', 'email', 'reach', 'visit', 'office', 'shop'])) {
            return $this->contactResponse();
        }
        
        // Loyalty / Points
        if ($this->match($msg, ['loyalty', 'points', 'reward', 'discount', 'tier', 'bronze', 'silver', 'gold', 'diamond', 'platinum'])) {
            return $this->loyaltyResponse();
        }
        
        // Account / Login
        if ($this->match($msg, ['login', 'account', 'register', 'sign up', 'password', 'forgot', 'profile'])) {
            return $this->accountResponse();
        }
        
        // Compare products
        if ($this->match($msg, ['compare', 'comparison', 'vs', 'versus', 'difference', 'which is better'])) {
            return $this->compareResponse();
        }
        
        // Cart / Shopping cart
        if ($this->match($msg, ['cart', 'basket', 'shopping cart', 'my cart', 'view cart'])) {
            return $this->cartResponse();
        }
        
        // Wishlist
        if ($this->match($msg, ['wishlist', 'wish list', 'favorites', 'saved items'])) {
            return $this->wishlistResponse();
        }
        
        // Stock / Availability
        if ($this->match($msg, ['stock', 'available', 'in stock', 'out of stock', '库存', 'hapa'])) {
            return $this->stockResponse($msg);
        }
        
        // FAQ
        if ($this->match($msg, ['faq', 'question', 'common', 'usually'])) {
            return $this->faqResponse();
        }
        
        // Complaints
        if ($this->match($msg, ['complaint', 'problem', 'issue', 'bad', 'worst', 'disappointed', 'frustrated'])) {
            return $this->complaintResponse();
        }
        
        // compliments
        if ($this->match($msg, ['good', 'great', 'awesome', 'amazing', 'excellent', 'love', 'nice', 'best'])) {
            return $this->complimentResponse();
        }
        
        // Default response
        return $this->defaultResponse($msg);
    }
    
    /**
     * Check if message matches any patterns
     */
    private function match($msg, $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($msg, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get products from database
     */
    private function getProducts($category = null, $limit = 5) {
        $sql = "SELECT name, price, stock FROM products WHERE visible = 1";
        $params = [];
        
        if ($category) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = '%' . $category . '%';
            $params[] = '%' . $category . '%';
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Greeting response
     */
    private function greetingResponse() {
        $greeting = "Hello! 👋 Welcome to Outsourced Technologies!";
        
        // If user is logged in, personalize the greeting
        if ($this->user_id) {
            $user = $this->getUserInfo();
            if ($user) {
                $greeting = "Welcome back, " . $user['name'] . "! 👋";
                
                // Add loyalty info if available
                if (isset($user['loyalty_points'])) {
                    $greeting .= "\n\n⭐ You have **" . number_format($user['loyalty_points']) . "** loyalty points!";
                    $tier = $this->getLoyaltyTier($user['loyalty_points']);
                    $greeting .= "\n💎 Your tier: **" . $tier . "**";
                }
            }
        } else {
            $greeting .= "\n\n🔐 Login to track orders, earn points & get exclusive discounts!";
        }
        
        return $greeting . "\n\n" . $this->getQuickOptions();
    }
    
    /**
     * Get user info
     */
    private function getUserInfo() {
        if (!$this->user_id) return null;
        
        try {
            $stmt = $this->db->prepare(
                "SELECT id, name, email, phone, loyalty_points FROM users WHERE id = ?"
            );
            $stmt->execute([$this->user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get loyalty tier based on points
     */
    private function getLoyaltyTier($points) {
        if ($points >= 5000) return 'Diamond';
        if ($points >= 3000) return 'Platinum';
        if ($points >= 1500) return 'Gold';
        if ($points >= 500) return 'Silver';
        return 'Bronze';
    }
    
    /**
     * Goodbye response
     */
    private function goodbyeResponse() {
        return "You're welcome! 🌟\n\nCome back anytime for:\n• Laptops & Computers\n• Phones & Tablets\n• Networking Equipment\n• Repairs & Services\n\nHave a great day! 😊";
    }
    
    /**
     * Help response
     */
    private function helpResponse() {
        return "I can help you with:\n\n" .
            "🛒 **Shopping** - Browse products, check prices, availability\n" .
            "💰 **Payments** - M-Pesa, payment status\n" .
            "📦 **Orders** - Track delivery, order status\n" .
            "🔧 **Services** - Repairs, diagnostics, ISP setup\n" .
            "⭐ **Loyalty** - Points, discounts, rewards\n" .
            "📞 **Contact** - Location, phone, support\n\n" .
            "What would you like to know?";
    }
    
    /**
     * Quick options keyboard
     */
    private function getQuickOptions() {
        $this->context['last_topic'] = 'main_menu';
        
        $options = [];
        
        // Always show these options
        $options[] = "View Products";
        $options[] = "Check Prices";
        $options[] = "Track Order";
        
        // If user is logged in, show more options
        if ($this->user_id) {
            $options[] = "My Orders";
            $options[] = "My Points";
            $options[] = "My Cart";
        } else {
            $options[] = "Book Repair";
            $options[] = "Contact Info";
        }
        
        return "Quick options:\n" . implode("\n", array_map(function($o) { return "- " . $o; }, $options));
    }
    
    /**
     * Products response
     */
    private function productsResponse($msg) {
        $this->context['last_topic'] = 'products';
        
        $reply = "We have a wide range of products:\n\n";
        
        $reply .= "💻 **LAPTOPS** - Dell, HP, Lenovo, MacBook\n";
        $reply .= "📱 **PHONES** - iPhone, Samsung, Xiaomi\n";
        $reply .= "🖥️ **DESKTOPS** - Custom builds, All-in-One\n";
        $reply .= "🌐 **NETWORKING** - Routers, Switches, Access Points\n";
        $reply .= "🖨️ **PRINTERS** - HP, Canon, Epson\n";
        $reply .= "💾 **STORAGE** - SSDs, HDDs, USB Drives\n";
        $reply .= "🔌 **ACCESSORIES** - Keyboards, Mice, Cables\n\n";
        
        // Check if specific product mentioned
        if (preg_match('/laptop|macbook|dell|hp|lenovo|asus|mac/', $msg)) {
            $products = $this->getProducts('laptop', 3);
            if (!empty($products)) {
                $reply .= "**Available Laptops:**\n";
                foreach ($products as $p) {
                    $reply .= "• {$p['name']} - KSh " . number_format($p['price']) . "\n";
                }
            }
        }
        
        $reply .= "\nWhich category interests you?";
        return $reply;
    }
    
    /**
     * Price response
     */
    private function priceResponse($msg) {
        $reply = "Here's our price guide:\n\n";
        $reply .= "💻 **Laptops**: KSh 35,000 - 250,000\n";
        $reply .= "📱 **Phones**: KSh 15,000 - 150,000\n";
        $reply .= "🖥️ **Desktops**: KSh 40,000 - 150,000\n";
        $reply .= "🌐 **Routers**: KSh 4,000 - 50,000\n";
        $reply .= "🖨️ **Printers**: KSh 15,000 - 80,000\n";
        $reply .= "💾 **SSD 500GB**: KSh 6,000 - 15,000\n\n";
        
        $reply .= "💎 **Loyalty Discounts:**\n";
        $reply .= "• Silver (500+ pts): 5% off\n";
        $reply .= "• Gold (1,500+ pts): 10% off\n";
        $reply .= "• Platinum (3,000+ pts): 15% off\n";
        $reply .= "• Diamond (5,000+ pts): 20% off\n\n";
        
        $reply .= "Want specific pricing on any item?";
        return $reply;
    }
    
    /**
     * Order response
     */
    private function orderResponse($msg) {
        $this->context['last_topic'] = 'orders';
        
        // Check if user provided order number or phone
        $order_id = $this->extractOrderId($msg);
        $phone = $this->extractPhone($msg);
        
        // If order ID or phone provided, try to lookup
        if ($order_id || $phone) {
            return $this->lookupOrder($order_id, $phone);
        }
        
        $reply = "📦 **Order Status & Tracking**\n\n";
        
        // If user is logged in, show their recent orders
        if ($this->user_id) {
            $recent_orders = $this->getUserRecentOrders();
            if (!empty($recent_orders)) {
                $reply .= "**Your Recent Orders:**\n\n";
                foreach ($recent_orders as $order) {
                    $status_emoji = $this->getStatusEmoji($order['status']);
                    $reply .= $status_emoji . " **Order #" . $order['order_number'] . "**\n";
                    $reply .= "   📅 " . date('M d, Y', strtotime($order['created_at'])) . "\n";
                    $reply .= "   💰 KSh " . number_format($order['total']) . "\n";
                    $reply .= "   📍 " . ucfirst($order['status']) . "\n\n";
                }
                $reply .= "Provide your order number (e.g., ORD-12345) for full tracking details.\n\n";
                return $reply;
            }
        }
        
        $reply .= "**To track your order, please provide:**\n";
        $reply .= "- Your order number (e.g., ORD-12345), OR\n";
        $reply .= "- Your phone number used during checkout\n\n";
        
        $reply .= "🚚 **Delivery Times:**\n";
        $reply .= "- Mlolongo/Syokimau: Same day\n";
        $reply .= "- Nairobi CBD: 1-2 days\n";
        $reply .= "- Outside Nairobi: 2-3 days\n\n";
        
        $reply .= "📍 **Pickup:** Free pickup at our shop - always available!\n\n";
        
        $reply .= "Track directly: /outsourced/public/track-order.php";
        
        return $reply;
    }
    
    /**
     * Extract order ID from message
     */
    private function extractOrderId($msg) {
        // Match patterns like ORD-12345, #12345, order 12345
        if (preg_match('/(?:order|ord|#)\s*[-#]?\s*(\d+)/i', $msg, $matches)) {
            return $matches[1];
        }
        // Match ORD-XXXXX format
        if (preg_match('/(ORD-\d+)/i', $msg, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Extract phone number from message
     */
    private function extractPhone($msg) {
        // Match Kenyan phone format
        if (preg_match('/(?:0|\+254|254)?[7]\d{8}/', $msg, $matches)) {
            $phone = $matches[0];
            // Normalize to 07XX XXX XXX format
            if (strlen($phone) == 12 && strpos($phone, '254') === 0) {
                return '0' . substr($phone, 3);
            }
            return $phone;
        }
        return null;
    }
    
    /**
     * Lookup order by ID or phone
     */
    private function lookupOrder($order_id = null, $phone = null) {
        try {
            if ($order_id) {
                // Clean order ID
                $order_id = preg_replace('/ORD-/i', '', $order_id);
                
                // Try to find by order number or ID
                $stmt = $this->db->prepare(
                    "SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.id 
                     WHERE o.id = ? OR o.order_number LIKE ?
                     ORDER BY o.created_at DESC LIMIT 5"
                );
                $stmt->execute([$order_id, '%' . $order_id]);
            } elseif ($phone && $this->user_id) {
                // If logged in, only show orders for this user
                $stmt = $this->db->prepare(
                    "SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.id 
                     WHERE o.user_id = ? AND (u.phone LIKE ? OR o.shipping_phone LIKE ?)
                     ORDER BY o.created_at DESC LIMIT 5"
                );
                $stmt->execute([$this->user_id, '%' . $phone, '%' . $phone]);
            } elseif ($phone) {
                // Not logged in - show orders by phone (without user_id check)
                $stmt = $this->db->prepare(
                    "SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.id 
                     WHERE u.phone LIKE ? OR o.shipping_phone LIKE ?
                     ORDER BY o.created_at DESC LIMIT 5"
                );
                $stmt->execute(['%' . $phone, '%' . $phone]);
            }
            
            if (isset($stmt)) {
                $orders = $stmt->fetchAll();
                
                if (empty($orders)) {
                    return "😕 I couldn't find any orders matching that information.\n\n" .
                           "Please check:\n" .
                           "- Your order number is correct\n" .
                           "- Your phone number matches what you used at checkout\n\n" .
                           "Or login to see your order history!";
                }
                
                if (count($orders) == 1) {
                    return $this->formatOrderDetails($orders[0]);
                }
                
                // Multiple orders found
                $reply = "📦 **Found " . count($orders) . " Orders:**\n\n";
                foreach ($orders as $order) {
                    $status_emoji = $this->getStatusEmoji($order['status']);
                    $reply .= $status_emoji . " **Order #" . $order['order_number'] . "**\n";
                    $reply .= "   📅 " . date('M d, Y', strtotime($order['created_at'])) . "\n";
                    $reply .= "   💰 KSh " . number_format($order['total']) . "\n";
                    $reply .= "   📍 " . ucfirst($order['status']) . "\n\n";
                }
                $reply .= "Which order would you like details for?";
                return $reply;
            }
        } catch (Exception $e) {
            return "⚠️ Error looking up order. Please try again or contact support.";
        }
        
        return "I couldn't find that order. Please provide your order number or phone number.";
    }
    
    /**
     * Get status emoji
     */
    private function getStatusEmoji($status) {
        $statuses = [
            'pending' => '⏳',
            'processing' => '🔄',
            'shipped' => '📦',
            'delivered' => '✅',
            'cancelled' => '❌',
            'paid' => '💳',
            'completed' => '🎉'
        ];
        return $statuses[strtolower($status)] ?? '📋';
    }
    
    /**
     * Format order details
     */
    private function formatOrderDetails($order) {
        $reply = "📋 **Order #" . $order['order_number'] . "**\n\n";
        
        $reply .= "**Status:** " . $this->getStatusEmoji($order['status']) . " " . ucfirst($order['status']) . "\n";
        $reply .= "**Date:** 📅 " . date('F d, Y \a\t g:i A', strtotime($order['created_at'])) . "\n";
        $reply .= "**Total:** 💰 KSh " . number_format($order['total']) . "\n\n";
        
        if (!empty($order['shipping_address'])) {
            $reply .= "**Shipping Address:**\n" . $order['shipping_address'] . "\n\n";
        }
        
        // Get order items
        try {
            $stmt = $this->db->prepare(
                "SELECT oi.*, p.name as product_name, p.image_url 
                 FROM order_items oi 
                 LEFT JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = ?"
            );
            $stmt->execute([$order['id']]);
            $items = $stmt->fetchAll();
            
            if (!empty($items)) {
                $reply .= "**Items:**\n";
                foreach ($items as $item) {
                    $reply .= "- " . $item['product_name'] . " x" . $item['quantity'] . " - KSh " . number_format($item['price']) . "\n";
                }
            }
        } catch (Exception $e) {
            // Skip items if error
        }
        
        // Add tracking info if shipped
        if (in_array($order['status'], ['shipped', 'delivered'])) {
            $reply .= "\n🚚 **Tracking:** Your order is on its way!\n";
            $reply .= "Track live: /outsourced/public/track-order.php?order=" . $order['order_number'];
        }
        
        return $reply;
    }
    
    /**
     * Get user's recent orders
     */
    private function getUserRecentOrders() {
        if (!$this->user_id) return [];
        
        try {
            $stmt = $this->db->prepare(
                "SELECT order_number, total, status, created_at 
                 FROM orders 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC LIMIT 3"
            );
            $stmt->execute([$this->user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Payment response
     */
    private function paymentResponse() {
        $reply = "We accept the following payments:\n\n";
        $reply .= "📱 **M-Pesa** (Recommended)\n";
        $reply .= "   • STK Push - Instant payment\n";
        $reply .= "   • Paybill: 123456\n";
        $reply .= "   • Account: Your phone number\n\n";
        
        $reply .= "💵 **Cash** - Pay on pickup/delivery\n";
        $reply .= "🏦 **Bank Transfer** - On request\n\n";
        
        $reply .= "🔒 All payments are secure and confirmed via SMS!";
        
        return $reply;
    }
    
    /**
     * Service response
     */
    private function serviceResponse() {
        $reply = "🔧 Our Services:\n\n";
        
        $reply .= "**REPMAIRS:**\n";
        $reply .= "• Laptop Screen Replacement\n";
        $reply .= "• Battery Replacement\n";
        $reply .= "• Keyboard Repair\n";
        $reply .= "• Water Damage\n";
        $reply .= "• Data Recovery\n\n";
        
        $reply .= "**DIAGNOSTICS:**\n";
        $reply .= "• Software Issues\n";
        $reply .= "• Hardware Check\n";
        $reply .= "• Virus Removal\n\n";
        
        $reply .= "**ISP SERVICES:**\n";
        $reply .= "• WiFi Installation\n";
        $reply .= "• Network Setup\n";
        $reply .= "• CCTV Installation\n\n";
        
        $reply .= "Starting at KSh 500 for diagnostics!\n";
        $reply .= "Book at: /outsourced/public/services.php";
        
        return $reply;
    }
    
    /**
     * Return/Refund response
     */
    private function returnResponse() {
        $reply = "📋 Our Return Policy:\n\n";
        $reply .= "✅ 7-day return for defective items\n";
        $reply .= "✅ Original packaging required\n";
        $reply .= "✅ Receipt/invoice needed\n";
        $reply .= "✅ Contact us first before returning\n\n";
        
        $reply .= "🔧 **Warranty:**\n";
        $reply .= "• Laptops: 1-year warranty\n";
        $reply .= "• Phones: Manufacturer warranty\n";
        $reply .= "• Accessories: 3-month warranty\n\n";
        
        $reply .= "Questions? Contact us with your order number!";
        
        return $reply;
    }
    
    /**
     * Contact response
     */
    private function contactResponse() {
        $reply = "📍 **Visit Us:**\n";
        $reply .= "Mlolongo, Along Airport Road\n";
        $reply .= "Nairobi, Kenya\n\n";
        
        $reply .= "📞 **Call/WhatsApp:**\n";
        $reply .= "+254 700 000 000\n\n";
        
        $reply .= "📧 **Email:**\n";
        $reply .= "info@outsourcedtechnologies.co.ke\n\n";
        
        $reply .= "🕐 **Hours:**\n";
        $reply .= "Mon-Fri: 8am - 6pm\n";
        $reply .= "Sat: 9am - 4pm\n";
        $reply .= "Sun: Closed\n\n";
        
        $reply .= "Location: /outsourced/public/index.php (scroll to bottom)";
        
        return $reply;
    }
    
    /**
     * Loyalty response
     */
    private function loyaltyResponse() {
        $reply = "⭐ **Loyalty Program**\n\n";
        
        // If logged in, show user's points
        if ($this->user_id) {
            $user = $this->getUserInfo();
            if ($user && isset($user['loyalty_points'])) {
                $points = $user['loyalty_points'];
                $tier = $this->getLoyaltyTier($points);
                
                $reply .= "**Your Status:**\n";
                $reply .= "⭐ Points: **" . number_format($points) . "**\n";
                $reply .= "💎 Tier: **" . $tier . "**\n\n";
                
                // Show progress to next tier
                $next_tier = $this->getNextTierInfo($points);
                if ($next_tier) {
                    $reply .= "📈 Progress to **" . $next_tier['name'] . "**:\n";
                    $reply .= $next_tier['remaining'] . " more points needed\n";
                    $reply .= $next_tier['benefit'] . "\n\n";
                }
            }
        }
        
        $reply .= "**TIER BENEFITS:**\n";
        $reply .= "🥉 Bronze (0+ pts): Base member\n";
        $reply .= "🥈 Silver (500+ pts): 5% discount\n";
        $reply .= "🥇 Gold (1,500+ pts): 10% discount + Free delivery\n";
        $reply .= "💎 Platinum (3,000+ pts): 15% discount + Priority support\n";
        $reply .= "👑 Diamond (5,000+ pts): 20% discount + Exclusive deals\n\n";
        
        $reply .= "Earn 1 point for every KSh 100 spent!\n";
        
        if (!$this->user_id) {
            $reply .= "\n💳 Login to see your points and start earning!";
        }
        
        return $reply;
    }
    
    /**
     * Get next tier info
     */
    private function getNextTierInfo($points) {
        $tiers = [
            ['name' => 'Silver', 'min' => 500, 'benefit' => '5% discount'],
            ['name' => 'Gold', 'min' => 1500, 'benefit' => '10% discount + free delivery'],
            ['name' => 'Platinum', 'min' => 3000, 'benefit' => '15% discount + priority support'],
            ['name' => 'Diamond', 'min' => 5000, 'benefit' => '20% discount + exclusive deals']
        ];
        
        foreach ($tiers as $tier) {
            if ($points < $tier['min']) {
                return [
                    'name' => $tier['name'],
                    'remaining' => number_format($tier['min'] - $points),
                    'benefit' => $tier['benefit']
                ];
            }
        }
        
        return null; // Already at highest tier
    }
    
    /**
     * Account response
     */
    private function accountResponse() {
        $reply = "👤 **Account Help:**\n\n";
        
        $reply .= "**Register:** /outsourced/public/register.php\n";
        $reply .= "**Login:** /outsourced/public/login.php\n";
        $reply .= "**Profile:** /outsourced/public/profile.php\n\n";
        
        $reply .= "**Forgot Password:**\n";
        $reply .= "Use the 'Forgot Password' link on login page\n\n";
        
        $reply .= "Need help with anything else?";
        
        return $reply;
    }
    
    /**
     * Compare response
     */
    private function compareResponse() {
        $reply = "🔍 **Product Comparison**\n\n";
        $reply .= "Compare up to 4 products side-by-side!\n\n";
        $reply .= "Features compared:\n";
        $reply .= "- Price\n";
        $reply .= "- Specifications\n";
        $reply .= "- Ratings\n";
        $reply .= "- Stock availability\n\n";
        
        $reply .= "Try it at: /outsourced/public/compare.php\n\n";
        
        $reply .= "Or ask me about specific products to compare!";
        
        return $reply;
    }
    
    /**
     * Cart response
     */
    private function cartResponse() {
        $reply = "🛒 **Your Shopping Cart**\n\n";
        
        // Try to get cart from session
        $cart_items = [];
        $cart_total = 0;
        
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $cart_items[] = $item;
                $cart_total += $item['price'] * $item['quantity'];
            }
        }
        
        if (empty($cart_items)) {
            $reply .= "Your cart is empty!\n\n";
            $reply .= "Browse our products:\n";
            $reply .= "- /outsourced/public/products.php\n\n";
            $reply .= "What would you like to buy?";
            return $reply;
        }
        
        $reply .= "**Items in cart:**\n\n";
        foreach ($cart_items as $item) {
            $reply .= "- " . $item['name'] . " x" . $item['quantity'] . "\n";
            $reply .= "   KSh " . number_format($item['price'] * $item['quantity']) . "\n";
        }
        
        $reply .= "\n**Total: KSh " . number_format($cart_total) . "**\n\n";
        
        $reply .= "Checkout: /outsourced/public/checkout.php\n";
        $reply .= "View cart: /outsourced/public/cart.php\n\n";
        
        $reply .= "Need to add more items? Just ask!";
        
        return $reply;
    }
    
    /**
     * Wishlist response
     */
    private function wishlistResponse() {
        $reply = "❤️ **Your Wishlist**\n\n";
        
        if (!$this->user_id) {
            $reply .= "Login to save your favorite items!\n\n";
            $reply .= "Login: /outsourced/public/login.php\n";
            $reply .= "Register: /outsourced/public/register.php\n\n";
            $reply .= "What products are you interested in?";
            return $reply;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT w.id, w.created_at, p.name, p.price, p.image_url 
                 FROM wishlist w 
                 LEFT JOIN products p ON w.product_id = p.id 
                 WHERE w.user_id = ? 
                 ORDER BY w.created_at DESC LIMIT 5"
            );
            $stmt->execute([$this->user_id]);
            $items = $stmt->fetchAll();
            
            if (empty($items)) {
                $reply .= "Your wishlist is empty!\n\n";
                $reply .= "Browse products and save your favorites!\n\n";
                $reply .= "Products: /outsourced/public/products.php";
                return $reply;
            }
            
            $reply .= "**Your saved items:**\n\n";
            foreach ($items as $item) {
                $reply .= "⭐ " . $item['name'] . "\n";
                $reply .= "   KSh " . number_format($item['price']) . "\n\n";
            }
            
            $reply .= "View all: /outsourced/public/wishlist.php\n\n";
            $reply .= "Want to add any to cart?";
            
        } catch (Exception $e) {
            $reply .= "Could not load wishlist. Please try again later.";
        }
        
        return $reply;
    }
    
    /**
     * Stock response
     */
    private function stockResponse($msg) {
        // Check specific product
        preg_match_all('/(dell|hp|lenovo|apple|iphone|samsung|router|switch)/i', $msg, $matches);
        
        if (!empty($matches[1])) {
            $products = $this->getProducts($matches[1][0], 3);
            if (!empty($products)) {
                $reply = "**Available {$matches[1][0]} products:**\n\n";
                foreach ($products as $p) {
                    $stock = $p['stock'] > 0 ? "✅ In Stock ({$p['stock']})" : "❌ Out of Stock";
                    $reply .= "• {$p['name']} - KSh " . number_format($p['price']) . " - $stock\n";
                }
                return $reply;
            }
        }
        
        $reply = "📦 **Stock Status:**\n\n";
        $reply .= "Most items are in stock!\n";
        $reply .= "For specific availability, tell me the product name.\n\n";
        $reply .= "We restock weekly!";
        
        return $reply;
    }
    
    /**
     * FAQ response
     */
    private function faqResponse() {
        $reply = "❓ **Frequently Asked Questions:**\n\n";
        
        $reply .= "**Q: Do you deliver?**\n";
        $reply .= "A: Yes! Free delivery within 5km, KSh 200-1000 outside.\n\n";
        
        $reply .= "**Q: How long is warranty?**\n";
        $reply .= "A: Laptops 1 year, phones vary by model.\n\n";
        
        $reply .= "**Q: Can I pay with M-Pesa?**\n";
        $reply .= "A: Yes! We accept M-Pesa STK Push.\n\n";
        
        $reply .= "**Q: Do you repair phones?**\n";
        $reply .= "A: Yes! Screen, battery, and more.\n\n";
        
        $reply .= "More questions? Ask me!";
        
        return $reply;
    }
    
    /**
     * Complaint response
     */
    private function complaintResponse() {
        $reply = "😟 I'm sorry to hear that!\n\n";
        $reply .= "Please contact our support team:\n\n";
        $reply .= "📞 +254 700 000 000\n";
        $reply .= "📧 support@outsourcedtechnologies.co.ke\n\n";
        
        $reply .= "Please provide:\n";
        $reply .= "• Your order number\n";
        $reply .= "• Description of the issue\n\n";
        
        $reply .= "We'll resolve this ASAP! 🙌";
        
        return $reply;
    }
    
    /**
     * Compliment response
     */
    private function complimentResponse() {
        $replies = [
            "Thank you so much! 😊 We're glad to help!",
            "We appreciate your kind words! 🙏 Come back soon!",
            "That's so kind of you! 🌟 Let us know if you need anything else!"
        ];
        
        return $replies[array_rand($replies)];
    }
    
    /**
     * Handle context-based follow-up responses
     */
    private function handleContextResponse($msg) {
        $topic = $this->context['last_topic'] ?? '';
        
        switch ($topic) {
            case 'products':
                if ($this->match($msg, ['laptop', 'phone', 'router', 'printer', 'tablet', 'accessory'])) {
                    return $this->productsResponse($msg);
                }
                break;
                
            case 'orders':
                // Check for order number or phone
                $order_id = $this->extractOrderId($msg);
                $phone = $this->extractPhone($msg);
                
                if ($order_id || $phone) {
                    return $this->lookupOrder($order_id, $phone);
                }
                
                // If user says "my order" or similar while logged in
                if ($this->user_id && $this->match($msg, ['my', 'i want', 'show', 'check', 'view'])) {
                    $recent_orders = $this->getUserRecentOrders();
                    if (!empty($recent_orders)) {
                        $reply = "**Your Recent Orders:**\n\n";
                        foreach ($recent_orders as $order) {
                            $status_emoji = $this->getStatusEmoji($order['status']);
                            $reply .= $status_emoji . " **Order #" . $order['order_number'] . "** - " . ucfirst($order['status']) . "\n";
                        }
                        $reply .= "\nWhich order would you like details for?";
                        return $reply;
                    }
                }
                break;
                
            case 'main_menu':
                // Handle quick option selections
                if ($this->match($msg, ['product', 'shop', 'buy'])) {
                    return $this->productsResponse($msg);
                }
                if ($this->match($msg, ['price', 'cost', 'how much'])) {
                    return $this->priceResponse($msg);
                }
                if ($this->match($msg, ['track', 'order', 'delivery', 'ship'])) {
                    return $this->orderResponse($msg);
                }
                if ($this->match($msg, ['book', 'repair', 'service', 'fix'])) {
                    return $this->serviceResponse();
                }
                if ($this->match($msg, ['contact', 'location', 'address', 'where'])) {
                    return $this->contactResponse();
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Default response for unrecognized messages
     */
    private function defaultResponse($msg) {
        // Check for product names in message
        $products = $this->getProducts($msg, 3);
        
        if (!empty($products)) {
            $reply = "I found these products that might interest you:\n\n";
            foreach ($products as $p) {
                $stock = $p['stock'] > 0 ? "✅" : "❌";
                $reply .= "• {$p['name']} - KSh " . number_format($p['price']) . " $stock\n";
            }
            $reply .= "\nWant more details?";
            return $reply;
        }
        
        $defaults = [
            "I'm not sure I understood that. 😅\n\n" . $this->getQuickOptions(),
            "Could you rephrase that? I want to help! 🤔\n\n" . $this->getQuickOptions(),
            "I didn't catch that. Let me know what you need! 😊"
        ];
        
        return $defaults[array_rand($defaults)];
    }
    
    /**
     * Save conversation to database
     */
    public function saveConversation($user_message, $bot_response) {
        if (!isset($this->db)) return;
        
        try {
            query(
                "INSERT INTO chatbot_conversations (user_id, user_message, bot_response) VALUES (?, ?, ?)",
                [$this->user_id, $user_message, $bot_response]
            );
        } catch (Exception $e) {
            // Silently fail
        }
    }
}

/**
 * Process chatbot message
 */
function processChatbotMessage($message, $user_id = null) {
    $chatbot = new Chatbot($user_id);
    $response = $chatbot->processMessage($message);
    $chatbot->saveConversation($message, $response);
    return $response;
}

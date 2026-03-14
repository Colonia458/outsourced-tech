<?php
// api/v1/chatbot.php

require_once '../../src/config.php';
require_once '../../src/database.php';
require_once '../../src/auth.php';

require_once '../../src/chatbot.php';

$data = json_decode(file_get_contents('php://input'), true);
$message = trim($data['message'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'reply' => 'Please type something!']);
    exit;
}

$msg = strtolower($message);
$reply = "Hi! I'm here to help. What would you like to know? 😊";

// ────────────── Greetings ──────────────
if (preg_match('/(hi|hello|hey|good morning|good afternoon|salam)/', $msg)) {
    $reply = "Hey there! Welcome to Outsourced Technologies!
🛒 We sell: Laptops, Phones, Networking Equipment, Accessories
🔧 We fix: Laptops, Phones, Tablets
🌐 ISP Services: Network installation & setup

How can I help you today?";
}

// ────────────── Thanks / Goodbye ──────────────
elseif (preg_match('/(bye|thank you|thanks|ok|see you)/', $msg)) {
    $reply = "You're welcome! Come back anytime. Have a great day!
💻 Laptops from KSh 45,000
📱 Phones from KSh 28,000
🌐 Routers from KSh 6,000

See you soon!";
}

// ────────────── Products ──────────────
elseif (preg_match('/(product|buy|sell|shop|item|store)/', $msg)) {
    if (preg_match('/laptop|macbook|dell|hp|lenovo|asus/', $msg)) {
        $reply = "LAPTOPS AVAILABLE:
• Dell XPS 15 - KSh 145,000 (i7, 16GB RAM, 512GB SSD)
• HP EliteBook 840 - KSh 85,000 (i5, 8GB RAM, 256GB SSD)
• MacBook Air M2 - KSh 135,000 (Apple M2, 8GB RAM)
• Lenovo ThinkPad E14 - KSh 78,000 (i5, 16GB RAM, 512GB SSD)

All laptops come with 1-year warranty!";
    } elseif (preg_match('/phone|iphone|samsung|galaxy|redmi/', $msg)) {
        $reply = "PHONES AVAILABLE:
• iPhone 14 (128GB) - KSh 125,000
• Samsung Galaxy S23 - KSh 98,000
• Redmi Note 12 - KSh 28,000

TABLETS:
• iPad 10th Gen (64GB) - KSh 65,000
• Samsung Galaxy Tab A8 - KSh 32,000

Want to buy any of these?";
    } elseif (preg_match('/router|network|switch|modem/', $msg)) {
        $reply = "ROUTERS:
• TP-Link Archer AX73 (WiFi 6) - KSh 18,500
• MikroTik hAP ac3 - KSh 16,500
• Cisco RV340 (VPN) - KSh 45,000

SWITCHES:
• TP-Link 8-Port - KSh 4,500
• TP-Link 16-Port - KSh 12,000
• Cisco 48-Port Managed - KSh 85,000

Need help choosing?";
    } elseif (preg_match('/accessory|mouse|keyboard|cable|charger|hub/', $msg)) {
        $reply = "ACCESSORIES:

MOUSE:
• Logitech MX Master 3S - KSh 12,500
• HP X1000 Wired - KSh 850

KEYBOARDS:
• Logitech K380 (Bluetooth) - KSh 6,500
• HP Keyboard 100 - KSh 1,800

CABLES & CHARGERS:
• Cat6 Cable 10m - KSh 1,200
• Universal Charger 65W - KSh 3,500
• USB-C Hub 7-in-1 - KSh 4,500";
    } elseif (preg_match('/printer|print|scan/', $msg)) {
        $reply = "PRINTERS:
• HP LaserJet Pro MFP - KSh 65,000 (Monochrome, All-in-One)
• Canon PIXMA G3010 - KSh 18,500 (InkTank with CISS)
• Epson L3250 - KSh 22,000 (InkTank with WiFi)

Need ink or toner?";
    } elseif (preg_match('/storage|ssd|hard drive|usb|pendrive/', $msg)) {
        $reply = "STORAGE DEVICES:

INTERNAL SSD:
• Samsung 980 PRO 1TB (NVMe) - KSh 15,000
• Kingston A400 480GB (SATA) - KSh 5,500

PORTABLE:
• WD Blue 1TB - KSh 5,500
• SanDisk Ultra Flair 64GB - KSh 1,200
• Kingston 128GB USB - KSh 1,800";
    } else {
        $reply = "OUR PRODUCTS:
💻 Laptops: From KSh 45,000
📱 Phones: From KSh 28,000
🌐 Routers: From KSh 6,000
🖥️ Switches: From KSh 4,500
🎧 Accessories: From KSh 850
💾 Storage: From KSh 1,200
🖨️ Printers: From KSh 18,500

What category interests you?";
    }
}

// ────────────── Services & Repairs ──────────────
elseif (preg_match('/(repair|fix|broken|damage|service)/', $msg)) {
    if (preg_match('/(laptop|computer|macbook)/', $msg)) {
        $reply = "LAPTOP REPAIRS:
• Diagnostics - KSh 1,500 (free if you proceed with repair)
• Screen Replacement - KSh 5,000
• Battery Replacement - KSh 3,500
• Keyboard Repair - KSh 2,500
• Virus Removal - KSh 2,000
• Data Recovery - KSh 5,000+

We also do motherboard repairs. Bring your laptop to Mlolongo!";
    } elseif (preg_match('/(phone|iphone|samsung|galaxy)/', $msg)) {
        $reply = "PHONE REPAIRS:
• Screen Replacement - KSh 2,000 - 8,000
• Battery Replacement - KSh 1,500 - 4,000
• Charging Port - KSh 1,500 - 3,000
• Water Damage - KSh 2,500
• Software Issues - KSh 1,500

What phone do you have and what's the problem?";
    } elseif (preg_match('/(tablet|ipad)/', $msg)) {
        $reply = "TABLET REPAIRS:
• Screen Replacement - KSh 3,500 - 12,000
• Battery Replacement - KSh 3,500
• Software/Firmware - KSh 2,000

Bring your tablet to our Mlolongo shop!";
    } else {
        $reply = "OUR REPAIR SERVICES:
🖥️ Laptop Repairs: From KSh 1,500
📱 Phone Repairs: From KSh 1,500
📱 Tablet Repairs: From KSh 2,000
🔌 Electronics: Call for quote

Free diagnostics! What needs fixing?";
    }
}

// ────────────── Pricing ──────────────
elseif (preg_match('/(price|cost|how much|charges|fee|quote)/', $msg)) {
    if (preg_match('/laptop|macbook/', $msg)) {
        $reply = "LAPTOP PRICES:
• Dell XPS 15 - KSh 145,000
• HP EliteBook 840 - KSh 85,000
• MacBook Air M2 - KSh 135,000
• Lenovo ThinkPad - KSh 78,000

Want me to add one to cart?";
    } elseif (preg_match('/router|network/', $msg)) {
        $reply = "NETWORKING PRICES:
• TP-Link Router AX73 - KSh 18,500
• MikroTik Router - KSh 16,500
• 8-Port Switch - KSh 4,500
• 16-Port Switch - KSh 12,000

Need enterprise grade?";
    } else {
        $reply = "QUICK PRICE GUIDE:
Laptops: KSh 45,000 - 145,000
Phones: KSh 28,000 - 125,000
Tablets: KSh 32,000 - 65,000
Routers: KSh 6,000 - 45,000
Switches: KSh 4,500 - 85,000
Printers: KSh 18,500 - 65,000
Repairs: KSh 1,500+

What specific item do you want?";
    }
}

// ────────────── Delivery ──────────────
elseif (preg_match('/(deliver|delivery|ship|send|post|location|area|address)/', $msg)) {
    $reply = "DELIVERY INFO:

Mlolongo & Syokimau: FREE (within 5km)
Nairobi CBD: KSh 500
Nairobi Metro: KSh 800
Outside Nairobi: KSh 1,500+

Free delivery on orders over KSh 5,000 in Mlolongo!
Or pickup at shop - always free.

Where are you located?";
}

// ────────────── Payment ──────────────
elseif (preg_match('/(mpesa|pay|payment|paybill|lipa|stk|bank|cash)/', $msg)) {
    $reply = "PAYMENT OPTIONS:

1. M-PESA (Recommended)
   - During checkout, enter your phone
   - You'll get STK push prompt
   - Enter PIN - done!

2. Cash on Delivery
   - Pay when you receive your order

3. Bank Transfer
   - Available on request

Need M-Pesa instructions?";
}

// ────────────── Booking ──────────────
elseif (preg_match('/(book|booking|schedule|appointment|time|date|when)/', $msg)) {
    $reply = "BOOKING A SERVICE:

1. Go to Services page
2. Choose your service
3. Select preferred date/time
4. We'll confirm within 24 hours

Services: Repairs, Installations, Setup

What service do you need?";
}

// ────────────── Warranty ──────────────
elseif (preg_match('/(warranty|guarantee|return|refund|exchange)/', $msg)) {
    $reply = "WARRANTY & RETURNS:

PRODUCTS:
- 1-year supplier warranty on most items
- Original accessories only

REPAIRS:
- 30-90 days warranty on repairs
- Depends on the repair type

RETURNS:
- Within 7 days if unused
- Original packaging required

Need more details?";
}

// ────────────── Location ──────────────
elseif (preg_match('/(where|location|shop|address|mlolongo|find|visit)/', $msg)) {
    $reply = "OUR LOCATION:

Outsourced Technologies
Mlolongo, Nairobi, Kenya

OPENING HOURS:
Monday - Friday: 8:00 AM - 6:00 PM
Saturday: 9:00 AM - 5:00 PM
Sunday: Closed

Come visit us for repairs, purchases, or inquiries!";
}

// ────────────── ISP Services ──────────────
elseif (preg_match('/(isp|internet|wifi|network setup|fiber|broadband)/', $msg)) {
    $reply = "ISP & NETWORK SERVICES:

We offer:
- Router configuration & setup
- WiFi network installation
- Office network cabling
- Fiber internet setup
- Mesh WiFi systems

Installation: From KSh 2,500
Configuration: From KSh 1,500

Need a quote for your office or home?";
}

// ────────────── Contact ──────────────
elseif (preg_match('/(contact|phone|call|email|reach|talk)/', $msg)) {
    $reply = "CONTACT US:

Phone: 0712 345678
Email: info@outsourcedtech.co.ke
WhatsApp: 0712 345678

Or use the chat - we're here to help!
Location: Mlolongo, Nairobi";
}

// ────────────── Loyalty Program ──────────────
elseif (preg_match('/(loyalty|points|discount|bronze|silver|gold|platinum|tier)/', $msg)) {
    $reply = "LOYALTY PROGRAM:

Earn points with every purchase!

Bronze: 0-499 points (0% discount)
Silver: 500-1,499 points (5% off)
Gold: 1,500-4,999 points (10% off + free delivery)
Platinum: 5,000+ points (15% off + free delivery)

1 point per KSh 100 spent!
Login to see your points.";
}

// Fallback for unknown questions
if ($reply === "Hi! I'm here to help. What would you like to know?") {
    $reply = "I'm not sure about that one, but I can help you with:

Products & Prices
Repairs & Services
Delivery Info
Payment Methods
Our Location
Loyalty Points

What would you like to know about?";
}

// Get user ID if logged in
$user_id = current_user_id();

// Process message with enhanced chatbot
$reply = processChatbotMessage($message, $user_id);

echo json_encode([
    'success' => true,
    'reply'   => $reply
]);

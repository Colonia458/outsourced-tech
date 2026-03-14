-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 01:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `outsourced_tech`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_award_loyalty_points` (IN `p_order_id` INT, IN `p_user_id` INT, IN `p_amount` DECIMAL(12,2))   BEGIN
    DECLARE points_earned INT;
    DECLARE points_value DECIMAL(12,2);
    
    -- Calculate points: 1 point per 100 KES
    SET points_earned = FLOOR(p_amount / 100);
    
    IF points_earned > 0 THEN
        -- Update user points
        UPDATE users SET loyalty_points = loyalty_points + points_earned WHERE id = p_user_id;
        
        -- Record transaction
        INSERT INTO loyalty_transactions (user_id, order_id, points, type, description, expires_at)
        VALUES (p_user_id, p_order_id, points_earned, 'earn', 
                CONCAT('Points earned for order #', p_order_id),
                DATE_ADD(NOW(), INTERVAL 6 MONTH));
        
        -- Update order
        UPDATE orders SET loyalty_points_earned = points_earned WHERE id = p_order_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_expire_loyalty_points` ()   BEGIN
    UPDATE loyalty_transactions 
    SET points = 0, type = 'expire', description = CONCAT(description, ' - Expired')
    WHERE expires_at < NOW() 
    AND type = 'earn'
    AND points > 0;
    
    -- Log expired points
    INSERT INTO loyalty_transactions (user_id, points, type, description)
    SELECT user_id, SUM(points) * -1, 'expire', 'Monthly points expiration'
    FROM loyalty_transactions
    WHERE expires_at < NOW() 
    AND type = 'earn'
    GROUP BY user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_product_visibility` ()   BEGIN
    -- Hide out of stock products
    UPDATE products 
    SET visible = 0 
    WHERE stock = 0 AND visible = 1;
    
    -- Log inventory alerts
    INSERT INTO inventory_alerts (product_id, alert_type, previous_stock, current_stock)
    SELECT id, 'out_of_stock', stock, 0
    FROM products 
    WHERE stock = 0 AND visible = 1;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `abandoned_carts`
--

CREATE TABLE `abandoned_carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `cart_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`cart_data`)),
  `cart_total` decimal(12,2) NOT NULL,
  `recovery_email_sent` tinyint(1) DEFAULT 0,
  `recovery_email_sent_at` timestamp NULL DEFAULT NULL,
  `recovered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `role` enum('admin','manager','support') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `full_name`, `role`, `created_at`, `active`) VALUES
(1, 'admin', '$2y$10$oWLJvD3qRByN8R7aeb892eIisb90Qfb12/9qKlPqwW8W7Yb64iuL6', 'Administrator', 'admin', '2026-02-25 08:29:39', 1);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `parent_id`, `display_order`, `created_at`) VALUES
(1, 'Hubs', 'hubs', 'Network hubs for basic connectivity', NULL, 10, '2026-02-15 08:41:57'),
(2, 'Switches', 'switches', 'Managed and unmanaged network switches', NULL, 20, '2026-02-15 08:41:57'),
(3, 'Routers', 'routers', 'Home, business and enterprise routers', NULL, 30, '2026-02-15 08:41:57'),
(4, 'Computers & Laptops', 'computers-laptops', 'Desktops, laptops, all-in-one PCs', NULL, 40, '2026-02-15 08:41:57'),
(5, 'Phones & Tablets', 'phones-tablets', 'Smartphones, feature phones, tablets', NULL, 50, '2026-02-15 08:41:57'),
(6, 'Accessories', 'accessories', 'Cables, chargers, mice, keyboards, hubs', NULL, 60, '2026-02-15 08:41:57'),
(7, 'Storage', 'storage', 'HDDs, SSDs, USB drives, memory cards', NULL, 70, '2026-02-15 08:41:57'),
(8, 'Printers & Scanners', 'printers-scanners', 'Inkjet, laser, multifunction devices', NULL, 80, '2026-02-15 08:41:57'),
(21, 'Laptops', 'laptops', 'Professional laptops for business and personal use', NULL, 1, '2026-02-15 09:05:43'),
(22, 'Networking', 'networking', 'Routers, switches, hubs, and network accessories', NULL, 2, '2026-02-15 09:05:43'),
(23, 'Printers', 'printers', 'Printers, scanners, and printing supplies', NULL, 6, '2026-02-15 09:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_conversations`
--

CREATE TABLE `chatbot_conversations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_id` varchar(60) DEFAULT NULL,
  `user_message` text NOT NULL,
  `bot_response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chatbot_conversations`
--

INSERT INTO `chatbot_conversations` (`id`, `user_id`, `session_id`, `user_message`, `bot_response`, `created_at`) VALUES
(1, 1, NULL, 'hi', 'Hey there! Welcome to Outsourced Technologies!\r\n🛒 We sell: Laptops, Phones, Networking Equipment, Accessories\r\n🔧 We fix: Laptops, Phones, Tablets\r\n🌐 ISP Services: Network installation & setup\r\n\r\nHow can I help you today?', '2026-02-15 10:20:32'),
(2, 2, NULL, 'hi', 'Hey there! Welcome to Outsourced Technologies!\r\n🛒 We sell: Laptops, Phones, Networking Equipment, Accessories\r\n🔧 We fix: Laptops, Phones, Tablets\r\n🌐 ISP Services: Network installation & setup\r\n\r\nHow can I help you today?', '2026-02-16 09:52:44'),
(3, 2, NULL, 'phones', 'CONTACT US:\r\n\r\nPhone: 0712 345678\r\nEmail: info@outsourcedtech.co.ke\r\nWhatsApp: 0712 345678\r\n\r\nOr use the chat - we\'re here to help!\r\nLocation: Mlolongo, Nairobi', '2026-02-16 09:53:01'),
(4, 2, NULL, 'how much is network installation', 'NETWORKING PRICES:\r\n• TP-Link Router AX73 - KSh 18,500\r\n• MikroTik Router - KSh 16,500\r\n• 8-Port Switch - KSh 4,500\r\n• 16-Port Switch - KSh 12,000\r\n\r\nNeed enterprise grade?', '2026-02-16 09:53:29'),
(5, 1, NULL, 'services offered', 'OUR REPAIR SERVICES:\r\n🖥️ Laptop Repairs: From KSh 1,500\r\n📱 Phone Repairs: From KSh 1,500\r\n📱 Tablet Repairs: From KSh 2,000\r\n🔌 Electronics: Call for quote\r\n\r\nFree diagnostics! What needs fixing?', '2026-02-25 09:59:23'),
(6, 1, NULL, 'customer care', 'Hi! I\'m here to help. What would you like to know? 😊', '2026-02-25 09:59:31'),
(7, 1, NULL, 'hi', 'Hey there! Welcome to Outsourced Technologies!\r\n🛒 We sell: Laptops, Phones, Networking Equipment, Accessories\r\n🔧 We fix: Laptops, Phones, Tablets\r\n🌐 ISP Services: Network installation & setup\r\n\r\nHow can I help you today?', '2026-02-25 10:47:11'),
(8, 1, NULL, 'hello', 'Hey there! Welcome to Outsourced Technologies!\r\n🛒 We sell: Laptops, Phones, Networking Equipment, Accessories\r\n🔧 We fix: Laptops, Phones, Tablets\r\n🌐 ISP Services: Network installation & setup\r\n\r\nHow can I help you today?', '2026-02-25 10:58:40'),
(9, 1, NULL, 'Cisco Switch 48-Port', 'Hi! I\'m here to help. What would you like to know? 😊', '2026-02-25 13:46:32'),
(10, 1, NULL, 'hey', 'Hey there! Welcome to Outsourced Technologies!\r\n🛒 We sell: Laptops, Phones, Networking Equipment, Accessories\r\n🔧 We fix: Laptops, Phones, Tablets\r\n🌐 ISP Services: Network installation & setup\r\n\r\nHow can I help you today?', '2026-02-25 13:53:15'),
(11, 1, NULL, 'phones available', 'CONTACT US:\r\n\r\nPhone: 0712 345678\r\nEmail: info@outsourcedtech.co.ke\r\nWhatsApp: 0712 345678\r\n\r\nOr use the chat - we\'re here to help!\r\nLocation: Mlolongo, Nairobi', '2026-02-25 13:53:23'),
(12, 1, NULL, 'how much is a phone', 'QUICK PRICE GUIDE:\r\nLaptops: KSh 45,000 - 145,000\r\nPhones: KSh 28,000 - 125,000\r\nTablets: KSh 32,000 - 65,000\r\nRouters: KSh 6,000 - 45,000\r\nSwitches: KSh 4,500 - 85,000\r\nPrinters: KSh 18,500 - 65,000\r\nRepairs: KSh 1,500+\r\n\r\nWhat specific item do you want?', '2026-02-25 13:53:37'),
(13, 1, NULL, 'hi', 'Hello! 😊 We\'re here to help! What are you looking for?\n\nQuick options:\n• View Products\n• Check Prices\n• Track Order\n• Book Repair\n• Contact Info', '2026-02-27 16:44:34'),
(14, 1, NULL, 'prices', 'Here\'s our price guide:\n\n💻 **Laptops**: KSh 35,000 - 250,000\n📱 **Phones**: KSh 15,000 - 150,000\n🖥️ **Desktops**: KSh 40,000 - 150,000\n🌐 **Routers**: KSh 4,000 - 50,000\n🖨️ **Printers**: KSh 15,000 - 80,000\n💾 **SSD 500GB**: KSh 6,000 - 15,000\n\n💎 **Loyalty Discounts:**\n• Silver (500+ pts): 5% off\n• Gold (1,500+ pts): 10% off\n• Platinum (3,000+ pts): 15% off\n• Diamond (5,000+ pts): 20% off\n\nWant specific pricing on any item?', '2026-02-27 16:44:43'),
(15, 1, NULL, 'whats in stock', '📦 **Stock Status:**\n\nMost items are in stock!\nFor specific availability, tell me the product name.\n\nWe restock weekly!', '2026-02-27 16:44:54'),
(16, 1, NULL, 'login', '👤 **Account Help:**\n\n**Register:** /outsourced/public/register.php\n**Login:** /outsourced/public/login.php\n**Profile:** /outsourced/public/profile.php\n\n**Forgot Password:**\nUse the \'Forgot Password\' link on login page\n\nNeed help with anything else?', '2026-02-27 16:45:03'),
(17, 1, NULL, 'hi', 'Hey there! 👋 Welcome to Outsourced Technologies! How can I help you today?\n\nQuick options:\n• View Products\n• Check Prices\n• Track Order\n• Book Repair\n• Contact Info', '2026-03-07 14:02:10'),
(18, 1, NULL, 'hello', 'Hey there! 👋 Welcome to Outsourced Technologies! How can I help you today?\n\nQuick options:\n• View Products\n• Check Prices\n• Track Order\n• Book Repair\n• Contact Info', '2026-03-07 14:02:17'),
(19, 1, NULL, 'hi', 'Hello! 👋 Welcome to Outsourced Technologies!\n\nQuick options:\n- View Products\n- Check Prices\n- Track Order\n- My Orders\n- My Points\n- My Cart', '2026-03-07 14:13:59'),
(20, 1, NULL, 'hey', 'Hello! 👋 Welcome to Outsourced Technologies!\n\nQuick options:\n- View Products\n- Check Prices\n- Track Order\n- My Orders\n- My Points\n- My Cart', '2026-03-07 14:14:05');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_uses` int(10) UNSIGNED DEFAULT NULL,
  `used_count` int(10) UNSIGNED DEFAULT 0,
  `valid_from` datetime DEFAULT current_timestamp(),
  `valid_until` datetime DEFAULT NULL,
  `is_active` tinyint(3) UNSIGNED DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_tracking`
--

CREATE TABLE `delivery_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lng` decimal(11,8) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `source` enum('driver','admin','system') NOT NULL DEFAULT 'driver'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `max_distance_km` decimal(6,2) DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_for_free` decimal(12,2) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_zones`
--

INSERT INTO `delivery_zones` (`id`, `name`, `max_distance_km`, `fee`, `min_order_for_free`, `sort_order`, `active`) VALUES
(1, 'Pickup at Shop', NULL, 0.00, NULL, 1, 1),
(2, 'Mlolongo & Syokimau (Free)', 8.00, 0.00, 5000.00, 2, 1),
(3, 'Nairobi CBD & Nearby', 25.00, 250.00, 10000.00, 3, 1),
(4, 'Greater Nairobi', 50.00, 500.00, 20000.00, 4, 1),
(5, 'Outside Nairobi', NULL, 1000.00, NULL, 5, 1),
(6, 'Mlolongo Central', 5.00, 0.00, 5000.00, 1, 1),
(7, 'Mlolongo Area', 10.00, 200.00, 8000.00, 2, 1),
(8, 'Nairobi CBD', 25.00, 500.00, 15000.00, 3, 1),
(9, 'Nairobi Metro', 40.00, 800.00, 25000.00, 4, 1),
(10, 'Outside Nairobi', NULL, 1500.00, NULL, 5, 1),
(11, 'Kikuyu', 20.00, 200.00, 100.00, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_alerts`
--

CREATE TABLE `inventory_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','reorder') NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `current_stock` int(11) NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_tiers`
--

CREATE TABLE `loyalty_tiers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `min_points` int(10) UNSIGNED NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `free_delivery` tinyint(1) DEFAULT 0,
  `other_benefits` text DEFAULT NULL,
  `badge_color` varchar(20) DEFAULT '#cccccc',
  `badge_icon` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loyalty_tiers`
--

INSERT INTO `loyalty_tiers` (`id`, `name`, `min_points`, `discount_percent`, `free_delivery`, `other_benefits`, `badge_color`, `badge_icon`) VALUES
(1, 'Bronze', 0, 0.00, 0, NULL, '#cd7f32', NULL),
(2, 'Silver', 500, 5.00, 0, NULL, '#c0c0c0', NULL),
(3, 'Gold', 1500, 10.00, 1, NULL, '#ffd700', NULL),
(4, 'Platinum', 5000, 15.00, 1, NULL, '#e5e4e2', NULL),
(5, 'Bronze', 0, 0.00, 0, NULL, '#cd7f32', NULL),
(6, 'Silver', 500, 5.00, 0, NULL, '#c0c0c0', NULL),
(7, 'Gold', 1500, 10.00, 1, NULL, '#ffd700', NULL),
(8, 'Platinum', 5000, 15.00, 1, NULL, '#e5e4e2', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transactions`
--

CREATE TABLE `loyalty_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `points` int(11) NOT NULL,
  `type` enum('earn','redeem','expire','bonus') NOT NULL,
  `description` varchar(255) NOT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unsubscribed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('email','sms','push') NOT NULL,
  `event` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','processing','sent','failed') DEFAULT 'pending',
  `attempts` int(10) UNSIGNED DEFAULT 0,
  `max_attempts` int(10) UNSIGNED DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `status` enum('pending','processing','ready_for_delivery','shipped','delivered','cancelled','returned') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `payment_method` enum('mpesa','cash_on_delivery','bank_transfer') DEFAULT 'mpesa',
  `subtotal` decimal(12,2) NOT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `delivery_type` enum('pickup','home_delivery') DEFAULT 'home_delivery',
  `delivery_address` text DEFAULT NULL,
  `delivery_zone_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_note` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivery_lat` decimal(10,8) DEFAULT NULL COMMENT 'Customer pinned latitude',
  `delivery_lng` decimal(11,8) DEFAULT NULL COMMENT 'Customer pinned longitude',
  `driver_lat` decimal(10,8) DEFAULT NULL COMMENT 'Driver current latitude',
  `driver_lng` decimal(11,8) DEFAULT NULL COMMENT 'Driver current longitude',
  `driver_updated_at` datetime DEFAULT NULL COMMENT 'Last driver location update',
  `phone` varchar(20) DEFAULT NULL,
  `invoice_path` varchar(255) DEFAULT NULL,
  `loyalty_points_earned` int(10) UNSIGNED DEFAULT 0,
  `loyalty_points_redeemed` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_number`, `status`, `payment_status`, `payment_method`, `subtotal`, `delivery_fee`, `total_amount`, `delivery_type`, `delivery_address`, `delivery_zone_id`, `customer_note`, `admin_note`, `created_at`, `updated_at`, `delivery_lat`, `delivery_lng`, `driver_lat`, `driver_lng`, `driver_updated_at`, `phone`, `invoice_path`, `loyalty_points_earned`, `loyalty_points_redeemed`) VALUES
(1, 1, 'TEST-1772013917', 'delivered', 'paid', '', 100.00, 0.00, 100.00, 'pickup', NULL, NULL, NULL, '', '2026-02-25 10:05:17', '2026-03-14 12:17:26', NULL, NULL, NULL, NULL, NULL, '0712345678', 'invoices/invoice_TEST-1772013917.pdf', 0, 0),
(2, 1, 'ORD-20260225-013961', 'delivered', 'paid', '', 1200.00, 0.00, 1200.00, 'pickup', '', NULL, 'will be picked', '', '2026-02-25 10:06:01', '2026-02-25 10:15:01', NULL, NULL, NULL, NULL, NULL, '0707881102', NULL, 0, 0),
(3, 1, 'ORD-20260225-027343', 'delivered', 'paid', '', 95000.00, 0.00, 95000.00, 'pickup', '', NULL, '', '', '2026-02-25 13:49:03', '2026-02-25 13:49:39', NULL, NULL, NULL, NULL, NULL, '0707881102', NULL, 0, 0),
(4, 1, 'ORD-20260227-210073', 'processing', 'paid', '', 3500.00, 0.00, 3500.00, 'pickup', '', NULL, '', NULL, '2026-02-27 16:34:33', '2026-03-10 11:27:48', NULL, NULL, NULL, NULL, NULL, '0707881102', NULL, 0, 0),
(5, 1, 'ORD-20260310-135149', 'processing', 'paid', '', 95000.00, 200.00, 95200.00, 'home_delivery', 'kikuyu', NULL, '', NULL, '2026-03-10 09:32:29', '2026-03-10 11:27:52', NULL, NULL, NULL, NULL, NULL, '0707881102', NULL, 0, 0),
(6, 1, 'ORD-20260314-490414', 'processing', 'paid', '', 250000.00, 0.00, 250000.00, 'pickup', '', NULL, '', NULL, '2026-03-14 12:13:34', '2026-03-14 12:30:18', NULL, NULL, NULL, NULL, NULL, '0707881102', NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `service_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` decimal(14,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `purpose` enum('password_reset','email_verification','login_verification') DEFAULT 'password_reset',
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_verifications`
--

INSERT INTO `otp_verifications` (`id`, `email`, `otp`, `purpose`, `attempts`, `created_at`, `expires_at`, `verified_at`) VALUES
(17, 'gordonogolo@gmail.com', '846942', 'password_reset', 0, '2026-03-14 09:49:20', '2026-03-14 07:59:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('mpesa','cash','bank') DEFAULT 'mpesa',
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `amount`, `method`, `transaction_id`, `receipt_number`, `status`, `payment_date`, `created_at`) VALUES
(5, 2, 1200.00, '', 'PAY_699EC9897739B', 'RCP20260225110601', 'completed', NULL, '2026-02-25 10:06:01'),
(6, 1, 3500.00, '', 'SVC_699ECA6705586', 'RCP20260225110943', 'completed', NULL, '2026-02-25 10:09:43'),
(7, 3, 95000.00, '', 'PAY_699EFDCF8AAFF', 'RCP20260225144903', 'pending', NULL, '2026-02-25 13:49:03'),
(8, 3, 95000.00, '', 'PAY_699EFDE8C4344', 'RCP20260225144928', 'completed', NULL, '2026-02-25 13:49:28'),
(9, 4, 3500.00, '', 'PAY_69A1C7994C204', 'RCP20260227173433', 'pending', NULL, '2026-02-27 16:34:33'),
(10, 5, 95200.00, '', 'PAY_69AFE52D1D9B0', 'RCP20260310103229', 'pending', NULL, '2026-03-10 09:32:29'),
(11, 4, 3500.00, '', 'PAY_69B000348B184', 'RCP20260310122748', 'completed', NULL, '2026-03-10 11:27:48'),
(12, 5, 95200.00, '', 'PAY_69B00038505D0', 'RCP20260310122752', 'completed', NULL, '2026-03-10 11:27:52'),
(13, 6, 250000.00, '', 'PAY_69B550EEB9D2E', 'RCP20260314131334', 'pending', NULL, '2026-03-14 12:13:34'),
(14, 6, 250000.00, '', 'PAY_69B554DA51671', 'RCP20260314133018', 'completed', NULL, '2026-03-14 12:30:18');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(250) NOT NULL,
  `short_description` text DEFAULT NULL,
  `full_description` mediumtext DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `stock` int(10) UNSIGNED DEFAULT 0,
  `low_stock_threshold` int(10) UNSIGNED DEFAULT 5,
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image` varchar(255) DEFAULT 'no-image.jpg',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `reorder_level` int(10) UNSIGNED DEFAULT 10,
  `is_featured` tinyint(1) DEFAULT 0,
  `brand` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `slug`, `short_description`, `full_description`, `price`, `compare_at_price`, `stock`, `low_stock_threshold`, `weight_kg`, `featured`, `visible`, `created_at`, `updated_at`, `image`, `is_active`, `reorder_level`, `is_featured`, `brand`) VALUES
(1, NULL, '', 'Test Router', 'test-router', NULL, NULL, 2500.00, NULL, 0, 5, NULL, 0, 0, '2026-02-14 10:13:06', '2026-03-14 12:24:13', 'router.jpg', 1, 10, 0, NULL),
(2, 1, 'HUB-TP-8P', 'TP-Link 8-Port 10/100 Mbps Desktop Switch', 'tp-link-8-port-switch', 'Basic unmanaged switch for small networks', NULL, 2800.00, NULL, 45, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'tp-link-8port.jpg', 1, 10, 0, NULL),
(3, 1, 'HUB-DLI-5P', 'D-Link 5-Port 10/100 Mbps Switch', 'd-link-5-port-switch', 'Compact desktop switch, plug-and-play', NULL, 2200.00, NULL, 60, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'dlink-5port.jpg', 1, 10, 0, NULL),
(4, 2, 'SWT-CIS-24P', 'Cisco Catalyst 9200L 24-Port Switch', 'cisco-catalyst-9200l-24p', 'Managed Gigabit switch with PoE', NULL, 95000.00, NULL, 5, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'cisco-9200.jpg', 1, 10, 0, NULL),
(5, 2, 'SWT-TP-48P', 'TP-Link TL-SG1048 48-Port Gigabit Switch', 'tp-link-tl-sg1048', 'Rack-mountable, high-speed switch', NULL, 32000.00, NULL, 12, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'tp-link-48port.jpg', 1, 10, 0, NULL),
(6, 3, 'RTR-TP-AX55', 'TP-Link Archer AX55 WiFi 6 Router', 'tp-link-archer-ax55', 'Dual-band AX3000 WiFi 6 router', NULL, 12500.00, NULL, 30, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'tp-link-ax55.jpg', 1, 10, 0, NULL),
(7, 3, 'RTR-CIS-RV340', 'Cisco RV340 Dual WAN VPN Router', 'cisco-rv340', 'Business router with dual WAN & VPN', NULL, 48000.00, NULL, 8, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'cisco-rv340.jpg', 1, 10, 0, NULL),
(8, 3, 'RTR-MIK-HAP', 'MikroTik hAP ac³ Dual Band Router', 'mikrotik-hap-ac3', 'RouterOS powered, 5 Gigabit ports', NULL, 18500.00, NULL, 15, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'mikrotik-hap.jpg', 1, 10, 0, NULL),
(9, 4, 'LAP-HP-840G8', 'HP EliteBook 840 G8 i5 11th Gen', 'hp-elitebook-840-g8', '14\" business laptop, i5, 16GB RAM, 512GB SSD', NULL, 98000.00, NULL, 10, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'hp-elitebook-840.jpg', 1, 10, 0, NULL),
(10, 4, 'LAP-DEL-3520', 'Dell Inspiron 15 3520 i5 12th Gen', 'dell-inspiron-3520', '15.6\" FHD, i5, 8GB RAM, 512GB SSD', NULL, 72000.00, NULL, 20, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'dell-inspiron-3520.jpg', 1, 10, 0, NULL),
(11, 5, 'PHN-SAM-A54', 'Samsung Galaxy A54 5G 256GB', 'samsung-galaxy-a54', '6.4\" AMOLED, Exynos 1380, 50MP camera', NULL, 48000.00, NULL, 25, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'samsung-a54.jpg', 1, 10, 0, NULL),
(12, 5, 'TAB-SAM-TAB-A8', 'Samsung Galaxy Tab A8 64GB', 'samsung-tab-a8', '10.5\" display, 4GB RAM, WiFi', NULL, 28000.00, NULL, 15, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'tab-a8.jpg', 1, 10, 0, NULL),
(13, 6, 'ACC-USB-128', 'SanDisk Ultra 128GB USB 3.0', 'sandisk-ultra-128gb', 'Fast USB flash drive, 150MB/s read', NULL, 1800.00, NULL, 80, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'sandisk-ultra.jpg', 1, 10, 0, NULL),
(14, 6, 'ACC-MOU-LOGI', 'Logitech M185 Wireless Mouse', 'logitech-m185', 'Reliable wireless mouse, 1000 DPI', NULL, 1200.00, NULL, 100, 5, NULL, 0, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57', 'logitech-m185.jpg', 1, 10, 0, NULL),
(15, 1, 'LAP-DELL-001', 'Dell XPS 15', 'dell-xps-15', '15.6\" FHD, Intel i7, 16GB RAM, 512GB SSD', NULL, 145000.00, NULL, 15, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(16, 1, 'LAP-HP-001', 'HP EliteBook 840', 'hp-elitebook-840', '14\" FHD, Intel i5, 8GB RAM, 256GB SSD', NULL, 85000.00, NULL, 20, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(17, 1, 'LAP-MAC-001', 'MacBook Air M2', 'macbook-air-m2', '13.6\" Retina, M2 chip, 8GB RAM', NULL, 135000.00, NULL, 10, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(18, 1, 'LAP-LEN-001', 'Lenovo ThinkPad E14', 'lenovo-thinkpad-e14', '14\" FHD, Intel i5, 16GB RAM, 512GB SSD', NULL, 78000.00, NULL, 18, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(19, 2, 'NET-TP-001', 'TP-Link Router AX73', 'tp-link-archer-ax73', 'AX5400 WiFi 6 Router', NULL, 18500.00, NULL, 25, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(20, 2, 'NET-TP-002', 'TP-Link Switch 8-Port', 'tp-link-tl-sg1008d', '8-Port Gigabit Switch', NULL, 4500.00, NULL, 30, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(21, 2, 'NET-CISC-001', 'Cisco Switch 48-Port', 'cisco-catalyst-2960', '48-Port Managed Switch', NULL, 85000.00, NULL, 8, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(22, 3, 'ACC-MOU-001', 'Logitech MX Master 3S', 'logitech-mx-master-3s', 'Wireless Ergonomic Mouse', NULL, 12500.00, NULL, 40, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(23, 3, 'ACC-MOU-002', 'HP Mouse X1000', 'hp-x1000-mouse', '3-Button Wired Mouse', NULL, 850.00, NULL, 100, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(24, 3, 'ACC-KB-001', 'Logitech K380 Keyboard', 'logitech-k380', 'Bluetooth Multi-Device Keyboard', NULL, 6500.00, NULL, 35, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(25, 3, 'ACC-CAB-001', 'Cat6 Cable 10m', 'cat6-cable-10m', 'Cat6 Ethernet Cable 10 Meter', NULL, 1200.00, NULL, 200, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(26, 3, 'ACC-CHG-001', 'Universal Charger 65W', 'universal-charger-65w', '65W Universal Laptop Charger', NULL, 3500.00, NULL, 50, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(27, 3, 'ACC-HUB-001', 'USB-C Hub 7-in-1', 'usb-c-hub-7in1', 'HDMI, USB-A, SD Card Reader', NULL, 4500.00, NULL, 45, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(28, 4, 'PHN-APP-001', 'iPhone 14', 'iphone-14', '128GB, A15 Bionic', NULL, 125000.00, NULL, 15, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(29, 4, 'PHN-SAM-001', 'Samsung Galaxy S23', 'samsung-galaxy-s23', '128GB, Snapdragon 8 Gen 2', NULL, 98000.00, NULL, 20, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(30, 4, 'TAB-APP-001', 'iPad 10th Gen', 'ipad-10th-gen', '64GB, 10.9\" Display', NULL, 65000.00, NULL, 10, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(31, 5, 'STR-SSD-001', 'Samsung 980 PRO 1TB', 'samsung-980-pro-1tb', 'NVMe M.2 SSD 1TB', NULL, 15000.00, NULL, 40, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(32, 5, 'STR-SSD-002', 'Kingston A400 480GB', 'kingston-a400-480gb', 'SATA SSD 480GB', NULL, 5500.00, NULL, 60, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(33, 5, 'STR-USB-001', 'SanDisk Ultra Flair 64GB', 'sandisk-ultra-flair-64gb', 'USB 3.0 Flash Drive 64GB', NULL, 1200.00, NULL, 100, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(34, 6, 'PRT-HP-001', 'HP LaserJet Pro MFP', 'hp-laserjet-pro-mfp', 'Monochrome Laser All-in-One', NULL, 65000.00, NULL, 8, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL),
(35, 6, 'PRT-EP-001', 'Epson L3250', 'epson-l3250', 'InkTank Printer with WiFi', NULL, 22000.00, NULL, 12, 5, NULL, 0, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43', 'no-image.jpg', 1, 10, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_comparisons`
--

CREATE TABLE `product_comparisons` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `alt_text` varchar(200) DEFAULT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `filename`, `alt_text`, `is_main`, `sort_order`, `created_at`) VALUES
(1, 15, 'lap-dell-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(2, 16, 'lap-hp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(3, 17, 'lap-mac-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(4, 18, 'lap-len-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(5, 19, 'net-tp-002.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(6, 20, 'net-tp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(7, 21, 'net-cisc-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(8, 22, 'acc-mou-001.png', NULL, 1, 0, '2026-02-27 13:33:11'),
(11, 25, 'acc-cab-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(12, 26, 'acc-chg-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(13, 27, 'acc-hub-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(14, 28, 'phn-app-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(15, 29, 'phn-sam-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(16, 30, 'tab-app-001.png', NULL, 1, 0, '2026-02-27 13:33:11'),
(17, 32, 'str-ssd-002.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(18, 33, 'str-usb-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(19, 34, 'prt-hp-001.png', NULL, 1, 0, '2026-02-27 13:33:11'),
(20, 35, 'prt-ep-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(21, 1, 'net-tp-002.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(22, 2, 'net-tp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(23, 3, 'net-dli-001.png', NULL, 1, 0, '2026-02-27 13:33:11'),
(25, 7, 'net-cisc-002.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(26, 8, 'net-mik-001.webp', NULL, 1, 0, '2026-02-27 13:33:11'),
(27, 9, 'lap-hp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(28, 10, 'lap-dell-002.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(29, 11, 'phn-sam-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(30, 12, 'tab-sam-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(31, 13, 'str-usb-001.jpg', NULL, 1, 0, '2026-02-27 13:33:11'),
(32, 14, 'acc-mou-002.png', NULL, 1, 0, '2026-02-27 13:33:11'),
(33, 15, 'lap-dell-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(34, 16, 'lap-hp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(35, 17, 'lap-mac-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(36, 18, 'lap-len-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(37, 19, 'net-tp-002.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(38, 20, 'net-tp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(39, 21, 'net-cisc-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(40, 22, 'acc-mou-001.png', NULL, 1, 0, '2026-02-27 13:33:26'),
(41, 23, 'acc-mou-002.png', NULL, 1, 0, '2026-02-27 13:33:26'),
(42, 24, 'acc-kb-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(43, 25, 'acc-cab-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(44, 26, 'acc-chg-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(45, 27, 'acc-hub-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(46, 28, 'phn-app-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(47, 29, 'phn-sam-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(48, 30, 'tab-app-001.png', NULL, 1, 0, '2026-02-27 13:33:26'),
(49, 32, 'str-ssd-002.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(50, 33, 'str-usb-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(51, 34, 'prt-hp-001.png', NULL, 1, 0, '2026-02-27 13:33:26'),
(52, 35, 'prt-ep-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(53, 1, 'net-tp-002.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(54, 2, 'net-tp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(55, 3, 'net-dli-001.png', NULL, 1, 0, '2026-02-27 13:33:26'),
(56, 6, 'net-tp-002.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(57, 7, 'net-cisc-002.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(58, 8, 'net-mik-001.webp', NULL, 1, 0, '2026-02-27 13:33:26'),
(59, 9, 'lap-hp-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(60, 10, 'lap-dell-002.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(61, 11, 'phn-sam-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(62, 12, 'tab-sam-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(63, 13, 'str-usb-001.jpg', NULL, 1, 0, '2026-02-27 13:33:26'),
(64, 14, 'acc-mou-002.png', NULL, 1, 0, '2026-02-27 13:33:26'),
(65, 4, 'net-cisc-001.jpg', NULL, 1, 0, '2026-02-27 14:35:13'),
(66, 5, 'net-tp-003.jpg', NULL, 1, 0, '2026-02-27 14:35:13'),
(67, 22, 'acc-mou-001.png', NULL, 1, 0, '2026-02-27 14:35:13'),
(68, 31, 'str-ssd-002.jpg', NULL, 1, 0, '2026-02-27 14:40:09');

-- --------------------------------------------------------

--
-- Table structure for table `product_interactions`
--

CREATE TABLE `product_interactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `interaction_type` enum('view','add_to_cart','purchase','wishlist','compare','review') NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `price_at_time` decimal(10,2) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_recommendations`
--

CREATE TABLE `product_recommendations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `recommendation_type` enum('similar','frequently_bought','popular','personalized','category') NOT NULL,
  `score` decimal(5,4) DEFAULT 0.0000,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_search`
--

CREATE TABLE `product_search` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `search_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_notifications`
--

CREATE TABLE `push_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `icon` varchar(500) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `badge` varchar(500) DEFAULT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `requires_interaction` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_notification_recipients`
--

CREATE TABLE `push_notification_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','sent','delivered','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(200) NOT NULL,
  `auth` varchar(100) NOT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recently_viewed`
--

CREATE TABLE `recently_viewed` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `payment_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `refund_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_logs`
--

CREATE TABLE `report_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `record_count` int(10) UNSIGNED DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_schedules`
--

CREATE TABLE `report_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_type` enum('daily_sales','weekly_summary','monthly_analysis','low_stock','top_products') NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL,
  `day_of_month` tinyint(4) DEFAULT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_run` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report_schedules`
--

INSERT INTO `report_schedules` (`id`, `report_type`, `frequency`, `day_of_week`, `day_of_month`, `recipients`, `is_active`, `last_run`, `created_at`) VALUES
(1, 'daily_sales', 'daily', NULL, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:06:18'),
(2, 'weekly_summary', 'weekly', 1, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:06:18'),
(3, 'low_stock', 'daily', NULL, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:06:18'),
(4, 'top_products', 'weekly', 1, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:06:18'),
(5, 'daily_sales', 'daily', NULL, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:08:41'),
(6, 'weekly_summary', 'weekly', 1, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:08:41'),
(7, 'low_stock', 'daily', NULL, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:08:41'),
(8, 'top_products', 'weekly', 1, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:08:41'),
(9, 'daily_sales', 'daily', NULL, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:19:34'),
(10, 'low_stock', 'daily', NULL, NULL, '[\"admin@outsourcedtechnologies.co.ke\"]', 1, NULL, '2026-03-14 12:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `slug`, `price`, `description`, `duration_minutes`, `visible`, `created_at`, `updated_at`) VALUES
(1, 'Phone Diagnostics', 'phone-diagnostics', 1000.00, 'Full hardware & software checkup + report', 45, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57'),
(2, 'Laptop Diagnostics', 'laptop-diagnostics', 1500.00, 'Complete laptop health check + diagnostics report', 60, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57'),
(3, 'Phone Screen Replacement', 'phone-screen-replacement', 4500.00, 'Original or high-quality screen replacement', 90, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57'),
(4, 'Laptop Battery Replacement', 'laptop-battery-replacement', 6500.00, 'Genuine or compatible battery swap', 60, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57'),
(5, 'ISP Home Installation', 'isp-home-installation', 3500.00, 'Router setup, WiFi configuration, speed test', 120, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57'),
(6, 'Network Troubleshooting', 'network-troubleshooting', 2500.00, 'Fix slow internet, dropouts, configuration issues', 90, 1, '2026-02-15 08:41:57', '2026-02-15 08:41:57'),
(7, 'Screen Replacement', 'screen-replacement', 5000.00, 'LCD/LED screen replacement for laptops', 120, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(8, 'Battery Replacement', 'battery-replacement', 3500.00, 'Original battery replacement', 45, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(9, 'Keyboard Repair', 'keyboard-repair', 2500.00, 'Keyboard cleaning or replacement', 90, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(10, 'Virus Removal', 'virus-removal', 2000.00, 'Malware and virus removal', 120, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(11, 'Data Recovery', 'data-recovery', 5000.00, 'Recover data from damaged drives', 180, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(12, 'Network Setup', 'network-setup', 3500.00, 'Router and network configuration', 90, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(13, 'ISP Installation', 'isp-installation', 2500.00, 'Internet service provider setup', 60, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(14, 'Phone Repair', 'phone-repair', 2000.00, 'Screen and battery replacement', 60, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43'),
(15, 'Tablet Repair', 'tablet-repair', 3500.00, 'Screen and component repair', 90, 1, '2026-02-15 09:05:43', '2026-02-15 09:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `service_bookings`
--

CREATE TABLE `service_bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `service_id` bigint(20) UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `calendar_blocked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_bookings`
--

INSERT INTO `service_bookings` (`id`, `user_id`, `service_id`, `booking_date`, `booking_time`, `status`, `notes`, `admin_notes`, `created_at`, `phone`, `payment_status`, `reminder_sent`, `cancelled_at`, `calendar_blocked`) VALUES
(1, 1, 8, '2026-02-26', '14:00:00', 'confirmed', '', NULL, '2026-02-25 10:09:43', '0707881102', 'paid', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `similar_products`
--

CREATE TABLE `similar_products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_a_id` int(10) UNSIGNED NOT NULL,
  `product_b_id` int(10) UNSIGNED NOT NULL,
  `similarity_score` decimal(5,4) NOT NULL,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_log`
--

CREATE TABLE `sms_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','sent','delivered','failed') DEFAULT 'pending',
  `gateway_response` text DEFAULT NULL,
  `cost` decimal(10,4) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_subscriptions`
--

CREATE TABLE `sms_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `notifications_order_updates` tinyint(1) DEFAULT 1,
  `notifications_promotions` tinyint(1) DEFAULT 0,
  `notifications_low_stock` tinyint(1) DEFAULT 0,
  `notifications_delivery_updates` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

CREATE TABLE `sms_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `message_template` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_templates`
--

INSERT INTO `sms_templates` (`id`, `name`, `event_type`, `message_template`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Order Confirmation', 'order_confirmed', 'Thank you for your order! Your order #{order_id} has been confirmed. Total: KES {total}. Track at {tracking_url}', 1, '2026-02-27 17:06:03', '2026-02-27 17:06:03'),
(2, 'Order Shipped', 'order_shipped', 'Your order #{order_id} has been shipped! Delivery expected within {delivery_days} days. Track: {tracking_url}', 1, '2026-02-27 17:06:03', '2026-02-27 17:06:03'),
(3, 'Order Delivered', 'order_delivered', 'Your order #{order_id} has been delivered! Thank you for shopping with us. Leave a review: {review_url}', 1, '2026-02-27 17:06:03', '2026-02-27 17:06:03'),
(4, 'Payment Received', 'payment_received', 'Payment of KES {amount} received for order #{order_id}. Thank you!', 1, '2026-02-27 17:06:03', '2026-02-27 17:06:03'),
(5, 'Low Stock Alert', 'low_stock', 'Alert: {product_name} is running low ({quantity} left). Consider restocking.', 1, '2026-02-27 17:06:03', '2026-02-27 17:06:03'),
(6, 'Promotional', 'promotion', '{message}', 1, '2026-02-27 17:06:03', '2026-02-27 17:06:03'),
(11, 'Booking Reminder (24h)', 'booking_reminder_24h', 'Reminder: Your {service_name} booking is tomorrow at {time}. See you then! - Outsourced Technologies', 1, '2026-03-14 12:08:41', '2026-03-14 12:08:41'),
(12, 'Booking Reminder (1h)', 'booking_reminder_1h', 'Your {service_name} appointment is in 1 hour. We look forward to seeing you! - Outsourced Technologies', 1, '2026-03-14 12:08:41', '2026-03-14 12:08:41'),
(13, 'Abandoned Cart', 'abandoned_cart', 'You left {items} in your cart! Complete your order of KES {total}: {cart_url}', 1, '2026-03-14 12:08:41', '2026-03-14 12:08:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `address`, `loyalty_points`, `created_at`, `last_login`, `active`, `is_verified`, `verified_at`) VALUES
(1, 'GGS', 'gordonogolo@gmail.com', '$2y$10$d1FqKFCla/fZDr9T89Ey4.UnOD3Hlck4A6I48QDwoT2KmvZZ3pijG', 'Gordon Ogolo', '0707881102', NULL, 2310, '2026-02-15 08:18:51', NULL, 1, 0, NULL),
(2, 'couragee', 'elvismbuvi55@gmail.com', '$2y$10$4zK8dyOss2t4UUN49gE6XuRPLMwHQwX.Jd.0VZPkY8YHh4nXXwFpS', 'cow chicken', '0717319666', NULL, 3000, '2026-02-16 09:52:22', NULL, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(2, 1, 4, '2026-03-07 13:40:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `abandoned_carts`
--
ALTER TABLE `abandoned_carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_recovered` (`recovered_at`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_recorded_at` (`recorded_at`);

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_type` (`alert_type`),
  ADD KEY `idx_resolved` (`is_resolved`);

--
-- Indexes for table `loyalty_tiers`
--
ALTER TABLE `loyalty_tiers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_event` (`event`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `delivery_zone_id` (`delivery_zone_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_otp` (`otp`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_comparisons`
--
ALTER TABLE `product_comparisons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_interactions`
--
ALTER TABLE `product_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_type` (`interaction_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `product_recommendations`
--
ALTER TABLE `product_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_product_type` (`user_id`,`product_id`,`recommendation_type`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`recommendation_type`),
  ADD KEY `idx_generated` (`generated_at`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `product_search`
--
ALTER TABLE `product_search`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `push_notifications`
--
ALTER TABLE `push_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `push_notification_recipients`
--
ALTER TABLE `push_notification_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification` (`notification_id`),
  ADD KEY `idx_subscription` (`subscription_id`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_endpoint` (`endpoint`(255));

--
-- Indexes for table `recently_viewed`
--
ALTER TABLE `recently_viewed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_viewed` (`user_id`,`viewed_at`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `report_logs`
--
ALTER TABLE `report_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`report_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `report_schedules`
--
ALTER TABLE `report_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`report_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `service_bookings`
--
ALTER TABLE `service_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `similar_products`
--
ALTER TABLE `similar_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_pair` (`product_a_id`,`product_b_id`),
  ADD KEY `idx_product_a` (`product_a_id`),
  ADD KEY `idx_product_b` (`product_b_id`);

--
-- Indexes for table `sms_log`
--
ALTER TABLE `sms_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `sms_subscriptions`
--
ALTER TABLE `sms_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user` (`user_id`),
  ADD KEY `idx_phone` (`phone_number`);

--
-- Indexes for table `sms_templates`
--
ALTER TABLE `sms_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_type` (`event_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist_item` (`user_id`,`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `abandoned_carts`
--
ALTER TABLE `abandoned_carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty_tiers`
--
ALTER TABLE `loyalty_tiers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `product_comparisons`
--
ALTER TABLE `product_comparisons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `product_interactions`
--
ALTER TABLE `product_interactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_recommendations`
--
ALTER TABLE `product_recommendations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_search`
--
ALTER TABLE `product_search`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_notifications`
--
ALTER TABLE `push_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_notification_recipients`
--
ALTER TABLE `push_notification_recipients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recently_viewed`
--
ALTER TABLE `recently_viewed`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_logs`
--
ALTER TABLE `report_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_schedules`
--
ALTER TABLE `report_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `service_bookings`
--
ALTER TABLE `service_bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `similar_products`
--
ALTER TABLE `similar_products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_log`
--
ALTER TABLE `sms_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_subscriptions`
--
ALTER TABLE `sms_subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_templates`
--
ALTER TABLE `sms_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_search`
--
ALTER TABLE `product_search`
  ADD CONSTRAINT `product_search_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_bookings`
--
ALTER TABLE `service_bookings`
  ADD CONSTRAINT `service_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_bookings_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

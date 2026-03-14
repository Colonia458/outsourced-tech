# Quick Start Guide - TechHub Pro

Get your e-commerce platform running in 5 minutes!

## What You Have

A complete e-commerce platform with:
- ✅ Product catalog (networking, computers, phones)
- ✅ Service booking system (repairs, diagnostics, ISP)
- ✅ M-Pesa payment integration
- ✅ Delivery options with zones
- ✅ Loyalty rewards program
- ✅ Customer support chatbot
- ✅ Admin management panel
- ✅ Shopping cart and checkout
- ✅ Supabase database (already configured!)
- ✅ **System health monitoring** (NEW)
- ✅ **Automated backups** (NEW)
- ✅ **Scheduled maintenance** (NEW)

## 5-Minute Setup

### Step 1: Add Sample Data (1 minute)

1. Go to [Supabase Dashboard](https://supabase.com/dashboard)
2. Open your project
3. Click "SQL Editor" in the left menu
4. Copy everything from `database/outsourced_tech.sql`
5. Paste it into the SQL editor
6. Click "Run"

This adds:
- 11 sample products
- 11 sample services
- Categories
- Admin account (username: `admin`, password: `admin123`)

### Step 2: Enable Activity Logging (optional but recommended)

1. In SQL Editor, copy and run:
```sql
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_action (action),
    INDEX idx_user_id (user_id)
);
```

### Step 3: Configure Database Connection (2 minutes)

1. Open `api/config.php`
2. Go to line 15
3. Replace `[YOUR-PASSWORD]` with your Supabase database password
   - Get it from: Supabase Dashboard → Settings → Database

### Step 4: Test Locally or Deploy (2 minutes)

**Option A: Test Locally**

```bash
# If you have PHP installed
php -S localhost:8000

# Visit http://localhost:8000
```

**Option B: Deploy to Server**

- Upload all files to your web hosting
- Make sure server has PHP 7.4+
- See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed instructions

### Step 5: Login to Admin Panel

1. Go to `/admin/login.php`
2. Username: `admin`
3. Password: `admin123`
4. **IMMEDIATELY** change this password!

### Step 6: Customize Your Store

1. Add your real products (or keep samples)
2. Update contact information in `index.html` footer
3. Add product images (or use the Pexels stock photos included)
4. Configure M-Pesa for payments (see below)

## New System Features (6-Month Stability)

### System Health Check
- Visit `/api/health.php` to check system status
- Shows PHP version, disk space, extensions, database status
- Works even without database connection

### Admin System Status Dashboard
- Visit `/admin/system-status.php`
- View PHP info, disk usage, extensions
- See log files status

### Log Viewer
- Visit `/admin/logs.php`
- View error logs, activity logs, email logs
- Filter by log type and number of lines

## M-Pesa Setup (For Payments)

### Sandbox Testing (Recommended First)

1. Register at [Daraja API Portal](https://developer.safaricom.co.ke/)
2. Create a test app
3. Get credentials:
   - Consumer Key
   - Consumer Secret
   - Shortcode (usually 174379 for sandbox)
   - Passkey
4. Open `api/mpesa.php`
5. Update the credentials in the constructor (lines 12-17)
6. Set `environment` to `'sandbox'`

**Test Phone Number:** 254708374149

### Production (For Live Payments)

1. Apply for production access on Daraja
2. Update credentials in `api/mpesa.php`
3. Set environment to `'production'`
4. Set callback URL to your domain: `https://yourdomain.com/api/mpesa.php`
5. **Must use HTTPS** (M-Pesa requirement)

## What's Already Configured

✅ **Supabase Database**
- URL: `https://xajtokukmeeyfgditwns.supabase.co`
- Connection configured in `api/config.php` and `js/config.js`

✅ **Delivery Zones**
- Free delivery (0-5km)
- KES 200 (5-15km)
- KES 500 (15-30km)
- KES 1000 (30km+)

✅ **Loyalty Program**
- Bronze: 0 points (0% discount)
- Silver: 500 points (5% discount)
- Gold: 1500 points (10% discount)
- Platinum: 3000 points (15% discount)
- Diamond: 5000 points (20% discount)

## Testing the Platform

### Test as Customer

1. Open homepage
2. Click "Sign Up" and create account
3. Browse products
4. Add items to cart
5. Proceed to checkout
6. Select delivery option
7. Enter M-Pesa number (use sandbox test number if testing)
8. Complete payment

### Test as Admin

1. Go to `/admin/login.php`
2. Login with admin credentials
3. View dashboard
4. Manage products, services, orders
5. Update order statuses

### Test System Monitoring

1. Visit `/api/health.php` for JSON health status
2. Visit `/admin/system-status.php` for visual dashboard
3. Visit `/admin/logs.php` to view logs

## Cron Jobs (Optional but Recommended)

For automated maintenance, set up cron jobs:

```bash
# Run every 5 minutes
*/5 * * * * curl -s https://yourdomain.com/api/cron.php?key=YOUR_KEY

# Run daily for backup
0 0 * * * curl -s https://yourdomain.com/api/backup.php?key=YOUR_KEY
```

## Common Issues & Quick Fixes

### Products Not Showing
**Fix:** Run the SQL from `database/outsourced_tech.sql` in Supabase

### Database Connection Error
**Fix:** Update password in `api/config.php` line 15

### M-Pesa Not Working
**Fix:**
- Use sandbox environment first
- Check credentials in `api/mpesa.php`
- Ensure phone number format is 254XXXXXXXXX

### Admin Login Not Working
**Fix:**
- Default credentials: admin/admin123
- Clear browser cookies
- Verify admin user exists in database

### Health Check Shows Database Unavailable
**Fix:** Update database password in `api/config.php`

## File Structure Overview

```
├── index.html          # Main store page
├── css/style.css       # All styling
├── js/
│   ├── config.js       # Frontend config (Supabase)
│   └── app.js          # Frontend logic
├── api/                # Backend PHP files
│   ├── config.php      # Database connection
│   ├── health.php      # Health check (NEW)
│   ├── backup.php      # Automated backup (NEW)
│   ├── cron.php        # Scheduled tasks (NEW)
│   ├── auth.php        # User authentication
│   ├── products.php    # Products API
│   ├── services.php    # Services API
│   ├── orders.php      # Orders API
│   └── mpesa.php      # M-Pesa integration
├── src/
│   ├── email.php       # Email system (NEW)
│   ├── activity.php    # Activity logging (NEW)
│   └── ratelimit.php  # Rate limiting (NEW)
├── admin/              # Admin panel
│   ├── login.php
│   ├── dashboard.php
│   ├── system-status.php  # System monitoring (NEW)
│   └── logs.php          # Log viewer (NEW)
└── database/
    ├── outsourced_tech.sql
    └── activity_logs.sql  # Activity logging (NEW)
```

## Next Steps

1. ✅ Add your real products and services
2. ✅ Update store information and branding
3. ✅ Configure M-Pesa for payments
4. ✅ Set up SSL certificate (required for production)
5. ✅ Test complete purchase flow
6. ✅ Configure cron jobs for maintenance (optional)
7. ✅ Launch and start selling!

## Key Features to Explore

### Customer Features
- **Product Catalog**: Browse by category
- **Service Booking**: Schedule appointments
- **Shopping Cart**: Manage orders
- **Delivery Options**: Free or paid delivery, pickup
- **Loyalty Rewards**: Earn points, unlock badges
- **Chatbot**: Customer support assistant

### Admin Features
- **Dashboard**: Sales overview
- **Product Management**: Add/edit inventory
- **Order Management**: Track and fulfill orders
- **User Management**: View customers
- **Service Bookings**: Manage appointments
- **System Monitoring**: Health checks, logs (NEW)

## Important Notes

⚠️ **Security:**
- Change default admin password immediately
- Never commit `.env` files to Git
- Keep API keys secure
- Use HTTPS in production

⚠️ **Database:**
- Update password in api/config.php
- Run activity_logs.sql for tracking

💡 **Performance:**
- Sample data uses Pexels stock images
- Add your own product images for faster loading
- Consider CDN for static assets in production

🚀 **Ready to Go:**
Your platform is production-ready! Just add:
1. Real products/services
2. Your branding
3. M-Pesa credentials
4. SSL certificate
5. Database password

## Need Help?

- 📖 Full documentation: [README.md](README.md)
- 🚀 Deployment guide: [DEPLOYMENT.md](DEPLOYMENT.md)
- 💳 M-Pesa setup: See M-Pesa section above
- 🗄️ Database: Everything's in Supabase
- 🔧 System monitoring: /api/health.php

---

**Your store is ready to launch!** 🎉

Just configure M-Pesa credentials and you're good to go!

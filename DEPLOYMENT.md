# Deployment Guide - TechHub Pro E-Commerce Platform

This guide will help you deploy your TechHub Pro e-commerce platform to a production server.

## Prerequisites

Before deploying, ensure you have:

- [ ] A web hosting service with PHP 7.4+ support (shared hosting, VPS, or dedicated server)
- [ ] SSL certificate (required for M-Pesa integration)
- [ ] Supabase account with your database already set up
- [ ] M-Pesa Daraja API credentials (for production or sandbox testing)
- [ ] FTP/SFTP access to your server or SSH access
- [ ] Domain name pointing to your server

## Step-by-Step Deployment

### 1. Prepare Your Database

Your Supabase database is already configured with the URL: `https://xajtokukmeeyfgditwns.supabase.co`

**To add sample data:**

1. Go to your Supabase project dashboard
2. Navigate to SQL Editor
3. Copy and paste the contents of `database/outsourced_tech.sql`
4. Click "Run" to populate your database

**To enable activity logging, also run:**

1. Copy the contents of `database/activity_logs.sql`
2. Run it in the SQL Editor

**Important:** The sample data includes a default admin account:
- Username: `admin`
- Password: `admin123`

**⚠️ SECURITY WARNING:** Change this password immediately after first login!

### 2. Configure Environment Variables

The platform is already configured with your Supabase credentials. However, you need to:

1. **Get your database password:**
   - Go to Supabase Dashboard → Settings → Database
   - Copy your database password
   - Update `api/config.php` line 15, replace `[YOUR-PASSWORD]` with your actual password

2. **Set up M-Pesa credentials:**
   - Open `api/mpesa.php`
   - Update the M-Pesa credentials in the constructor or set environment variables:
     - `MPESA_CONSUMER_KEY`
     - `MPESA_CONSUMER_SECRET`
     - `MPESA_SHORTCODE`
     - `MPESA_PASSKEY`
     - `MPESA_CALLBACK_URL`
     - `MPESA_ENVIRONMENT` (set to 'sandbox' for testing or 'production' for live)

3. **Set up Email (optional):**
   - Configure SMTP credentials in `src/email.php` or via environment variables:
     - `MAIL_DRIVER=smtp`
     - `MAIL_HOST=smtp.gmail.com`
     - `MAIL_PORT=587`
     - `MAIL_USERNAME=your-email@gmail.com`
     - `MAIL_PASSWORD=your-app-password`

4. **Set up Cron Keys:**
   - Choose secure keys for cron jobs:
     - `CRON_SECRET_KEY=your-secret-key`
     - `BACKUP_SECRET_KEY=your-backup-key`

### 3. Upload Files to Server

**Option A: Using FTP/SFTP Client (e.g., FileZilla)**

1. Connect to your server using FTP/SFTP credentials
2. Navigate to your web root directory (usually `public_html`, `www`, or `htdocs`)
3. Upload all project files to this directory
4. Ensure proper file permissions:
   - Directories: 755
   - Files: 644
   - PHP files: 644

**Option B: Using SSH and Git**

```bash
# Connect to your server
ssh user@your-server.com

# Navigate to web root
cd /var/www/html  # or your web root directory

# Clone your repository (if using Git)
git clone your-repository-url .

# Or upload via rsync
rsync -avz --progress /local/path/ user@server:/remote/path/
```

### 4. Configure Web Server

**For Apache (.htaccess is already configured)**

The `.htaccess` file is already set up. Just ensure:

1. `mod_rewrite` is enabled
2. `mod_headers` is enabled (for CORS)
3. `AllowOverride All` is set in your Apache config

**For Nginx**

Add this to your server block:

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name yourdomain.com;

    root /path/to/project;
    index index.html index.php;

    # SSL Configuration (required for M-Pesa)
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Enable CORS for API
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type, Authorization";
}
```

### 5. SSL Certificate Setup

M-Pesa requires HTTPS. Set up SSL using:

**Option A: Let's Encrypt (Free)**

```bash
# Install certbot
sudo apt install certbot python3-certbot-apache  # For Apache
# or
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Get certificate
sudo certbot --apache -d yourdomain.com  # For Apache
# or
sudo certbot --nginx -d yourdomain.com   # For Nginx
```

**Option B: Commercial SSL**

Purchase SSL from your hosting provider and follow their installation instructions.

### 6. Test the Installation

1. **Visit your website:** `https://yourdomain.com`
2. **Test customer features:**
   - Browse products and services
   - Create an account
   - Add items to cart
   - Test checkout (use sandbox mode for M-Pesa)

3. **Test admin panel:** `https://yourdomain.com/admin/dashboard.php`
   - Login with default credentials
   - **IMMEDIATELY change the password**
   - Add/edit products
   - Manage orders

4. **Test system monitoring:**
   - Visit `/api/health.php` for health check
   - Visit `/admin/system-status.php` for system status
   - Visit `/admin/logs.php` for log viewer

### 7. M-Pesa Configuration

**For Testing (Sandbox):**

1. Go to https://developer.safaricom.co.ke/
2. Create a test app
3. Use sandbox credentials in `api/mpesa.php`
4. Test with sandbox phone number: `254708374149`

**For Production:**

1. Apply for Daraja API production access
2. Get your production credentials
3. Update `MPESA_ENVIRONMENT` to 'production'
4. Set your production shortcode and credentials
5. Configure your production callback URL: `https://yourdomain.com/api/mpesa.php`
6. Ensure callback URL is publicly accessible and uses HTTPS

### 8. Configure Cron Jobs

For automated maintenance and backups, configure cron jobs:

```bash
# Run every 5 minutes for maintenance tasks
*/5 * * * * curl -s https://yourdomain.com/api/cron.php?key=YOUR_CRON_KEY

# Run daily at midnight for backup
0 0 * * * curl -s https://yourdomain.com/api/backup.php?key=YOUR_BACKUP_KEY
```

**Cron tasks include:**
- Clean old chatbot conversations (30+ days)
- Low stock product notifications
- Cancel unpaid orders (24+ hours)
- Clean expired password reset tokens
- Clean old log files

### 9. Final Security Checklist

- [ ] Changed default admin password
- [ ] Updated database password in `api/config.php`
- [ ] SSL certificate is installed and working
- [ ] M-Pesa callback URL is HTTPS
- [ ] Tested all payment flows
- [ ] Set proper file permissions (no 777 permissions)
- [ ] `.env` file is not accessible via web (if using one)
- [ ] Removed or secured `setup/` directory
- [ ] Tested on multiple devices and browsers
- [ ] Set up database backups in Supabase
- [ ] Configured error logging (not showing errors to users)
- [ ] Configured cron jobs for maintenance

### 10. Post-Deployment Tasks

**Immediate:**
1. Change admin password
2. Add your actual products and services
3. Update contact information in footer
4. Update store policies and terms
5. Test complete purchase flow

**Ongoing:**
1. Monitor orders daily
2. Respond to service bookings
3. Update inventory levels
4. Review and fulfill orders
5. Backup database regularly
6. Monitor M-Pesa transactions
7. Update loyalty tiers as needed
8. Check system health via /api/health.php

## Troubleshooting Common Issues

### Issue: Database Connection Failed

**Solution:**
- Verify database password in `api/config.php`
- Check Supabase project is active
- Ensure server can connect to external databases

### Issue: M-Pesa STK Push Not Working

**Solutions:**
- Verify credentials are correct
- Check callback URL is HTTPS and publicly accessible
- Ensure phone number format is correct (254XXXXXXXXX)
- Test with sandbox first
- Check M-Pesa API logs in Daraja portal

### Issue: Products Not Displaying

**Solutions:**
- Run database SQL in Supabase
- Check database connection
- Verify API endpoints are accessible
- Check browser console for errors

### Issue: Admin Login Not Working

**Solutions:**
- Verify admin account exists in database
- Check password is correct (default: admin123)
- Clear browser cookies
- Check PHP session configuration

### Issue: Health Check Shows Database Unavailable

**Solutions:**
- Verify database password in api/config.php
- Check Supabase credentials are correct
- Ensure database is accessible from server

## Server Requirements

**Minimum:**
- PHP 7.4 or higher
- PostgreSQL client libraries for PHP
- 512MB RAM
- 5GB storage

**Recommended:**
- PHP 8.0 or higher
- 1GB+ RAM
- 10GB+ storage
- Dedicated or VPS hosting
- Regular backups

## System Monitoring

### Health Check Endpoint
Access `/api/health.php` to check:
- PHP version and extensions
- Disk space
- Database connection
- Log directory status

### Admin Monitoring Dashboard
Access `/admin/system-status.php` to view:
- System health
- PHP configuration
- Disk usage
- Log files
- Database status

### Log Viewer
Access `/admin/logs.php` to view:
- Error logs
- Activity logs
- Email logs
- Security logs

## Support

For deployment issues:
1. Check error logs in your hosting panel
2. Review Supabase logs
3. Check M-Pesa API logs in Daraja portal
4. Verify all credentials are correct

## Production Optimization

Once deployed, consider:
1. Enable PHP OPcache for better performance
2. Set up CDN for static assets
3. Enable gzip compression
4. Implement caching strategies
5. Monitor server resources
6. Set up automated backups
7. Configure monitoring and alerts

## Backup Strategy

**Database Backups:**
- Supabase provides automatic backups
- Additional manual backups recommended weekly
- Export can be done via Supabase Dashboard
- Automated backups via `/api/backup.php`

**File Backups:**
- Backup entire project directory monthly
- Keep copies of configuration files
- Store backups in separate location

---

## Quick Start Checklist

For a rapid deployment:

1. ✅ Upload all files to server
2. ✅ Update database password in `api/config.php`
3. ✅ Run database SQL files in Supabase
4. ✅ Set up SSL certificate
5. ✅ Configure M-Pesa credentials
6. ✅ Test website access
7. ✅ Login to admin panel (admin/admin123)
8. ✅ Change admin password immediately
9. ✅ Test full purchase flow
10. ✅ Add your products and services
11. ✅ Configure cron jobs for maintenance
12. ✅ Test health check endpoint

Your TechHub Pro e-commerce platform is now live! 🚀

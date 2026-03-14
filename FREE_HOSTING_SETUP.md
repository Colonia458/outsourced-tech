# Free Hosting Setup Guide for PHP/Supabase E-Commerce App

## Recommended: Render (render.com)

**Render** is the best free hosting option for your application because:
- ✅ Free tier available (750 hours/month)
- ✅ Native PHP support via official PHP buildpack
- ✅ Easy connection to external PostgreSQL (Supabase)
- ✅ Free automatic SSL certificates
- ✅ SMTP support via environment variables
- ✅ Perfect for M-Pesa callbacks (requires HTTPS)

---

## Step 1: Prepare Your Application

### 1.1 Update config.php for Supabase PostgreSQL

Modify [`src/config.php`](src/config.php:1) to support Supabase:

```php
<?php
// src/config.php - Updated for Supabase PostgreSQL

define('APP_NAME', 'Outsourced Technologies');
define('BASE_URL', 'https://your-app-name.onrender.com/public/');  // ← Change this

define('DB_HOST', getenv('DB_HOST') ?: 'your-supabase-host');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres.your-project');
define('DB_PASS', getenv('DB_PASS') ?: 'your-supabase-password');

define('MPESA_ENV', 'live'); // Change to 'live' for production
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY'));
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET'));
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE'));
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY'));
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: 'https://your-app.onrender.com/api/mpesa/callback.php');

session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

// PostgreSQL Connection via Supabase
try {
    $db = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $db->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

$pdo = $db;
```

### 1.2 Create composer.json

Create [`composer.json`](composer.json) in the root directory:

```json
{
    "name": "outsourced/tech",
    "description": "Outsourced Technologies E-Commerce",
    "type": "project",
    "require": {
        "php": "^7.4|^8.0"
    },
    "require-dev": {
        "phpmailer/phpmailer": "^6.8"
    },
    "autoload": {
        "classmap": ["src/", "controllers/", "api/"]
    }
}
```

### 1.3 Create render.yaml

Create [`render.yaml`](render.yaml) for deployment configuration:

```yaml
services:
  - type: web
    name: outsourced-tech
    env: php
    buildCommand: |
      curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
      composer install --no-dev --optimize-autoloader
    startCommand: |
      cp -r . /var/www/html
      chmod -R 755 /var/www/html/public
    envVars:
      - key: APP_ENV
        value: production
      - key: LOG_ERRORS
        value: true
```

---

## Step 2: Deploy to Render

### 2.1 Push Code to GitHub

```bash
git init
git add .
git commit -m "Prepare for production deployment"
git branch -M main
git remote add origin https://github.com/yourusername/outsourced-tech.git
git push -u origin main
```

### 2.2 Create Render Account

1. Go to [render.com](https://render.com) and sign up with GitHub
2. Click "New +" → "Web Service"

### 2.3 Configure Web Service

| Setting | Value |
|---------|-------|
| Name | `outsourced-tech` |
| Region | `Frankfurt (EU)` or `Oregon (US)` |
| Environment | `PHP` |
| Build Command | `composer install --no-dev --optimize-autoloader` |
| Publish Directory | `public` |

### 2.4 Add Environment Variables

Add these in Render dashboard under "Environment Variables":

```env
# Supabase Database (PostgreSQL)
DB_HOST=db.your-project.supabase.co
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.your-project
DB_PASS=your-supabase-password

# M-Pesa (Production)
MPESA_ENV=live
MPESA_CONSUMER_KEY=your-consumer-key
MPESA_CONSUMER_SECRET=your-consumer-secret
MPESA_SHORTCODE=your-shortcode
MPESA_PASSKEY=your-passkey
MPESA_CALLBACK_URL=https://your-app.onrender.com/api/mpesa/callback.php

# Email (SMTP)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=Outsourced Technologies
```

> **Note:** For Gmail, use an [App Password](https://support.google.com/accounts/answer/185833)

---

## Step 3: Configure Supabase

### 3.1 Enable External Connections

1. Go to Supabase Dashboard → Settings → Database
2. Find "Connection Pooler" and "Direct Connection"
3. Note your connection string

### 3.2 Configure IP Allowlist

Add Render's IP ranges to Supabase:
- Go to Supabase → Settings → Database → IP Allowlist
- Add: `0.0.0.0/0` (for development) or specific Render IPs

---

## Step 4: Configure M-Pesa for Production

### 4.1 Update M-Pesa Credentials

1. Go to [Daraja Portal](https://developer.safaricom.co.ke)
2. Switch from Sandbox to Production
3. Get production credentials
4. Update environment variables in Render

### 4.2 Update Callback URL

Ensure the callback URL in M-Pesa dashboard matches:
```
https://your-app.onrender.com/api/mpesa/callback.php
```

---

## Step 5: Verify Deployment

### 5.1 Test Health Endpoint

Visit: `https://your-app.onrender.com/api/health.php`

### 5.2 Test Database Connection

Visit: `https://your-app.onrender.com/public/test-db.php`

### 5.3 Test M-Pesa Callback

The M-Pesa callback URL should be publicly accessible with HTTPS.

---

## Alternative Free Options

If Render doesn't work for you, consider these alternatives:

| Provider | Pros | Cons |
|----------|------|------|
| **Railway** | Easy setup, PostgreSQL built-in | Limited free tier (500 hours) |
| **Fly.io** | Global CDN, persistent storage | Complex Docker setup |
| **Cyclic** | Generous free tier | Limited PHP support |

---

## Support

- Render Documentation: https://render.com/docs
- Supabase Documentation: https://supabase.com/docs
- M-Pesa Daraja API: https://developer.safaricom.co.ke

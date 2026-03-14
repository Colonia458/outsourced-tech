# Automation Setup Guide

## 1. Cron Jobs Setup (cPanel/Hostinger)

### In cPanel:
1. Go to **Cron Jobs** in cPanel
2. Add new cron job with these settings:

#### Every 15 minutes - Enhanced Cron (Notifications, Queue):
```
*/15 * * * * curl -s "https://outsourcedtechnologies.co.ke/api/enhanced-cron.php?key=YOUR_SECRET_KEY" > /dev/null 2>&1
```

#### Every hour - Basic Cron (Low stock, Cleanup):
```
0 * * * * curl -s "https://outsourcedtechnologies.co.ke/api/cron.php?key=YOUR_SECRET_KEY" > /dev/null 2>&1
```

#### Daily at 6 AM - Reports:
```
0 6 * * * curl -s "https://outsourcedtechnologies.co.ke/api/enhanced-cron.php?key=YOUR_SECRET_KEY" > /dev/null 2>&1
```

### In Hostinger:
1. Go to **Cron Jobs** in hPanel
2. Add new cron job using the same commands above

### Set your secret key in .env:
```
CRON_SECRET_KEY=your-secure-random-key-change-me
```

---

## 2. GitHub Actions Setup

### Prerequisites:
1. Create a GitHub repository
2. Add these secrets in GitHub → Settings → Secrets:

#### Required Secrets:
| Secret | Description |
|--------|-------------|
| `SSH_PRIVATE_KEY` | Private SSH key for deployment |
| `SSH_HOST` | Your server hostname |
| `SSH_USER` | SSH username |
| `MYSQL_DATABASE` | Database name |
| `MYSQL_ROOT_PASSWORD` | MySQL root password |
| `AWS_S3_BUCKET` | S3 bucket for backups (optional) |
| `AWS_ACCESS_KEY_ID` | AWS access key (optional) |
| `AWS_SECRET_ACCESS_KEY` | AWS secret key (optional) |
| `DISCORD_WEBHOOK` | Discord webhook for notifications |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `NOTIFY_EMAIL` | Email for failure notifications |

### Workflow Triggers:
- **Push to `develop`** → Auto-deploy to staging
- **Push to `main`** → Auto-deploy to production
- **Schedule** → Daily database backup

---

## 3. Test the Features

### Test Invoice Generation:
Visit: `https://outsourcedtechnologies.co.ke/api/test-invoice.php`

### Test Booking Availability:
Visit: `https://outsourcedtechnologies.co.ke/api/test-booking.php`

### Test Cron Jobs Manually:
```bash
# Test enhanced cron
curl "https://outsourcedtechnologies.co.ke/api/enhanced-cron.php?key=YOUR_KEY"

# Test basic cron
curl "https://outsourcedtechnologies.co.ke/api/cron.php?key=YOUR_KEY"

# Test backup
curl "https://outsourcedtechnologies.co.ke/api/backup.php?key=YOUR_KEY"
```

---

## 4. Verify Installation

### Check Database Tables:
```sql
SHOW TABLES LIKE 'notification_queue';
SHOW TABLES LIKE 'loyalty_transactions';
SHOW TABLES LIKE 'report_schedules';
```

### Check Logs:
```bash
tail -f logs/cron.log
tail -f logs/email_errors.log
tail -f logs/low_stock.log
```

---

## 5. Feature Summary

| Feature | Cron Frequency | Endpoint |
|---------|---------------|----------|
| Notification Queue | Every 15 min | enhanced-cron.php |
| Booking Reminders | Every hour | enhanced-cron.php |
| Abandoned Carts | Every hour | enhanced-cron.php |
| Low Stock Alerts | Every 6 hours | enhanced-cron.php |
| Daily Reports | Daily 6 AM | enhanced-cron.php |
| Loyalty Expiry | Daily midnight | enhanced-cron.php |
| Auto-hide Out-of-Stock | Every hour | enhanced-cron.php |
| Cancel Unpaid Orders | Every hour | enhanced-cron.php |
| Daily Backup | Daily 2 AM | GitHub Actions |

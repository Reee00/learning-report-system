# 🚀 Panduan Deployment

## 1. Deployment Architecture

```
┌──────────────────────┐
│  Local Development   │
│  (Git repository)    │
└──────────┬───────────┘
           │ git push
           ▼
┌──────────────────────┐
│  GitHub Repository   │
│  (Main branch)       │
└──────────┬───────────┘
           │ (Auto-deploy or manual trigger)
           ▼
┌──────────────────────┐
│  Railway Platform    │
│  (Managed hosting)   │
│  • Container runtime │
│  • Database (MySQL)  │
│  • Auto-restart      │
│  • Domain + SSL      │
└──────────────────────┘
           │
           ├──────────────► Cloudinary (Media)
           └──────────────► External APIs
```

---

## 2. Pre-Deployment Checklist

### Code Readiness
- [ ] All features tested locally
- [ ] No debug code left (`dd()`, `var_dump()`, etc)
- [ ] `APP_DEBUG=false` in production env
- [ ] All migrations tested
- [ ] Environment variables documented

### Security
- [ ] Generate new `APP_KEY` for production
- [ ] Database credentials secured
- [ ] Cloudinary credentials secured
- [ ] HTTPS enforced
- [ ] CORS configured (if needed)
- [ ] No hardcoded secrets in code

### Performance
- [ ] Cache configured (database)
- [ ] Database indexes optimized
- [ ] Large queries optimized (eager loading)
- [ ] Static assets optimized
- [ ] NPM build completed (`npm run build`)

### Documentation
- [ ] Deployment steps documented
- [ ] Rollback procedure documented
- [ ] Admin credentials backup prepared
- [ ] Database backup strategy set

---

## 3. Deployment via Railway

### 3.1 Prerequisites

- GitHub account with repository access
- Railway account (https://railway.app)
- Cloudinary account with API credentials

### 3.2 Setup Railway Project

#### Step 1: Connect GitHub

1. Go to: https://railway.app
2. Login dengan GitHub
3. Click \"Create a new project\"
4. Select \"Deploy from GitHub repo\"
5. Select repository: `Reee00/learning-report-system`
6. Select branch: `main`

#### Step 2: Add Environment Variables

In Railway dashboard:

```
Variables → Add Variable

APP_NAME=Learning Report System
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxxxx (generate new key)
APP_URL=https://[your-domain].railway.app

DB_CONNECTION=mysql
DB_HOST=[railway-mysql-host]
DB_PORT=3306
DB_DATABASE=learning_report
DB_USERNAME=[random-user]
DB_PASSWORD=[random-password]

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

#### Step 3: Add Database

1. In Railway dashboard: \"Add services\"
2. Select \"MySQL\"
3. Auto-configured, Railway provides connection details
4. Copy DB credentials to environment variables

#### Step 4: Deploy

1. Click \"Deploy\"
2. Railroad akan:
   - Build Docker image
   - Run migrations (`php artisan migrate --force`)
   - Run seeders
   - Start application
3. Wait for deployment to complete (2-5 minutes)
4. Access: `https://[project-name].railway.app`

### 3.3 Post-Deployment Verification

```bash
# Check deployment logs
# In Railway dashboard: Deployments → View logs

# Verify application
curl https://[your-domain].railway.app
# Should return login page HTML

# Test login
# Go to: https://[your-domain].railway.app/login
# Try: admin@example.com / password

# Check database
# In Railway: MySQL logs should show migrations ran
```

---

## 4. Deployment via Docker (Manual)

### 4.1 Build Docker Image

```bash
# Build locally
docker build -t learning-report-system:latest .

# Tag untuk registry
docker tag learning-report-system:latest username/learning-report-system:latest
```

### 4.2 Push to Registry

```bash
# Login ke Docker Hub
docker login

# Push image
docker push username/learning-report-system:latest
```

### 4.3 Deploy on Server

```bash
# SSH to server
ssh user@your-server.com

# Pull image
docker pull username/learning-report-system:latest

# Create .env file
nano .env
# Paste configuration

# Run container
docker run -d \
  --name learning-report \
  -p 8080:8080 \
  --env-file .env \
  --restart unless-stopped \
  username/learning-report-system:latest

# Check status
docker ps
docker logs learning-report
```

### 4.4 Nginx Reverse Proxy (Optional)

```nginx
# /etc/nginx/sites-available/learning-report

server {
    listen 80;
    server_name learning-report.example.com;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 5. Deployment via VPS (Manual)

### 5.1 Server Requirements

```
OS: Ubuntu 20.04 LTS or later
CPU: 1 vCPU minimum
RAM: 2GB minimum
Disk: 20GB
```

### 5.2 Install Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP & extensions
sudo apt install -y \
  php8.3 \
  php8.3-cli \
  php8.3-fpm \
  php8.3-mysql \
  php8.3-pgsql \
  php8.3-gd \
  php8.3-xml \
  php8.3-mbstring \
  php8.3-zip \
  php8.3-curl

# Install MySQL / PostgreSQL
sudo apt install -y mysql-server
# OR
sudo apt install -y postgresql

# Install Nginx
sudo apt install -y nginx

# Install Composer
sudo apt install -y composer

# Install Node.js & NPM
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 5.3 Clone & Setup Application

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/Reee00/learning-report-system.git
cd learning-report-system

# Setup permissions
sudo chown -R www-data:www-data .
sudo chmod -R 775 storage bootstrap/cache

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Setup environment
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate

# Database
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force

# Build assets
sudo -u www-data npm install
sudo -u www-data npm run build

# Storage symlink
sudo -u www-data php artisan storage:link
```

### 5.4 Configure Nginx

```bash
# Create Nginx config
sudo nano /etc/nginx/sites-available/learning-report

# Paste:
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;

    root /var/www/learning-report-system/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}

# Enable site
sudo ln -s /etc/nginx/sites-available/learning-report /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default

# Test & restart
sudo nginx -t
sudo systemctl restart nginx
```

### 5.5 Configure Database

```bash
# MySQL
sudo mysql
# di MySQL prompt:
CREATE DATABASE learning_report;
CREATE USER 'lrs_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON learning_report.* TO 'lrs_user'@'localhost';
FLUSH PRIVILEGES;

# Update .env
DB_HOST=localhost
DB_DATABASE=learning_report
DB_USERNAME=lrs_user
DB_PASSWORD=strong_password
```

### 5.6 Setup HTTPS (SSL Certificate)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get certificate
sudo certbot certonly --nginx -d your-domain.com -d www.your-domain.com

# Auto-renew
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer

# Update Nginx for HTTPS
sudo nano /etc/nginx/sites-available/learning-report
# Update: listen 443 ssl; (copy SSL config dari Certbot)

sudo systemctl reload nginx
```

### 5.7 Setup Process Manager (Supervisor)

```bash
# Install Supervisor
sudo apt install -y supervisor

# Create config
sudo nano /etc/supervisor/conf.d/learning-report.conf

# Paste:
[program:learning-report-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/learning-report-system/artisan queue:listen
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/learning-report-system/storage/logs/queue.log

# Start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start learning-report-queue:*
```

---

## 6. Backup Strategy

### Database Backup

```bash
# Daily backup (MySQL)
0 2 * * * mysqldump -u lrs_user -p'password' learning_report > /backups/db_$(date +\\%Y\\%m\\%d).sql

# Or using script
# /var/www/learning-report-system/scripts/backup.sh
#!/bin/bash
BACKUP_DIR=/backups
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u lrs_user -p'password' learning_report > $BACKUP_DIR/db_$DATE.sql
gzip $BACKUP_DIR/db_$DATE.sql
# Remove backups older than 30 days
find $BACKUP_DIR -name \"db_*.sql.gz\" -mtime +30 -delete

# Add to crontab
0 2 * * * /var/www/learning-report-system/scripts/backup.sh
```

### Application Files Backup

```bash
# Weekly backup of entire app
0 3 * * 0 tar -czf /backups/app_$(date +\\%Y\\%m\\%d).tar.gz /var/www/learning-report-system/
```

### Cloud Backup

```bash
# Use S3, Backblaze, atau cloud storage lainnya
# Example: Upload backups to S3
aws s3 cp /backups/db_*.sql.gz s3://your-bucket/backups/
```

---

## 7. Rollback Procedure

### If Deployment Failed

```bash
# Via Railway
# 1. Go to Deployments tab
# 2. Click previous successful deployment
# 3. Click \"Redeploy\"

# Via Docker
docker stop learning-report
docker rm learning-report
docker pull username/learning-report-system:previous-tag
docker run -d --name learning-report ... username/learning-report-system:previous-tag

# Via VPS (Git)
cd /var/www/learning-report-system
git log --oneline  # Find previous commit
git checkout [commit-hash]
sudo -u www-data php artisan migrate:rollback
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

---

## 8. Monitoring & Maintenance

### Application Health Check

```bash
# Check if running
curl https://your-domain.com/login
# Should return 200 OK

# Check database
curl https://your-domain.com/api/health
# (implement health endpoint)

# Check logs
# SSH to server & view:
tail -f /var/www/learning-report-system/storage/logs/laravel.log

# Via Railway: Logs → View live logs
```

### Performance Monitoring

```bash
# Memory usage
free -h

# Disk usage
df -h

# CPU usage
top

# Nginx connections
netstat -an | grep ESTABLISHED | wc -l
```

### Updates & Patches

```bash
# Update OS packages
sudo apt update && sudo apt upgrade -y

# Update PHP extensions
sudo apt install -y --only-upgrade php8.3-*

# Update Composer packages
cd /var/www/learning-report-system
sudo -u www-data composer update --no-dev

# Restart services
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

---

## 9. Environment-Specific Configuration

### Production (.env)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

SESSION_SECURE_COOKIES=true
SESSION_SAME_SITE=strict

DB_CONNECTION=mysql
(Production DB credentials)

CLOUDINARY_* (Production credentials if different)
```

### Staging (.env)

```
APP_ENV=staging
APP_DEBUG=true (for debugging if issues)
APP_URL=https://staging.your-domain.com

SESSION_SECURE_COOKIES=true

(Separate staging database)
```

---

## 10. Common Deployment Issues

### Issue: \"502 Bad Gateway\"

**Cause**: PHP-FPM not running or Nginx misconfigured

**Solution**:
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
tail -f /var/log/nginx/error.log
```

### Issue: \"Composer dependencies missing\"

**Cause**: composer install not run or .gitignore excludes vendor

**Solution**:
```bash
cd /var/www/learning-report-system
composer install --no-dev --optimize-autoloader
```

### Issue: \"Database migration failed\"

**Cause**: Wrong DB credentials or DB not running

**Solution**:
```bash
php artisan migrate:status  # Check status
php artisan migrate --force  # Force run
php artisan migrate:rollback  # Rollback if issues
```

### Issue: \"Permission denied\" on storage/logs

**Cause**: Wrong file permissions

**Solution**:
```bash
sudo chown -R www-data:www-data /var/www/learning-report-system
sudo chmod -R 775 storage bootstrap/cache
```

### Issue: \"Cloudinary upload fails\"

**Cause**: Invalid API credentials

**Solution**:
```bash
# Check .env
cat .env | grep CLOUDINARY_

# Test manually
php artisan tinker
$result = CloudinaryHelper::upload('/tmp/test.jpg', 'lrs');

# Check Cloudinary dashboard for quota
```

---

## 11. Deployment Checklist

Before deploying to production:

- [ ] Test all features locally
- [ ] Run tests: `php artisan test`
- [ ] Build assets: `npm run build`
- [ ] Check for errors: `php artisan tinker` + test queries
- [ ] Backup current production database
- [ ] Backup current production files
- [ ] Generate new APP_KEY
- [ ] Review environment variables
- [ ] Test in staging environment first
- [ ] Prepare rollback procedure
- [ ] Notify users of maintenance window (if needed)
- [ ] Deploy
- [ ] Run migrations
- [ ] Verify health check
- [ ] Test critical workflows
- [ ] Monitor logs for errors
- [ ] Celebrate! 🎉


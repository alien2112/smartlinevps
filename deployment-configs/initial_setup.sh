#!/bin/bash
# ============================================
# SmartLine Initial VPS Setup Script
# ============================================
# Run on a fresh Ubuntu 22.04/24.04 VPS
# Run as root: sudo bash initial_setup.sh
# ============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DOMAIN="your-domain.com"  # CHANGE THIS
DB_NAME="smartline"
DB_USER="smartline"
DB_PASS=$(openssl rand -base64 32)
REDIS_PASS=$(openssl rand -base64 32)

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}   SmartLine Initial VPS Setup${NC}"
echo -e "${BLUE}   Ubuntu $(lsb_release -rs)${NC}"
echo -e "${BLUE}============================================${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (sudo bash initial_setup.sh)${NC}"
    exit 1
fi

# 1. Update system
echo -e "\n${YELLOW}1. Updating system...${NC}"
apt update && apt upgrade -y

# 2. Install essential packages
echo -e "\n${YELLOW}2. Installing essential packages...${NC}"
apt install -y software-properties-common curl wget git unzip htop

# 3. Add PHP repository
echo -e "\n${YELLOW}3. Adding PHP repository...${NC}"
add-apt-repository ppa:ondrej/php -y
apt update

# 4. Install PHP 8.2
echo -e "\n${YELLOW}4. Installing PHP 8.2...${NC}"
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath \
    php8.2-intl php8.2-redis php8.2-opcache

# 5. Install Nginx
echo -e "\n${YELLOW}5. Installing Nginx...${NC}"
apt install -y nginx
systemctl enable nginx

# 6. Install MySQL
echo -e "\n${YELLOW}6. Installing MySQL 8.0...${NC}"
apt install -y mysql-server
systemctl enable mysql

# 7. Install Redis
echo -e "\n${YELLOW}7. Installing Redis...${NC}"
apt install -y redis-server
systemctl enable redis-server

# Configure Redis password
sed -i "s/# requirepass foobared/requirepass $REDIS_PASS/" /etc/redis/redis.conf
sed -i "s/^maxmemory .*/maxmemory 2gb/" /etc/redis/redis.conf
echo "maxmemory-policy allkeys-lru" >> /etc/redis/redis.conf
systemctl restart redis-server

# 8. Install Composer
echo -e "\n${YELLOW}8. Installing Composer...${NC}"
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 9. Install Node.js 18
echo -e "\n${YELLOW}9. Installing Node.js 18...${NC}"
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs
npm install -g pm2

# 10. Install Supervisor
echo -e "\n${YELLOW}10. Installing Supervisor...${NC}"
apt install -y supervisor
systemctl enable supervisor

# 11. Install Certbot
echo -e "\n${YELLOW}11. Installing Certbot...${NC}"
apt install -y certbot python3-certbot-nginx

# 12. Create deploy user
echo -e "\n${YELLOW}12. Creating deploy user...${NC}"
if ! id "smartline" &>/dev/null; then
    adduser --disabled-password --gecos "" smartline
    usermod -aG sudo smartline
    usermod -aG www-data smartline
fi

# 13. Create database
echo -e "\n${YELLOW}13. Creating MySQL database...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# 14. Create project directory
echo -e "\n${YELLOW}14. Creating project directory...${NC}"
mkdir -p /var/www/smartline
chown -R smartline:www-data /var/www/smartline
chmod -R 755 /var/www/smartline

# 15. Configure firewall
echo -e "\n${YELLOW}15. Configuring firewall...${NC}"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw allow 6015/tcp  # Reverb WebSocket
ufw --force enable

# 16. Copy PHP OPcache config
echo -e "\n${YELLOW}16. Configuring PHP OPcache...${NC}"
cat > /etc/php/8.2/fpm/conf.d/99-opcache-custom.ini << 'EOF'
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
opcache.jit=1255
opcache.jit_buffer_size=128M
EOF

systemctl restart php8.2-fpm

# Summary
echo -e "\n${GREEN}============================================${NC}"
echo -e "${GREEN}   Initial Setup Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "${BLUE}Save these credentials securely:${NC}"
echo ""
echo "Database Name: $DB_NAME"
echo "Database User: $DB_USER"
echo "Database Pass: $DB_PASS"
echo ""
echo "Redis Password: $REDIS_PASS"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Switch to smartline user: sudo su - smartline"
echo "2. Clone your repository: cd /var/www/smartline && git clone YOUR_REPO ."
echo "3. Copy .env.production.example to .env and configure"
echo "4. Run composer install"
echo "5. Run php artisan key:generate"
echo "6. Run php artisan migrate"
echo "7. Configure Nginx: copy nginx-smartline.conf to /etc/nginx/sites-available/"
echo "8. Configure Supervisor: copy supervisor configs to /etc/supervisor/conf.d/"
echo "9. Get SSL certificate: sudo certbot --nginx -d $DOMAIN"
echo ""
echo -e "${GREEN}Credentials saved to: /root/smartline-credentials.txt${NC}"

# Save credentials to file
cat > /root/smartline-credentials.txt << EOF
SmartLine VPS Credentials
=========================
Generated: $(date)

Database:
  Name: $DB_NAME
  User: $DB_USER
  Pass: $DB_PASS

Redis:
  Password: $REDIS_PASS

Remember to:
1. Update .env with these credentials
2. Change REDIS_PASSWORD in Redis config if needed
3. Keep this file secure and delete after setup
EOF

chmod 600 /root/smartline-credentials.txt

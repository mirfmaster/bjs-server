## Setting Permission issue
```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/html/bjs/bjs-server/storage
sudo chown -R www-data:www-data /var/www/html/bjs/bjs-server/bootstrap/cache

# Set directory permissions
sudo chmod -R 775 /var/www/html/bjs/bjs-server/storage
sudo chmod -R 775 /var/www/html/bjs/bjs-server/bootstrap/cache

# Set the setgid bit on logs directory
sudo chmod g+s /var/www/html/bjs/bjs-server/storage/logs

# Add your user to www-data group
sudo usermod -a -G www-data $USER

sudo service nginx restart
```

```bash
# Setting up log rotate in /etc/logrotate.d/bjs-server
/var/www/html/bjs/bjs-server/storage/logs/*.log {
    su www-data www-data
    daily
    missingok
    rotate 14
    maxage 14
    nocompress
    notifempty
    create 0664 www-data www-data
    sharedscripts
}

# Test
# Do a dry run
sudo logrotate -d /etc/logrotate.d/laravel

# Force rotation (for testing)
sudo logrotate -f /etc/logrotate.d/laravel

```

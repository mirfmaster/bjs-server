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

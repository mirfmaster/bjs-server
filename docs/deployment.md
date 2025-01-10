## Setting Permission issue
```bash
root@localhost:/var/www/html/bjs/bjs-server/storage/logs# sudo chown -R www-data:www-data /var/www/html/bjs/bjs-server/storage
root@localhost:/var/www/html/bjs/bjs-server/storage/logs# sudo chmod -R 775 /var/www/html/bjs/bjs-server/storage
root@localhost:/var/www/html/bjs/bjs-server/storage/logs# sudo chmod g+s /var/www/html/bjs/bjs-server/storage/logs
```

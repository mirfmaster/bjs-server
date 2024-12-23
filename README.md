# BJS Server

This repository contain code of BJS v2 Server


## Migration And Seed
```bash
php artisan migrate

php artisan db:seed
```

### One time seed
Seeder for worker accounts, you need to add the assets into `storage/app/assets/prod/workers.csv`
```bash
php artisan db:seed --class=WorkerSeeder
```

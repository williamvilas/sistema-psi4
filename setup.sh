#!/bin/bash

sudo chmod -R 777 storage
sudo chmod -R 777 bootstrap/cache

php artisan config:clear
php artisan cache:clear
php artisan queue:restart



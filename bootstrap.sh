
# composer create-project suralabs/sura:^0.0.1 APP --prefer-dist
# cd APP
composer install
php sura -copy /system/data/config.php.example /system/data/config.php
php sura -copy /system/data/db_config.php.example /system/data/db_config.php
# cp /workspace/system/data/config.php.example /workspace/system/data/config.php 
# cp /workspace/system/data/db_config.php.example /workspace/system/data/db_config.php 
php sura -migrate
php sura -make:add-user Ivan Ivanov ivanov@example.ru example

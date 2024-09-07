composer install 
cp /workspace/system/data/config.php.example /workspace/system/data/config.php 
cp /workspace/system/data/db_config.php.example /workspace/system/data/db_config.php 
php sura -migrate
php sura -make:add-user Ivan Ivanov ivanov@example.ru example

<?php

//config
define('BASE_PATH', dirname(__DIR__));
define('DISPLAY_ERROR', true);
define('CURRENT_DOMAIN', trim(currentDomain(), '/'));
define('DB_HOST', 'db');
define('DB_USERNAME', 'urluser');
define('DB_NAME', 'url_shortener');
define('DB_PASSWORD', 'urlpassword');
define('DB_REDIS', 'redis');
define('DB_REDIS_PORT', '6379');
define('SHEMA_DB_REDIS_PORT', 'tcp');
define('JWT_SECRET_KEY', '/Ka4Wcvynx64mIqt8vehHWFg8W3ewBLDl86EVu3VoVc=');
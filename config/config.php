<?php

//config
define('BASE_PATH', dirname(__DIR__));
define('DISPLAY_ERROR', true);
define('CURRENT_DOMAIN', trim(currentDomain(), '/'));
define('DB_HOST', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_NAME', 'link-shortener');
define('DB_PASSWORD', '123456789');
define('JWT_SECRET_KEY', '/Ka4Wcvynx64mIqt8vehHWFg8W3ewBLDl86EVu3VoVc=');
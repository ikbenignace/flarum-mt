<?php

// Multi tenant Flarum Config
// 1. Get connection to MariaDB and pull names of all databases
// 2. If the current domain is in the list of databases, use that database
// 3. If not, return a 404 error
// 4. If the database is found
// 4a. Check if the there is a folder with the same name name of the domain in the folder "domains"
// 4b. If the folder exists, do nothing and go further
// 4c. If the folder does not exist, create a new folder with the name of the domain in the folder "domains" and copy the folder "storage" in that folder, do the same for public folder (but only the assets folder) with symlink to the original files in the public folder (.htaccess, index.php, web.config)


return array(
    'debug' => false,
    'offline' => false,
    'database' => array(
        'driver' => getenv('DB_DRIVER') ?: 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'database' =>  $_SERVER['HTTP_HOST'],
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
        'prefix' => getenv('DB_PREFIX') ?: '',
        'port' => getenv('DB_PORT') ?: '3306',
        'strict' => false,
    ),
    'url' => 'https://' . $_SERVER['HTTP_HOST'],
    'paths' => array(
        'api' => 'api',
        'admin' => 'admin',
    ),
);

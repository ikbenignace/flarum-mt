<?php

// Multi tenant Flarum Config
// 1. Get connection to MariaDB and pull names of all databases
// 2. If the current domain is in the list of databases, use that database
// 3. If not, return a 404 error
// 4. If the database is found
// 4a. Check if the there is a folder with the same name name of the domain in the folder "domains"
// 4b. If the folder exists, do nothing and go further
// 4c. If the folder does not exist, create a new folder with the name of the domain in the folder "domains" and copy the folder "storage" in that folder, do the same for public folder (but only the assets folder) with symlink to the original files in the public folder (.htaccess, index.php, web.config)

global $domain;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$config = array(
    'debug' => false,
    'offline' => false,
    'database' => array(
        'driver' => $_ENV['DB_DRIVER'] ?: 'mysql',
        'host' => $_ENV['DB_HOST'] ?: 'localhost',
        'database' =>  "$domain",
        'username' => $_ENV['DB_USER'] ?: 'root',
        'password' => $_ENV['DB_PASSWORD'] ?: '',
        'charset' => $_ENV['DB_CHARSET'] ?: 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?: 'utf8mb4_unicode_ci',
        'prefix' => $_ENV['DB_PREFIX'] ?: '',
        'port' => $_ENV['DB_PORT'] ?: '3306',
        'strict' => false,
    ),
    'url' => 'https://' . $domain,
    'paths' => array(
        'api' => 'api',
        'admin' => 'admin',
    ),
);

print_r(getenv("DB_HOST"));

// log the config as string
file_put_contents(__DIR__ . '/config.log', print_r($config, true));


return $config;

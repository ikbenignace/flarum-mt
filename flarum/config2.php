<?php

global $domain;

return array(
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

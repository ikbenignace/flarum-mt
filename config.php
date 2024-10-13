<?php

// Multi tenant Flarum Config
// 1. Get connection to MariaDB and pull names of all databases
// 2. If the current domain is in the list of databases, use that database
// 3. If not, return a 404 error
// 4. If the database is found
// 4a. Check if the there is a folder with the same name name of the domain in the folder "domains"
// 4b. If the folder exists, do nothing and go further
// 4c. If the folder does not exist, create a new folder with the name of the domain in the folder "domains" and copy the folder "storage" in that folder, do the same for public folder (but only the assets folder) with symlink to the original files in the public folder (.htaccess, index.php, web.config)

function getDatabaseNameFromDomain($domain) {
    // Connect to MariaDB
    $mysqli = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '');
    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    // Get list of databases
    $result = $mysqli->query("SHOW DATABASES");
    $databases = [];
    while ($row = $result->fetch_assoc()) {
        $databases[] = $row['Database'];
    }

    // Close connection
    $mysqli->close();

    // Check if the domain is in the list of databases
    if (in_array($domain, $databases)) {
        return $domain;
    } else {
        http_response_code(404);
        die('404 Not Found');
    }
}

function setupDomainFolders($domain) {
    $domainPath = __DIR__ . "/domains/$domain";
    $storageSource = __DIR__ . "/storage";
    $publicSource = __DIR__ . "/public";
    $publicAssetsSource = "$publicSource/assets";

    // Check if the domain folder exists
    if (!is_dir($domainPath)) {
        // Create the domain folder
        mkdir($domainPath, 0755, true);

        // Copy the storage folder
        recurseCopy($storageSource, "$domainPath/storage");

        // Copy the assets folder
        recurseCopy($publicAssetsSource, "$domainPath/assets");

        // Create symlinks for public folder files
        symlink("$publicSource/.htaccess", "$domainPath/.htaccess");
        symlink("$publicSource/index.php", "$domainPath/index.php");
        symlink("$publicSource/web.config", "$domainPath/web.config");
    }
}

function recurseCopy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir("$src/$file")) {
                recurseCopy("$src/$file", "$dst/$file");
            } else {
                copy("$src/$file", "$dst/$file");
            }
        }
    }
    closedir($dir);
}

// Get the current domain
$domain = $_SERVER['SERVER_NAME'];

// Get the database name based on the domain
$databaseName = getDatabaseNameFromDomain($domain);

// Setup domain folders
setupDomainFolders($domain);

return array(
    'debug' => false,
    'offline' => false,
    'database' => array(
        'driver' => getenv('DB_DRIVER') ?: 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'database' => $databaseName,
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
        'prefix' => getenv('DB_PREFIX') ?: '',
        'port' => getenv('DB_PORT') ?: '3306',
        'strict' => false,
    ),
    'url' => 'https://' . $domain,
    'paths' => array(
        'api' => 'api',
        'admin' => 'admin',
    ),
);
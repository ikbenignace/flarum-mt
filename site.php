<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

/*
|-------------------------------------------------------------------------------
| Load the autoloader
|-------------------------------------------------------------------------------
|
| First, let's include the autoloader, which is generated automatically by
| Composer (PHP's package manager) after installing our dependencies.
| From now on, all classes in our dependencies will be usable without
| explicitly loading any files.
|
*/

require __DIR__.'/vendor/autoload.php';

/*
|-------------------------------------------------------------------------------
| Configure the site
|-------------------------------------------------------------------------------
|
| A Flarum site represents your local installation of Flarum. It can be
| configured with a bunch of paths:
|
| - The *base path* is Flarum's root directory and contains important files
|   such as config.php and extend.php.
| - The *public path* is the directory that serves as document root for the
|   web server. Files in this place are accessible to the public internet.
|   This is where assets such as JavaScript files or CSS stylesheets need to
|   be stored in a default install.
| - The *storage path* is a place for Flarum to store files it generates during
|   runtime. This could be caches, session data or other temporary files.
|
| The fully configured site instance is returned to the including script, which
| then uses it to boot up the Flarum application and e.g. accept web requests.
|
*/

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

return Flarum\Foundation\Site::fromPaths([
    'base' => __DIR__,
    'public' => __DIR__.'/domains/'.$domain.'/public',
    'storage' => __DIR__.'/domains/'.$domain.'/storage',
]);

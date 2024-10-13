<?php

use Flarum\Testing\integration\Setup\SetupScript;


require __DIR__.'/vendor/autoload.php';
include __DIR__.'/domain.php';


function setupDomainFolders($domain) {
    $domainPath = __DIR__ . "/domains/$domain";
    $storageSource = __DIR__ . "/storage";
    $publicSource = __DIR__ . "/public";
    $publicAssetsSource = "$publicSource/assets";

    // Check if the domain folder exists
    if (!is_dir($domainPath)) {
        // Create the domain folder
        if (!mkdir($domainPath, 0755, true)) {
            die('Failed to create domain directory: ' . $domainPath);
        }

        // Copy the storage folder
        recurseCopy($storageSource, "$domainPath/storage");

        // Copy the assets folder
        recurseCopy($publicAssetsSource, "$domainPath/assets");

        // Create symlinks for public folder files
        createSymlink("$publicSource/.htaccess", "$domainPath/.htaccess");
        createSymlink("$publicSource/index.php", "$domainPath/index.php");
        createSymlink("$publicSource/web.config", "$domainPath/web.config");
    }
}

function createSymlink($target, $link) {
    if (!file_exists($target)) {
        die('Source file does not exist: ' . $target);
    }
    if (!symlink($target, $link)) {
        die('Failed to create symlink: ' . $link);
    }
}

function getDatabaseNameFromDomain($domain, $cli) {
    $mysqli = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '');
    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $result = $mysqli->query("SHOW DATABASES");
    $databases = [];
    while ($row = $result->fetch_assoc()) {
        $databases[] = $row['Database'];
    }


    if (in_array($domain, $databases)) {
        // Get tables of the database
        $tablesResult = $mysqli->query("SHOW TABLES FROM `$domain`");
        if ($tablesResult->num_rows == 0) {
            // No tables found

            // Backup config.php
            $configPath = __DIR__ . "/config.php";
            $configContents = file_get_contents($configPath);
            if ($configContents === false) {
                die('Failed to read config.php');
            }

            // Run migration
            $migration = new SetupScript();
            $migration->run();

            // Restore config.php

            if (file_put_contents($configPath, $configContents) === false) {
                die('Failed to restore config.php');
            }
        }

        $mysqli->close();

        return $domain;
    } else {
        if(!$cli) http_response_code(404);
        $mysqli->close();
        die('Nothing found on this domain: ' . $domain);
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



$databaseName = getDatabaseNameFromDomain($domain, $cli);
setupDomainFolders($domain);

return Flarum\Foundation\Site::fromPaths([
    'base' => __DIR__,
    'public' => __DIR__.'/domains/'.$domain.'/public',
    'storage' => __DIR__.'/domains/'.$domain.'/storage',
]);

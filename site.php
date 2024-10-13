<?php

require __DIR__.'/vendor/autoload.php';

//log all arguments in cli
if (php_sapi_name() == 'cli') {
    echo "Arguments: \n";
    foreach ($argv as $arg) {
        echo $arg . "\n";
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

function getDatabaseNameFromDomain($domain) {
    $mysqli = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '');
    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $result = $mysqli->query("SHOW DATABASES");
    $databases = [];
    while ($row = $result->fetch_assoc()) {
        $databases[] = $row['Database'];
    }

    $mysqli->close();

    if (in_array($domain, $databases)) {
        // TODO
        // get tables of the database
        // if no tables found, run migration script
        return $domain;
    } else {
        http_response_code(404);
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

if (isset($_SERVER['HTTP_HOST'])) {
    $domain = $_SERVER['HTTP_HOST'];
    echo "Using HTTP_HOST: $domain\n";
} elseif (isset($argv[1])) {
    $domain = $argv[1];
    echo "Using CLI argument: $domain\n";
} else {
    die('No domain specified.');
}

$databaseName = getDatabaseNameFromDomain($domain);
setupDomainFolders($domain);

return Flarum\Foundation\Site::fromPaths([
    'base' => __DIR__,
    'public' => __DIR__.'/domains/'.$domain.'/public',
    'storage' => __DIR__.'/domains/'.$domain.'/storage',
]);

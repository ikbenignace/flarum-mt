<?php

$cli = false;
$domain = null;

if (isset($_SERVER['HTTP_HOST'])) {
    $domain = $_SERVER['HTTP_HOST'];
    echo "Using HTTP_HOST: $domain\n";
} elseif (isset($argv[2])) {
    $domain = $argv[2];
    //set http_host to domain
    $_SERVER['HTTP_HOST'] = $domain;
    $cli = true;
    echo "Using CLI argument: $domain\n";
} else {
    if ($domain === null) {
        //log all args
        die('No domain specified: ' . print_r($argv, true));
    }
}
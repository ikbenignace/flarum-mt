<?php

$cli = false;
$domain = null;

if (isset($_SERVER['HTTP_HOST'])) {
    $domain = $_SERVER['HTTP_HOST'];
    echo "Using HTTP_HOST: $domain\n";
} elseif (isset($argv[2])) {
    $domain = $argv[2];
    echo "Using CLI argument: $domain\n";
    $cli = true;
} else {
    die('No domain specified.');
}
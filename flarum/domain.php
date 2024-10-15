<?php

$cli = false;
$domain = null;

if (!in_array('install', $argv)) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $domain = $_SERVER['HTTP_HOST'];
    } elseif (isset($argv[2])) {
        $domain = $argv[2];
        //set http_host to domain
        $_SERVER['HTTP_HOST'] = $domain;
        $cli = true;
    } else {
        if ($domain === null) {
            //log all args
            die('No domain specified: ' . print_r($argv, true));
        }
    }
}

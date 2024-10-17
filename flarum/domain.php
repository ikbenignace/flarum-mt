<?php

$domain = null;

if (isset($_SERVER['HTTP_HOST'])) {
    $domain = $_SERVER['HTTP_HOST'];
} elseif (isset($argv[2])) {
    $domain = $argv[2];
    //set http_host to domain
    $_SERVER['HTTP_HOST'] = $domain;
}

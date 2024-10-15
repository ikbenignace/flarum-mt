<?php

global $domain;
require __DIR__.'/vendor/autoload.php';
include __DIR__.'/domain.php';

return Flarum\Foundation\Site::fromPaths([
    'base' => __DIR__,
    'public' => __DIR__ . '/domains/' .$domain.'/public',
    'storage' => __DIR__ . '/domains/' .$domain.'/storage',
]);

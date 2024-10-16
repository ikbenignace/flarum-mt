<?php

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Yaml\Yaml;

$domain = null;
$argv = $argv ?? null;

if (($argv && !in_array('install', $argv) || isset($_SERVER['HTTP_HOST']))) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $domain = $_SERVER['HTTP_HOST'];
    } elseif (isset($argv[2])) {
        $domain = $argv[2];
        //set http_host to domain
        $_SERVER['HTTP_HOST'] = $domain;
    }
} else {
    if(!in_array('install', $argv)) {
        $input = new ArgvInput();
        if ($input->hasParameterOption('--file')) {
            $filePath = $input->getParameterOption('--file');
            if (file_exists($filePath)) {
                $configurationFileContents = file_get_contents($filePath);
                // Try parsing JSON
                if (($json = json_decode($configurationFileContents, true)) !== null) {
                    //Use JSON if Valid
                    $configuration = $json;
                } else {
                    //Else use YAML
                    $configuration = Yaml::parse($configurationFileContents);
                }

                $domain = $configuration['databaseConfiguration']['database'];
            } else {
                die('File not found: ' . $filePath);
            }
        }
    }
}

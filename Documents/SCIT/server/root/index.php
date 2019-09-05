<?php

/*Container holding dependencies*/
$managers = [];

/*assignment of default items to the container begins*/
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$managers['documentRoot'] = &$documentRoot;

$settings = json_decode(file_get_contents($documentRoot.'/server/root/adminSettings.json'),true);
if(!is_array($settings)){
    exit();
}

$settings['documentRoot'] = &$documentRoot;
$managers['adminSettings'] = &$settings;

ob_start(null,100);
date_default_timezone_set($settings['php_timezone']);
bcscale((int) $settings['bcscale']);

if($settings['php_debug']){
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

require "{$documentRoot}/src/vendor/autoload.php";

/*Launching super active utility*/
$utils = new \Scit\General\Utils($managers);
if($utils->launch()){
    require "{$documentRoot}/server/root/requests.php";
}

ob_end_flush();
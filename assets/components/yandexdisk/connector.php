<?php

$isWeb = isset($_REQUEST['action']) && strpos($_REQUEST['action'], 'web/') !== false;

if ($isWeb) {
    define('MODX_REQP', false);
}

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$corePath = $modx->getOption('yandexdisk.core_path', null, $modx->getOption('core_path') . 'components/yandexdisk/');

if ($isWeb) {
    $version = $modx->getVersionData();
    if ($modx->user->hasSessionContext($modx->context->get('key'))) {
        $_SERVER['HTTP_MODAUTH'] = $_SESSION['modx.' . $modx->context->get('key') . '.user.token'];
    } else {
        $_SESSION['modx.' . $modx->context->get('key') . '.user.token'] = 0;
        $_SERVER['HTTP_MODAUTH'] = 0;
    }
    $_REQUEST['HTTP_MODAUTH'] = $_SERVER['HTTP_MODAUTH'];
}

$path = $modx->getOption('processorsPath', null, $corePath . 'processors/');
$modx->request->handleRequest(['processors_path' => $path, 'location' => '']);

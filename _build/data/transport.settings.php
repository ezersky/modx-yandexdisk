<?php

/**
 * Add settings to package
 *
 * @package modx-yandexdisk
 * @subpackage build
 */

$settingsConfig = require_once $sources['config'] . 'settings.php';

$settings = [];

foreach ($settingsConfig as $name => $config) {
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray(
        array_merge(['key' => $name], $config),
        '',
        true,
        true
    );
    $settings[] = $setting;
}

return $settings;

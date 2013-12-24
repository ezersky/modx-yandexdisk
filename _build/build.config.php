<?php

define('PKG_NAME', 'YandexDiskProvider');
define('PKG_NAME_LOWER', strtolower(PKG_NAME));

define('PKG_VERSION', '1.0.0');
define('PKG_RELEASE', 'beta');
define('PKG_AUTO_INSTALL', true);
define('PKG_NAMESPACE_PATH', '{core_path}components/' . PKG_NAME_LOWER . '/');

//define('MODX_CORE_PATH', __DIR__ . '/../vendor/alroniks/modx-core/');

define('MODX_BASE_URL', '/');
define('MODX_CORE_URL', MODX_BASE_URL . 'core/');

define('BUILD_SETTING_UPDATE', false);

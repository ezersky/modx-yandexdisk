<?php

/**
 * Build script for package with media source for work with Yandex Disk Service
 *
 * @package modx-yandexdisk
 * @subpackage build
 */

set_time_limit(0);

define('PKG_NAME', 'YandexDisk');
define('PKG_NAME_LOWER', strtolower(PKG_NAME));
define('PKG_VERSION', '0.5.0');
define('PKG_RELEASE','alpha');

$root = dirname(__DIR__) . '/';
$sources = [
    'root' => $root,
    'build' => $root . '_build/',
    'data' => $root . '_build/data/',
    'config' => $root . '_build/config/',
    'resolvers' => $root . '_build/resolvers/',
    'lexicon' => $root . 'core/components/' . PKG_NAME_LOWER . '/lexicon/',
    'docs' => $root . 'core/components/' . PKG_NAME_LOWER . '/docs/',
    'core' => $root . 'core/components/' . PKG_NAME_LOWER,
    'assets' => $root . 'assets/components/' . PKG_NAME_LOWER
];

require_once $sources['build'] . 'build.config.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once $sources['build'] . 'includes/helpers.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->getService('error', 'error.modError');

$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(
    PKG_NAME_LOWER,
    false,
    true,
    '{core_path}' . join('/', ['components', PKG_NAME_LOWER, '']),
    '{assets_path}' . join('/', ['components', PKG_NAME_LOWER, ''])
);

$vehicle = $builder->createVehicle(new xPDOObject($modx), []);
// тут ошибка при сборке и это плохо
$vehicle->resolve(
    'file',
    [
        'source' => $sources['core'],
        'target' => "return MODX_CORE_PATH . 'components/';"
    ]
);
$vehicle->resolve(
    'file',
    [
        'source' => $sources['assets'],
        'target' => "return MODX_ASSETS_PATH . 'components/';"
    ]
);
$modx->log(modX::LOG_LEVEL_INFO, "Файлы добавлены в пакет");

foreach (['extensions'] as $resolver) {
    if ($vehicle->resolve('php', ['source' => $sources['resolvers'] . "resolve.$resolver.php"])) {
        $modx->log(modX::LOG_LEVEL_INFO, "Добавлен резолвер $resolver");
    } else {
        $modx->log(modX::LOG_LEVEL_INFO, "Не удалось добавить резолвер $resolver");
    }
}

$builder->putVehicle($vehicle);
$builder->setPackageAttributes(
    [
        'changelog' => file_get_contents($sources['docs'] . 'changelog.txt'),
        'license' => file_get_contents($sources['docs'] . 'license.txt'),
        'readme' => file_get_contents($sources['docs'] . 'readme.txt')
    ]
);
$modx->log(modX::LOG_LEVEL_INFO, "Добавлены атрибуты пакета");

$builder->pack();
$modx->log(modX::LOG_LEVEL_INFO, "Пакет заархивирован");

$signature = $builder->getSignature();
if (defined('PKG_AUTO_INSTALL') && PKG_AUTO_INSTALL) {
    $sig = explode('-', $signature);
    $versionSignature = explode('.', $sig[1]);

    if (!$package = $modx->getObject('transport.modTransportPackage', ['signature' => $signature])) {
        $package = $modx->newObject('transport.modTransportPackage');
        $package->set('signature', $signature);
        $package->fromArray(
            [
                'created' => date('Y-m-d H:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => 0,
                'source' => $signature . '.transport.zip',
                'package_name' => $sig[0],
                'version_major' => $versionSignature[0],
                'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
            ]
        );
        if (!empty($sig[2])) {
            $r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($r) && !empty($r)) {
                $package->set('release', $r[0]);
                $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
            } else {
                $package->set('release',$sig[2]);
            }
        }
        $package->save();
    }
    $package->install();
}
$modx->log(modX::LOG_LEVEL_INFO, "Пакет установлен");

$modx->cacheManager->refresh();
$modx->log(modX::LOG_LEVEL_INFO, "Кеш очищен");

exit();

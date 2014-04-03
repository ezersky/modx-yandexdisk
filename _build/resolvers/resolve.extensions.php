<?php

/**
 * Resolves core extensions
 *
 * @package yandexdisk
 * @subpackage build
 */

$modx = &$object->xpdo;

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $modelPath = $modx->getOption(
            'yandexdisk.core_path',
            null,
            $modx->getOption('core_path') . 'components/yandexdisk/' . 'model/'
        );
        if ($modx instanceof modX) {
            $modx->addExtensionPackage('yandexdisk', $modelPath);
        }
        break;
    case xPDOTransport::ACTION_UNINSTALL:
        $modelPath = $modx->getOption(
            'yandexdisk.core_path',
            null,
            $modx->getOption('core_path') . 'components/yandexdisk/' . 'model/'
        );
        if ($modx instanceof modX) {
            $modx->removeExtensionPackage('yandexdisk', $modelPath);
        }
        break;
}

return true;

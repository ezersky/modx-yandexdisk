<?php

$source = intval($modx->getOption('source', $scriptProperties, 0));
$path = trim($modx->getOption('path', $scriptProperties, '/'));

if ($source > 0 && !empty($path)) {
    /**
     * @var dropboxMediaSource $source
     */
    $media = $modx->getObject('sources.modMediaSource', $source);
    if (!empty($media)) {
        if ($media->initialize()) {
            $file = $media->getObjectContents($path);
        }
    }
    if (!empty($file['content'])) {
        header('Content-Disposition: attachment; filename=' . rawurlencode(basename($path)));
        header('Content-Type: ' . $file['mime']);

        return $file['content'];
    }
}

return;

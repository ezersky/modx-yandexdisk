<?php

require_once MODX_CORE_PATH . 'model/modx/sources/modmediasource.class.php';
require_once "phar://" . dirname(__DIR__) . "/yandexdisk/yandex-sdk-0.1.1.phar/vendor/autoload.php";

use Yandex\Disk\DiskClient;
use Yandex\Disk\DiskException;

class YandexDiskMediaSource extends modMediaSource implements modMediaSourceInterface
{
    /**
     * @var \Yandex\Disk\DiskClient
     */
    private $client;

    /**
     * @var array
     */
    private $propertyList;

    /**
     * Override the constructor to always force Yandex Disk sources to be streams
     * @param xPDO $xpdo
     */
    public function __construct(xPDO &$xpdo)
    {
        parent::__construct($xpdo);

        $this->set('is_stream', true);
        $this->xpdo->lexicon->load('yandexdisk:default');
    }

    /**
     * Initialize the source
     * @return boolean
     */
    public function initialize()
    {
        if (!parent::initialize()) {
            return false;
        }
        $this->propertyList = $this->getPropertyList();
        $this->client = new DiskClient($this->propertyList['token']);
        $this->client->setServiceScheme(DiskClient::HTTPS_SCHEME);

        return true;
    }

    /**
     * Return an array of containers at this current level in the container structure. Used for the tree navigation on the files tree
     * @param string $path
     * @return array
     */
    public function getContainerList($path)
    {
        try {
            $resources = $this->client->directoryContents($path);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'container.list', [
                        'path' => $path,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            return [];
        }

        $directories = [];
        $files = [];

        array_shift($resources);
        foreach ($resources as $resource) {

            $skiped = array_unique(
                array_map(
                    'trim',
                    explode(',', $this->getOption('skiped', $this->propertyList))
                )
            );

            if (in_array($resource['displayName'], $skiped)) {
                continue;
            }

            $hideFiles = !empty($this->propertyList['hideFiles']) && $this->propertyList['hideFiles'] != 'false' ? true : false;

            if ($resource['resourceType'] == 'dir' && $this->hasPermission('directory_list')) {

                $classes = ['folder'];
                if ($this->hasPermission('directory_chmod') && $this->checkPolicy('save')) {
                    $classes[] = 'pchmod';
                }
                if ($this->hasPermission('directory_create') && $this->checkPolicy('create')) {
                    $classes[] = 'pcreate';
                }
                if ($this->hasPermission('directory_remove') && $this->checkPolicy('remove')) {
                    $classes[] = 'premove';
                }
                if ($this->hasPermission('directory_update') && $this->checkPolicy('save')) {
                    $classes[] = 'pupdate';
                }
                if ($this->hasPermission('file_upload') && $this->checkPolicy('create')) {
                    $classes[] = 'pupload';
                }
                if ($this->hasPermission('file_create') && $this->checkPolicy('create')) {
                    $classes[] = 'pcreate';
                }

                $classes = implode(' ', array_unique($classes));

                $directories[$resource['displayName']] = [
                    'id' => $resource['href'],
                    'text' => $resource['displayName'],
                    'cls' => $classes,
                    'type' => 'dir',
                    'leaf' => false,
                    'path' => $resource['href'],
                    'pathRelative' => $resource['href'],
                    'perms' => '',
                    'menu' => [],
                ];
                $directories[$resource['displayName']]['menu'] = [
                    'items' => $this->getDirectoryContextMenu(),
                ];
            }
            if ($resource['resourceType'] == 'file' && $this->hasPermission('file_list') && !$hideFiles) {

                $classes = ['icon-file'];
                if ($this->hasPermission('file_remove') && $this->checkPolicy('remove')) {
                    $classes[] = 'premove';
                }
                if ($this->hasPermission('file_update') && $this->checkPolicy('save')) {
                    $classes[] = 'pupdate';
                }

                $classes[] = "icon-" . mb_strtolower(pathinfo($resource['displayName'], PATHINFO_EXTENSION));

                $ext = strtolower(@pathinfo($resource['displayName'], PATHINFO_EXTENSION));
                $images = explode(',', $this->getOption('images', $this->propertyList, 'jpg,jpeg,png,gif'));
                if (in_array($ext, $images)) {
                    if ($fileName = $this->getThumbnail($resource['href'], '150x150') ) {
                        $tip = '<img
                            src="' . $fileName . '"
                            alt="' . $resource['displayName'] . '" width="150" height="150" />';
                    }
                }

                $url = $this->getUrl($resource['href']);

                $action = $this->xpdo->getObject('modAction', ['controller' => 'system/file/edit']);
                $editAction = $action ? $action->get('id') : false;

                $page = !empty($editAction) ? '?a='.$editAction.'&file='.$resource['href'].'&wctx='.$this->ctx->get('key').'&source='.$this->get('id') : null;

                if (!empty($this->propertyList['currentFile'])
                    && rawurldecode($this->propertyList['currentFile']) == $resource['href']
                    && $this->propertyList['currentAction'] == $editAction) {
                    $classes[] = 'active-node';
                }

                $files[$resource['displayName']] = [
                    'id' => $resource['href'],
                    'text' => $resource['displayName'],
                    'lastmod' => strtotime($resource['lastModified']),
                    'size' => $resource['contentLength'],
                    'cls' => implode(' ', array_unique($classes)),
                    'type' => 'file',
                    'leaf' => true,
                    'qtip' => $tip ?: '',
                    'page' => explode('/', $resource['contentType'])[0] == 'text' ? $page : null,
                    'perms' => '',
                    'path' => $resource['href'],
                    'pathRelative' => $resource['href'],
                    'directory' => $path,
                    'url' => $resource['href'],
                    'file' => $this->ctx->getOption('base_url', MODX_BASE_URL) . $url,
                    'menu' => []
                ];
                $files[$resource['displayName']]['menu'] = [
                    'items' => $this->getFileContextMenu($files[$resource['displayName']]['page'])
                ];
            }
        }

        ksort($directories);
        ksort($files);

        return array_merge(
            array_values($directories),
            array_values($files)
        );
    }

    /**
     * Return a detailed list of objects in a specific path. Used for thumbnails in the Browser
     * @param string $path
     * @return array
     */
    public function getObjectsInContainer($path)
    {
        $items = $this->getContainerList($path);

        if (!$items) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'container.objects',
                    ['path' => $path],
                    'error'
                )
            );
            return [];
        }

        $files = [];
        foreach ($items as $item) {
            if ($item['type'] == 'dir') {
                continue;
            }

            $ext = strtolower(@pathinfo($item['text'], PATHINFO_EXTENSION));
            $images = explode(',', $this->getOption('images', $this->propertyList, 'jpg,jpeg,png,gif'));

            $thumb = $this->ctx->getOption('manager_url', MODX_MANAGER_URL) . 'templates/default/images/restyle/nopreview.jpg';
            if (in_array($ext, $images)) {
                if ($realThumb = $this->getThumbnail($item['path'], '80x60')) {
                    $thumb = $realThumb;
                }
            }

            $files[$item['id']] = array_merge(
                $item,
                [
                    'name' => $item['text'],
                    'relativeUrl' => $this->ctx->getOption('base_url', MODX_BASE_URL) . $this->getUrl($item['path']),
                    'fullRelativeUrl' => $item['path'],
                    'pathname' => $item['path'],
                    'ext' => $ext,
                    'thumb' => $thumb,
                    'thumbWidth' => $this->ctx->getOption('filemanager_thumb_width', 80),
                    'thumbHeight' => $this->ctx->getOption('filemanager_thumb_height', 60),
                    'menu' => [
                        [
                            'text' => $this->xpdo->lexicon('file_remove'),
                            'handler' => 'this.removeFile',
                        ]
                    ]
                ]
            );
        }

        return array_values($files);
    }

    /**
     * Create a container at the passed location with the passed name
     * @param string $name
     * @param string $parentContainer
     * @return boolean
     */
    public function createContainer($name, $parentContainer)
    {
        try {
            $path = rtrim($parentContainer, '/') . '/' . trim($name, '/');
            $this->client->createDirectory($path);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'container.create',
                    [
                        'path' => $path,
                        'message' => $e->getMessage()
                    ],
                    'error'
                )
            );
            $this->addError('name', $this->xpdo->lexicon('file_folder_err_create'));

            return false;
        }

        $this->xpdo->logManagerAction('directory_create', '', $path);

        return true;
    }

    /**
     * Remove the specified container
     * @param string $path
     * @return boolean
     */
    public function removeContainer($path)
    {
        try {
            $this->client->delete($path);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'container.remove',
                    [
                        'path' => $path,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            $this->addError('path', $this->xpdo->lexicon('file_folder_err_remove'));

            return false;
        }

        $this->xpdo->logManagerAction('directory_remove', '', $path);

        return true;
    }

    /**
     * Rename a container
     * @param string $old
     * @param string $new
     * @return boolean
     */
    public function renameContainer($old, $new)
    {
        try {
            $new = dirname($old) == '/'
                ? dirname($old) . $new
                : join('/', [dirname($old), $new]);
            $this->client->move($old, $new);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'container.rename',
                    [
                        'path' => $old,
                        'name' => $new,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            $this->addError('name', $this->xpdo->lexicon('file_folder_err_rename'));

            return false;
        }

        $this->xpdo->logManagerAction('directory_rename', '', $old);

        return true;
    }

    /**
     * Upload objects to a specific container
     * @param string $container
     * @param array $objects
     * @return boolean
     */
    public function uploadObjectsToContainer($container, array $objects = [])
    {
        $temporaryPath = $this->xpdo->getOption(xPDO::OPT_CACHE_PATH) . 'yandexdisk/' . uniqid() . '/';
        if (!file_exists($temporaryPath)) {
            $this->xpdo->cacheManager->writeTree($temporaryPath);
        }
        $this->xpdo->context->prepare();

        $allowedFileTypes = explode(',', $this->xpdo->getOption('upload_files', null, ''));
        $allowedFileTypes = array_merge(
            explode(',', $this->xpdo->getOption('upload_images')),
            explode(',', $this->xpdo->getOption('upload_media')),
            explode(',', $this->xpdo->getOption('upload_flash')),
            $allowedFileTypes
        );
        $allowedFileTypes = array_unique($allowedFileTypes);

        foreach ($objects as $file) {
            if ($file['error'] != UPLOAD_ERR_OK || empty($file['name'])) {
                continue;
            }
            $ext = @pathinfo($file['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext);
            if (empty($ext) || !in_array($ext, $allowedFileTypes)) {
                $this->addError('path', $this->xpdo->lexicon('file_err_ext_not_allowed', compact('ext')));
                continue;
            }

            $fileName = $temporaryPath . $file['name'];
            if (move_uploaded_file($file['tmp_name'], $fileName)) {
                try {
                    $file['path'] = $fileName;
                    $this->client->uploadFile(
                        $container == '/'
                            ? $container
                            : '/' . $container,
                        $file
                    );
                } catch (DiskException $e) {
                    $this->xpdo->log(
                        xPDO::LOG_LEVEL_ERROR,
                        $this->lexicon(
                            'container.upload',
                            [
                                'path' => $container,
                                'message' => $e->getMessage(),
                            ],
                            'error'
                        )
                    );
                    $this->addError('path', $this->xpdo->lexicon('file_err_upload'));
                    continue;
                }
            }
            @unlink($fileName);
        }
        @rmdir($temporaryPath);
        $this->xpdo->invokeEvent(
            'OnFileManagerUpload',
            [
                'files' => &$objects,
                'directory' => $container,
                'source' => &$this
            ]
        );

        $this->xpdo->logManagerAction('file_upload', '', $container);

        return true;
    }

    /**
     * Get the contents of an object
     * @param string $path
     * @return boolean
     */
    public function getObjectContents($path)
    {
        try {
            $file = $this->client->getFile($path);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.content',
                    [
                        'path' => $path,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );

            return [];
        }

        $images = explode(',', $this->getOption('images', $this->propertyList, 'jpg,jpeg,png,gif'));
        $ext = strtolower(@pathinfo($path, PATHINFO_EXTENSION));

        return [
            'name' => $path,
            'basename' => basename($path),
            'path' => $path,
            'size' => $file['headers']['Content-Length'],
            'last_accessed' => '',
            'last_modified' => $file['headers']['Last-Modified'],
            'content' => $file['body'],
            'mime' => $file['headers']['Content-Type'],
            'image' => in_array($ext, $images) ? true : false,
            'is_writable' => true,
            'is_readable' => true
        ];
    }

    /**
     * Update the contents of a specific object
     * @param string $path
     * @param string $content
     * @return boolean
     */
    public function updateObject($path, $content)
    {
        $name = basename($path);
        $path = dirname($path) == '/' ? '/' : dirname($path) . '/';

        $temporaryPath = $this->xpdo->getOption(xPDO::OPT_CACHE_PATH) . 'yandexdisk/' . uniqid() . '/';
        if (!file_exists($temporaryPath)) {
            $this->xpdo->cacheManager->writeTree($temporaryPath);
        }

        if (!file_put_contents($temporaryPath . $name, $content)) {
            $this->addError('file', $this->xpdo->lexicon('file_err_save'));
            return false;
        }

        try {
            $this->client->uploadFile(
                $path,
                [
                    'name' => $name,
                    'path' => $temporaryPath . $name,
                    'size' => filesize($temporaryPath . $name)
                ]
            );
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.update',
                    [
                        'path' => $path . $name,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            $this->addError('file', $this->xpdo->lexicon('file_err_save'));

            @unlink($temporaryPath . $name);
            @rmdir($temporaryPath);

            return false;
        }

        @unlink($temporaryPath . $name);
        @rmdir($temporaryPath);

        $this->xpdo->logManagerAction('file_update', '', $path);

        return rawurlencode($path);
    }

    /**
     * Create an object from a path
     * @param string $path
     * @param string $name
     * @param string $content
     * @return boolean|string
     */
    public function createObject($path, $name, $content)
    {
        $path = $path ? '/' . $path . '/' : '/';
        $name = basename($name);

        $temporaryPath = $this->xpdo->getOption(xPDO::OPT_CACHE_PATH) . 'yandexdisk/' . uniqid() . '/';
        if (!file_exists($temporaryPath)) {
            $this->xpdo->cacheManager->writeTree($temporaryPath);
        }

        if (!file_put_contents($temporaryPath . $name, $content)) {
            $this->addError('file', $this->xpdo->lexicon('file_err_create'));
            return false;
        }

        try {
            $this->client->uploadFile(
                $path,
                [
                    'name' => $name,
                    'path' => $temporaryPath . $name,
                    'size' => filesize($temporaryPath . $name)
                ]
            );
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.create',
                    [
                        'path' => $path . $name,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            $this->addError('file', $this->xpdo->lexicon('file_err_upload'));

            @unlink($temporaryPath . $name);
            @rmdir($temporaryPath);

            return false;
        }

        @unlink($temporaryPath . $name);
        @rmdir($temporaryPath);

        $this->xpdo->logManagerAction('file_create', '', $path . $name);

        return rawurlencode($path . $name);
    }

    /**
     * Remove an object
     * @param string $path
     * @return boolean
     */
    public function removeObject($path)
    {
        try {
            $this->client->delete($path);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.remove',
                    [
                        'path' => $path,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            $this->addError('file', $this->xpdo->lexicon('file_err_remove'));

            return false;
        }

        $this->xpdo->logManagerAction('file_remove', '', $path);

        return true;
    }

    /**
     * Rename a file/object
     * @param string $old
     * @param string $new
     * @return bool
     */
    public function renameObject($old, $new)
    {
        try {
            $new = dirname($old) == '/'
                ? dirname($old) . $new
                : join('/', [dirname($old), $new]);
            $this->client->move($old, $new);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.rename',
                    [
                        'path' => $old,
                        'name' => $new,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );
            $this->addError('name', $this->xpdo->lexicon('file_folder_err_rename'));

            return false;
        }

        $this->xpdo->logManagerAction('file_rename', '', $old);

        return true;
    }

    /**
     * Move a file or folder to a specific location
     * @param string $from The location to move from
     * @param string $to The location to move to
     * @param string $point The type of move; append, above, below
     * @return boolean
     */
    public function moveObject($from, $to, $point = 'append')
    {
        switch ($point) {
            case 'append':
                $to .= basename($from);
                break;
            case 'above':
            case 'below':
                $to = dirname($to) . basename($from);
                break;
        }

        try {
            $this->client->move($from, $to);
        } catch (DiskException $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.move',
                    [
                        'path' => $from,
                        'newPath' => $to,
                        'message' => $e->getMessage(),
                    ],
                    'error'
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Get the name of this source type
     * @return string
     */
    public function getTypeName()
    {
        return $this->lexicon('name');
    }

    /**
     * Get a short description of this source type
     * @return string
     */
    public function getTypeDescription()
    {
        return $this->lexicon('description');
    }

    /**
     * Get the default properties for this source. Override this in your custom source driver to provide custom
     * properties for your source type.
     * @return array
     */
    public function getDefaultProperties()
    {
        return [
            'token' => [
                'name' => 'token',
                'type' => 'textfield',
                'value' => '',
                'desc' => 'yandexdisk.prop.token',
                'lexicon' => 'yandexdisk:properties'
            ],
            'skiped' => [
                'name' => 'skiped',
                'type' => 'textfield',
                'value' => '.svn,.git,_notes,nbproject,.idea,.DS_Store',
                'desc' => 'yandexdisk.prop.skiped',
                'lexicon' => 'yandexdisk:properties'
            ],
            'images' => [
                'name' => 'images',
                'type' => 'textfield',
                'value' => 'jpg,jpeg,png,gif',
                'desc' => 'yandexdisk.prop.images',
                'lexicon' => 'yandexdisk:properties'
            ]
        ];
    }

    protected function getDirectoryContextMenu()
    {
        $menu = [];

        if ($this->hasPermission('directory_create') && $this->checkPolicy('create')) {
            $menu[] = [
                'text' => $this->xpdo->lexicon('file_folder_create_here'),
                'handler' => 'this.createDirectory'
            ];
        }
        if ($this->hasPermission('directory_update') && $this->checkPolicy('save')) {
            $menu[] = [
                'text' => $this->xpdo->lexicon('rename'),
                'handler' => 'this.renameDirectory'
            ];
        }
        $menu[] = [
            'text' => $this->xpdo->lexicon('directory_refresh'),
            'handler' => 'this.refreshActiveNode'
        ];
        if ($this->hasPermission('file_upload') && $this->checkPolicy('create')) {
            $menu[] = '-';
            $menu[] = [
                'text' => $this->xpdo->lexicon('upload_files'),
                'handler' => 'this.uploadFiles'
            ];
        }
        if ($this->hasPermission('directory_remove') && $this->checkPolicy('remove')) {
            $menu[] = '-';
            $menu[] = [
                'text' => $this->xpdo->lexicon('file_folder_remove'),
                'handler' => 'this.removeDirectory'
            ];
        }

        return $menu;
    }

    protected function getFileContextMenu($page)
    {
        $menu = [];

        if ($this->hasPermission('file_update') && $this->checkPolicy('save')) {
            $menu[] = [
                'text' => $this->xpdo->lexicon('rename'),
                'handler' => 'this.renameFile'
            ];
            if ($page) {
                $menu[] = [
                    'text' => $this->xpdo->lexicon('file_edit'),
                    'handler' => 'this.editFile',
                ];
                $menu[] = [
                    'text' => $this->xpdo->lexicon('quick_update_file'),
                    'handler' => 'this.quickUpdateFile',
                ];
            }
        }
        if ($this->hasPermission('file_view') && $this->checkPolicy('view')) {
            $menu[] = [
                'text' => $this->xpdo->lexicon('file_download'),
                'handler' => 'this.downloadFile'
            ];
        }
        if ($this->hasPermission('file_remove') && $this->checkPolicy('remove')) {
            if (!empty($menu)) {
                $menu[] = '-';
            }
            $menu[] = [
                'text' => $this->xpdo->lexicon('file_remove'),
                'handler' => 'this.removeFile'
            ];
        }

        return $menu;
    }

    /**
     * Prepare the source path for phpThumb
     * @param string $src
     * @return string
     */
    public function prepareSrcForThumb($src)
    {
        $src = $this->xpdo->getOption('assets_path', null, MODX_ASSETS_PATH)
            . 'components/yandexdisk/' . $this->get('id') . dirname($src) . '/' . '150x150-' . basename($src);

        return $src;
    }

    public function getThumbnail($path, $imageSize = '')
    {
        $size = $imageSize != '' ? $imageSize : 'XXXL';
        $realPath = 'components/yandexdisk/' . $this->get('id') . dirname($path) . '/' . $size . '-' . basename($path);

        $cachedFile = $this->xpdo->getOption('assets_path', null, MODX_ASSETS_PATH) . $realPath;
        $cachedFileUrl = $this->xpdo->getOption('assets_url', null, MODX_ASSETS_URL) . $realPath;

        if (file_exists($cachedFile)) {
            return $cachedFileUrl;
        }

        try {
            $file = $this->client->getImagePreview($path, $size);
            if ($this->xpdo->cacheManager->writeFile($cachedFile, $file['body'], 'ab')) {
                return $cachedFileUrl;
            }
        } catch (Exception $e) {
            $this->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                $this->lexicon(
                    'object.thumbnail',
                    [
                        'path' => $path,
                        'message' => $e->getMessage()
                    ],
                    'error'
                )
            );
        }

        return '';
    }

    protected function getUrl($path)
    {
        return join(
            '&',
            [
                'assets/components/yandexdisk/connector.php?source=' . $this->get('id'),
                http_build_query(['action' => 'web/view', 'path' => $path], '', '&')
            ]
        );
    }

    protected function lexicon($key, array $params = [], $category = 'source')
    {
        return $this->xpdo->lexicon("yandexdisk.$category.$key", $params);
    }
}

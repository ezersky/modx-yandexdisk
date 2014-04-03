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

    /*
     * done function getContainerList($path) { return array(); }
     * done function createContainer($name,$parentContainer) { return true; }
     * done function removeContainer($path) { return true; }
     * done function renameContainer($oldPath,$newName) { return true; }

    ??? public function updateContainer() { return true; }
    public function getObjectsInContainer($path) { return array(); }

    public function uploadObjectsToContainer($container,array $objects = array()) { return true; }
    public function getObjectContents($objectPath) { return true; }
    public function updateObject($objectPath,$content) { return true; }
    public function createObject($objectPath,$name,$content) { return true; }
    public function removeObject($objectPath) { return true; }
    public function renameObject($oldPath,$newName) { return true; }
    public function getBasePath($object = '') { return ''; }
    public function getBaseUrl($object = '') { return ''; }
    public function getObjectUrl($object = '') { return ''; }
    public function moveObject($from,$to,$point = 'append') { return true; }
    public function getDefaultProperties() { return array(); }
    */

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

        $properties = $this->getPropertyList();
        // нужно переписать проперти на нормальный лад в конструкторе
        $this->client = new DiskClient($properties['token']);
        $this->client->setServiceScheme(DiskClient::HTTPS_SCHEME);
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
                    explode(',', $this->getOption('skiped', $this->getPropertyList()))
                )
            );

            if (in_array($resource['displayName'], $skiped)) {
                continue;
            }

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
                    'items' => $this->getListContextMenu(true, $directories[$resource['displayName']]),
                ];
            }
            if ($resource['resourceType'] == 'file' && $this->hasPermission('file_list')) {

                $classes = ['icon-file'];
                if ($this->hasPermission('file_remove') && $this->checkPolicy('remove')) {
                    $classes[] = 'premove';
                }
                if ($this->hasPermission('file_update') && $this->checkPolicy('save')) {
                    $classes[] = 'pupdate';
                }

                $classes[] = "icon-" . mb_strtolower(pathinfo($resource['displayName'], PATHINFO_EXTENSION));
                $classes = implode(' ', array_unique($classes));

                if ($fileName = $this->getThumbnail($resource['href'])) {
                    $tip = '<img
                    src="' . $this->ctx->getOption('assets_url', MODX_ASSETS_URL) . $fileName . '"
                    alt="' . $resource['displayName'] . '" />';
                }

                $url = $this->getUrl($resource['href']);

                $files[$resource['displayName']] = [
                    'id' => $resource['href'],
                    'text' => $resource['displayName'],
                    'cls' => $classes,
                    'type' => 'file',
                    'leaf' => true,
                    'qtip' => $tip ?: '',
                    'page' => null,
                    'perms' => '',
                    'path' => $resource['href'],
                    'pathRelative' => $resource['href'],
                    'directory' => $path,
                    'url' => $url,
                    'file' => $this->ctx->getOption('base_url', MODX_BASE_URL) . $url,
                    'menu' => []
                ];
                $files[$resource['displayName']]['menu'] = [
                    'items' => $this->getListContextMenu(false, $files[$resource['displayName']])
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
		$response = null;
        
        echo 'rrrrr';
		try {
			$response = $this->client->getMetadata($path);
		} catch (Exception $e) {
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('objectsInContainer', array(
				'path' => $path,
				'message' => $e->getMessage(),
			), 'error'));
			return array();
		}
		if (!($response instanceof ResponseMetadata) || !isset($response->contents) || count($response->contents) == 0) {
			return array();
		}
		$useMultibyte = $this->ctx->getOption('use_multibyte', false);
		$encoding = $this->ctx->getOption('modx_charset', 'UTF-8');
		$allowedFileTypes = $this->getOption('allowedFileTypes', $this->properties, '');
		$allowedFileTypes = !empty($allowedFileTypes) && is_string($allowedFileTypes) ? explode(',', $allowedFileTypes) : $allowedFileTypes;
		$allowedFileTypes = is_array($allowedFileTypes) ? array_unique(array_filter(array_map('trim', $allowedFileTypes))) : array();
		$imageExtensions = $this->getOption('imageExtensions', $this->properties, 'jpg,jpeg,png,gif');
		$imageExtensions = explode(',', $imageExtensions);
		$skipFiles = array_unique(array_filter(array_map('trim', explode(',', $this->getOption('skipFiles', $this->properties, '.svn,.git,_notes,.DS_Store,nbproject,.idea')))));
		$files = array();
		foreach ($response->contents as $entry) {
			if ($entry->is_dir) {
				continue;
			}
			$entryPath = $entry->path;
			$fileName = basename($entryPath);
			if (in_array($fileName, $skipFiles)) {
				continue;
			}
			$ext = pathinfo($fileName, PATHINFO_EXTENSION);
			$ext = $useMultibyte ? mb_strtolower($ext, $encoding) : strtolower($ext);
			if (!empty($allowedFileTypes) && !in_array($ext, $allowedFileTypes)) {
				continue;
			}
			$objectUrl = $this->getUrl($entryPath);
			$thumbWidth = $this->ctx->getOption('filemanager_thumb_width', 80);
			$thumbHeight = $this->ctx->getOption('filemanager_thumb_height', 60);
			$file = array(
				'id' => $entryPath,
				'name' => $fileName,
				'url' => $entryPath,
				'relativeUrl' => $entryPath,
				'fullRelativeUrl' => $objectUrl,
				'ext' => $ext,
				'pathname' => $entryPath,
				'lastmod' => $entry->modified,
				'leaf' => true,
				'size' => $entry->bytes,
				'thumb' => $this->ctx->getOption('manager_url', MODX_MANAGER_URL) . 'templates/default/images/restyle/nopreview.jpg',
				'thumbWidth' => $thumbWidth,
				'thumbHeight' => $thumbHeight,
				'menu' => array(
					array(
						'text' => $this->xpdo->lexicon('file_remove'),
						'handler' => 'this.removeFile',
					),
				),
			);
			if (in_array($ext, $imageExtensions)) {
				$imageWidth = $this->ctx->getOption('filemanager_image_width', 400);
				$imageHeight = $this->ctx->getOption('filemanager_image_height', 300);
				/*$size = @getimagesize($objectUrl);
				if (is_array($size)) {
					$imageWidth = $size[0] > 800 ? 800 : $size[0];
					$imageHeight = $size[1] > 600 ? 600 : $size[1];
				}*/
				if ($thumbWidth > $imageWidth) {
					$thumbWidth = $imageWidth;
				}
				if ($thumbHeight > $imageHeight) {
					$thumbHeight = $imageHeight;
				}
				$objectUrl = urlencode($this->ctx->getOption('base_url', MODX_BASE_URL) . $objectUrl);
				$thumbUrl = '';
				if ($entry->thumb_exists) {
					$thumbUrl = $this->getThumbnail($entryPath);
				}
				$thumbUrl = !empty($thumbUrl) ? ($this->ctx->getOption('assets_url', MODX_ASSETS_URL) . $thumbUrl) : $objectUrl;
				$thumbParams = array(
					'f' => $this->getOption('thumbnailType', $this->properties, 'png'),
					'q' => $this->getOption('thumbnailQuality', $this->properties, 90),
					'HTTP_MODAUTH' => $this->xpdo->user->getUserToken($this->xpdo->context->get('key')),
					'wctx' => $this->ctx->get('key'),
					'source' => $this->get('id'),
				);
				$thumbQuery = http_build_query(array_merge($thumbParams, array(
					'src' => $thumbUrl,
					'w' => $thumbWidth,
					'h' => $thumbHeight,
				)));
				$imageQuery = http_build_query(array_merge($thumbParams, array(
					'src' => $objectUrl,
					'w' => $imageWidth,
					'h' => $imageHeight,
				)));
				$file['thumb'] = $this->ctx->getOption('connectors_url', MODX_CONNECTORS_URL) . 'system/phpthumb.php?' . urldecode($thumbQuery);
				$file['image'] = $this->ctx->getOption('connectors_url', MODX_CONNECTORS_URL) . 'system/phpthumb.php?' . urldecode($imageQuery);
			}
			$files[] = $file;
		}
		return $files;
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
	public function uploadObjectsToContainer($container, array $objects = array())
	{
		$tempPath = $this->xpdo->getOption(xPDO::OPT_CACHE_PATH) . 'yandexdisk/' . uniqid() . '/';
		if (!file_exists($tempPath)) {
			$this->xpdo->cacheManager->writeTree($tempPath);
		}
		$this->xpdo->context->prepare();
		$allowedFileTypes = explode(',', $this->xpdo->getOption('upload_files', null, ''));
		$allowedFileTypes = array_merge(explode(',', $this->xpdo->getOption('upload_images')), explode(',', $this->xpdo->getOption('upload_media')), explode(',', $this->xpdo->getOption('upload_flash')), $allowedFileTypes);
		$allowedFileTypes = array_unique($allowedFileTypes);
		foreach ($objects as $file) {
			if ($file['error'] != UPLOAD_ERR_OK || empty($file['name'])) {
				continue;
			}
			$ext = @pathinfo($file['name'], PATHINFO_EXTENSION);
			$ext = strtolower($ext);
			if (empty($ext) || !in_array($ext, $allowedFileTypes)) {
				$this->addError('path', $this->xpdo->lexicon('file_err_ext_not_allowed', array(
					'ext' => $ext,
				)));
				continue;
			}
			$fileName = $tempPath . $file['name'];
			$success = false;
			if (move_uploaded_file($file['tmp_name'], $fileName)) {
				$response = null;

				$container .= $file['name'];
				$response = $this->client->put_file('/'.$container, $fileName);

				if($response != 201){
					$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('uploadObjectsToContainer', array(
						'path' => $container,
						'message' => $e->getMessage(),
					), 'error'));
				}else{
					@unlink($fileName);
					$success = true;
				}
			}
			if (!$success) {
				$this->addError('path', $this->xpdo->lexicon('file_err_upload'));
				continue;
			}
		}
		@rmdir($tempPath);
		$this->xpdo->invokeEvent('OnFileManagerUpload', array(
			'files' => &$objects,
			'directory' => $container,
			'source' => &$this,
		));
		$this->xpdo->logManagerAction('file_upload', '', $container);
		return true;
	}

	/**
	 * Get the contents of an object
	 * @param string $objectPath
	 * @return boolean
	 */
	public function getObjectContents($objectPath)
	{
		$response = null;
		try {
			$response = $this->client->getMetadata($objectPath, false);
		} catch (Exception $e) {
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('getObjectContents', array(
				'path' => $objectPath,
				'message' => $e->getMessage(),
			), 'error'));
			return false;
		}
		if (!($response instanceof ResponseMetadata)) {
			return false;
		}
		$properties = $this->getPropertyList();
		$imageExtensions = $this->getOption('imageExtensions', $properties, 'jpg,jpeg,png,gif');
		$imageExtensions = explode(',', $imageExtensions);
		$fileExtension = pathinfo($objectPath, PATHINFO_EXTENSION);
		return array(
			'name' => $objectPath,
			'basename' => basename($objectPath),
			'path' => $objectPath,
			'size' => $response->size,
			'last_accessed' => '',
			'last_modified' => $response->modified,
			'content' => $this->getContent($objectPath),
			'image' => in_array($fileExtension, $imageExtensions) ? true : false,
			'is_writable' => false,
			'is_readable' => true,
		);
	}

	/**
	 * Update the contents of a specific object
	 * @param string $objectPath
	 * @param string $content
	 * @return boolean
	 */
	public function updateObject($objectPath, $content)
	{
		// TODO: Need to be implemented
		return true;
	}

	/**
	 * Create an object from a path
	 * @param string $objectPath
	 * @param string $name
	 * @param string $content
	 * @return boolean|string
	 */
	public function createObject($objectPath, $name, $content)
	{
		// TODO: Need to be implemented
		return true;
	}

	/**
	 * Remove an object
	 * @param string $objectPath
	 * @return boolean
	 */
	public function removeObject($objectPath)
	{
		$response = null;
		
		$response = $this->client->delete($objectPath);

		if($response['status'] != '200'){
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('removeObject', array(
				'path' => $objectPath,
				'message' => $e->getMessage(),
			), 'error'));
			$this->addError('file', $this->xpdo->lexicon('file_err_remove'));
			return false;
		}
		$this->xpdo->logManagerAction('file_remove', '', $objectPath);
		return true;
	}

	/**
	 * Rename a file/object
	 * @param string $oldPath
	 * @param string $newName
	 * @return bool
	 */
	public function renameObject($oldPath, $newName)
	{

		$response = null;

		if(dirname($oldPath) == '/') // root
			$response = $this->client->move($oldPath, dirname($oldPath) . $newName, 1);
		else
			$response = $this->client->move($oldPath, dirname($oldPath) .'/'. $newName, 1);

		

		if($response != 201){
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('removeObject', array(
				'path' => $oldPath,
				'name' => $newName,
				'message' => $e->getMessage(),
			), 'error'));
			$this->addError('name', $this->xpdo->lexicon('file_folder_err_rename'));
			return false;
		}
		$this->xpdo->logManagerAction('file_rename', '', $oldPath);
		return true;
	}

	/**
	 * Get the URL for an object in this source
	 * @param string $object
	 * @return string
	 */
	public function getObjectUrl($object = '')
	{
		return $this->xpdo->getOption('url_scheme', null, MODX_URL_SCHEME) . $this->xpdo->getOption('http_host', null, MODX_HTTP_HOST) . $this->xpdo->getOption('base_url', MODX_BASE_URL) . ($object == '' ? '' : $this->getUrl($object));
	}

	/**
	 * Prepares the output URL when the Source is being used in an Element
	 * @param string $value
	 * @return string
	 */
	public function prepareOutputUrl($value)
	{
		return $this->getObjectUrl($value);
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

		$response = null;

		if(dirname($to) == '/') // root
			$response = $this->client->move($from, $to . basename($from), 1);
		else
			$response = $this->client->move($from, $to . '/' . basename($from), 1);

		echo $from, '|',$to, '|',dirname($to);
		print_r($response);
		exit;
	
		if($response != 201){
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('moveObject', array(
				'path' => $from,
				'name' => $to,
				'message' => $e->getMessage(),
			), 'error'));
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

	public function getProperties()
	{
		$properties = parent::getProperties();
		//$this->processToken($properties);
		return $properties;
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
//            'imageExtensions' => [
//                'name' => 'imageExtensions',
//                'type' => 'textfield',
//                'value' => 'jpg,jpeg,png,gif',
//                'desc' => 'yandexdisk.prop.imageExtensions',
//                'lexicon' => 'yandexdisk:properties'
//            ],
//            'skipFiles' => [
//                'name' => 'skipFiles',
//                'type' => 'textfield',
//                'value' => '.svn,.git,_notes,nbproject,.idea,.DS_Store',
//                'desc' => 'yandexdisk.prop.skipFiles',
//                'lexicon' => 'yandexdisk:properties'
//            ]
        ];
	}

	public function getListContextMenu($isDir, array $fileArray)
	{
		$canSave = $this->checkPolicy('save');
		$canRemove = $this->checkPolicy('remove');
		$canCreate = $this->checkPolicy('create');
		$canView = $this->checkPolicy('view');
		$menu = array();
		if ($isDir) {
			if ($this->hasPermission('directory_create') && $canCreate) {
				$menu[] = array(
					'text' => $this->xpdo->lexicon('file_folder_create_here'),
					'handler' => 'this.createDirectory',
				);
			}
			if ($this->hasPermission('directory_update') && $canSave) {
				$menu[] = array(
					'text' => $this->xpdo->lexicon('rename'),
					'handler' => 'this.renameDirectory',
				);
			}
			$menu[] = array(
				'text' => $this->xpdo->lexicon('directory_refresh'),
				'handler' => 'this.refreshActiveNode',
			);
			if ($this->hasPermission('file_upload') && $canCreate) {
				$menu[] = '-';
				$menu[] = array(
					'text' => $this->xpdo->lexicon('upload_files'),
					'handler' => 'this.uploadFiles',
				);
			}
			if ($this->hasPermission('directory_remove') && $canRemove) {
				$menu[] = '-';
				$menu[] = array(
					'text' => $this->xpdo->lexicon('file_folder_remove'),
					'handler' => 'this.removeDirectory',
				);
			}
		} else {
			if ($this->hasPermission('file_update') && $canSave) {
				$menu[] = array(
					'text' => $this->xpdo->lexicon('rename'),
					'handler' => 'this.renameFile',
				);
			}
			if ($this->hasPermission('file_view') && $canView) {
				$menu[] = array(
					'text' => $this->xpdo->lexicon('file_download'),
					'handler' => 'this.downloadFile',
				);
			}
			if ($this->hasPermission('file_remove') && $canRemove) {
				if (!empty($menu)) {
					$menu[] = '-';
				}
				$menu[] = array(
					'text' => $this->xpdo->lexicon('file_remove'),
					'handler' => 'this.removeFile',
				);
			}
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
		if (strpos($src, $this->session->getUniqueKey() . '/thumbnails') !== false) {
			return $src;
		} else {
			if (strpos($src, $this->getConnectorUrl()) === false) {
				$src = $this->xpdo->getOption('url_scheme', null, MODX_URL_SCHEME) . $this->xpdo->getOption('http_host', null, MODX_HTTP_HOST) . $this->ctx->getOption('base_url', MODX_BASE_URL) . $this->getUrl($src);
			}
		}
		return $src;
	}

	public function getContent($path)
	{
		$fileName = $this->xpdo->getOption('assets_path', null, MODX_ASSETS_PATH) . $this->getCacheFileName($path, 'content');
		if (file_exists($fileName)) {
			return file_get_contents($fileName);
		}
		$content = '';
		try {
			$content = $this->client->getContent($path);
		} catch (Exception $e) {
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('getContent', array(
				'path' => $path,
				'message' => $e->getMessage(),
			), 'error'));
		}
		if (!empty($content)) {
			$this->xpdo->cacheManager->writeFile($fileName, $content);
		}
		return $content;
	}

	public function getThumbnail($path)
	{
        return ''; // заглушка пока что
		$fileName = $this->getCacheFileName($path);
		$filePath = $this->xpdo->getOption('assets_path', null, MODX_ASSETS_PATH) . $fileName;
		if (file_exists($filePath)) {
			return $fileName;
		}
		$content = '';
		try {
			$content = $this->client->getThumbnail($path, DropboxClient::THUMBNAIL_SIZE_LARGE);
		} catch (Exception $e) {
			$this->xpdo->log(xPDO::LOG_LEVEL_ERROR, $this->lexicon('getThumbnail', array(
				'path' => $path,
				'message' => $e->getMessage(),
			), 'error'));
		}
		if ($content) {
			if ($this->xpdo->cacheManager->writeFile($filePath, $content)) {
				return $fileName;
			}
		}
		return '';
	}

	public function getContentType($ext)
	{
		$contentType = 'application/octet-stream';
		$mimeTypes = array(
			'323' => 'text/h323',
			'acx' => 'application/internet-property-stream',
			'ai' => 'application/postscript',
			'aif' => 'audio/x-aiff',
			'aifc' => 'audio/x-aiff',
			'aiff' => 'audio/x-aiff',
			'asf' => 'video/x-ms-asf',
			'asr' => 'video/x-ms-asf',
			'asx' => 'video/x-ms-asf',
			'au' => 'audio/basic',
			'avi' => 'video/x-msvideo',
			'axs' => 'application/olescript',
			'bas' => 'text/plain',
			'bcpio' => 'application/x-bcpio',
			'bin' => 'application/octet-stream',
			'bmp' => 'image/bmp',
			'c' => 'text/plain',
			'cat' => 'application/vnd.ms-pkiseccat',
			'cdf' => 'application/x-cdf',
			'cer' => 'application/x-x509-ca-cert',
			'class' => 'application/octet-stream',
			'clp' => 'application/x-msclip',
			'cmx' => 'image/x-cmx',
			'cod' => 'image/cis-cod',
			'cpio' => 'application/x-cpio',
			'crd' => 'application/x-mscardfile',
			'crl' => 'application/pkix-crl',
			'crt' => 'application/x-x509-ca-cert',
			'csh' => 'application/x-csh',
			'css' => 'text/css',
			'dcr' => 'application/x-director',
			'der' => 'application/x-x509-ca-cert',
			'dir' => 'application/x-director',
			'dll' => 'application/x-msdownload',
			'dms' => 'application/octet-stream',
			'doc' => 'application/msword',
			'dot' => 'application/msword',
			'dvi' => 'application/x-dvi',
			'dxr' => 'application/x-director',
			'eps' => 'application/postscript',
			'etx' => 'text/x-setext',
			'evy' => 'application/envoy',
			'exe' => 'application/octet-stream',
			'fif' => 'application/fractals',
			'flr' => 'x-world/x-vrml',
			'gif' => 'image/gif',
			'gtar' => 'application/x-gtar',
			'gz' => 'application/x-gzip',
			'h' => 'text/plain',
			'hdf' => 'application/x-hdf',
			'hlp' => 'application/winhlp',
			'hqx' => 'application/mac-binhex40',
			'hta' => 'application/hta',
			'htc' => 'text/x-component',
			'htm' => 'text/html',
			'html' => 'text/html',
			'htt' => 'text/webviewhtml',
			'ico' => 'image/x-icon',
			'ief' => 'image/ief',
			'iii' => 'application/x-iphone',
			'ins' => 'application/x-internet-signup',
			'isp' => 'application/x-internet-signup',
			'jfif' => 'image/pipeg',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'js' => 'application/x-javascript',
			'latex' => 'application/x-latex',
			'lha' => 'application/octet-stream',
			'lsf' => 'video/x-la-asf',
			'lsx' => 'video/x-la-asf',
			'lzh' => 'application/octet-stream',
			'm13' => 'application/x-msmediaview',
			'm14' => 'application/x-msmediaview',
			'm3u' => 'audio/x-mpegurl',
			'man' => 'application/x-troff-man',
			'mdb' => 'application/x-msaccess',
			'me' => 'application/x-troff-me',
			'mht' => 'message/rfc822',
			'mhtml' => 'message/rfc822',
			'mid' => 'audio/mid',
			'mny' => 'application/x-msmoney',
			'mov' => 'video/quicktime',
			'movie' => 'video/x-sgi-movie',
			'mp2' => 'video/mpeg',
			'mp3' => 'audio/mpeg',
			'mpa' => 'video/mpeg',
			'mpe' => 'video/mpeg',
			'mpeg' => 'video/mpeg',
			'mpg' => 'video/mpeg',
			'mpp' => 'application/vnd.ms-project',
			'mpv2' => 'video/mpeg',
			'ms' => 'application/x-troff-ms',
			'mvb' => 'application/x-msmediaview',
			'nws' => 'message/rfc822',
			'oda' => 'application/oda',
			'p10' => 'application/pkcs10',
			'p12' => 'application/x-pkcs12',
			'p7b' => 'application/x-pkcs7-certificates',
			'p7c' => 'application/x-pkcs7-mime',
			'p7m' => 'application/x-pkcs7-mime',
			'p7r' => 'application/x-pkcs7-certreqresp',
			'p7s' => 'application/x-pkcs7-signature',
			'pbm' => 'image/x-portable-bitmap',
			'pdf' => 'application/pdf',
			'pfx' => 'application/x-pkcs12',
			'pgm' => 'image/x-portable-graymap',
			'pko' => 'application/ynd.ms-pkipko',
			'pma' => 'application/x-perfmon',
			'pmc' => 'application/x-perfmon',
			'pml' => 'application/x-perfmon',
			'pmr' => 'application/x-perfmon',
			'pmw' => 'application/x-perfmon',
			'pnm' => 'image/x-portable-anymap',
			'pot' => 'application/vnd.ms-powerpoint',
			'ppm' => 'image/x-portable-pixmap',
			'pps' => 'application/vnd.ms-powerpoint',
			'ppt' => 'application/vnd.ms-powerpoint',
			'prf' => 'application/pics-rules',
			'ps' => 'application/postscript',
			'pub' => 'application/x-mspublisher',
			'qt' => 'video/quicktime',
			'ra' => 'audio/x-pn-realaudio',
			'ram' => 'audio/x-pn-realaudio',
			'ras' => 'image/x-cmu-raster',
			'rgb' => 'image/x-rgb',
			'rmi' => 'audio/mid',
			'roff' => 'application/x-troff',
			'rtf' => 'application/rtf',
			'rtx' => 'text/richtext',
			'scd' => 'application/x-msschedule',
			'sct' => 'text/scriptlet',
			'setpay' => 'application/set-payment-initiation',
			'setreg' => 'application/set-registration-initiation',
			'sh' => 'application/x-sh',
			'shar' => 'application/x-shar',
			'sit' => 'application/x-stuffit',
			'snd' => 'audio/basic',
			'spc' => 'application/x-pkcs7-certificates',
			'spl' => 'application/futuresplash',
			'src' => 'application/x-wais-source',
			'sst' => 'application/vnd.ms-pkicertstore',
			'stl' => 'application/vnd.ms-pkistl',
			'stm' => 'text/html',
			'svg' => 'image/svg+xml',
			'sv4cpio' => 'application/x-sv4cpio',
			'sv4crc' => 'application/x-sv4crc',
			't' => 'application/x-troff',
			'tar' => 'application/x-tar',
			'tcl' => 'application/x-tcl',
			'tex' => 'application/x-tex',
			'texi' => 'application/x-texinfo',
			'texinfo' => 'application/x-texinfo',
			'tgz' => 'application/x-compressed',
			'tif' => 'image/tiff',
			'tiff' => 'image/tiff',
			'tr' => 'application/x-troff',
			'trm' => 'application/x-msterminal',
			'tsv' => 'text/tab-separated-values',
			'txt' => 'text/plain',
			'uls' => 'text/iuls',
			'ustar' => 'application/x-ustar',
			'vcf' => 'text/x-vcard',
			'vrml' => 'x-world/x-vrml',
			'wav' => 'audio/x-wav',
			'wcm' => 'application/vnd.ms-works',
			'wdb' => 'application/vnd.ms-works',
			'wks' => 'application/vnd.ms-works',
			'wmf' => 'application/x-msmetafile',
			'wps' => 'application/vnd.ms-works',
			'wri' => 'application/x-mswrite',
			'wrl' => 'x-world/x-vrml',
			'wrz' => 'x-world/x-vrml',
			'xaf' => 'x-world/x-vrml',
			'xbm' => 'image/x-xbitmap',
			'xla' => 'application/vnd.ms-excel',
			'xlc' => 'application/vnd.ms-excel',
			'xlm' => 'application/vnd.ms-excel',
			'xls' => 'application/vnd.ms-excel',
			'xlt' => 'application/vnd.ms-excel',
			'xlw' => 'application/vnd.ms-excel',
			'xof' => 'x-world/x-vrml',
			'xpm' => 'image/x-xpixmap',
			'xwd' => 'image/x-xwindowdump',
			'z' => 'application/x-compress',
			'zip' => 'application/zip'
		);
		if (isset($mimeTypes[strtolower($ext)])) {
			$contentType = $mimeTypes[$ext];
		}
		return $contentType;
	}

	protected function getConnectorUrl()
	{
		if ($this->connectorUrl == null) {
			$id = $this->get('id');
			if ($id <= 0) {
				$id = $this->get('source');
			}
			$this->connectorUrl = 'assets/components/yandexdisk/connector.php?source=' . $id;
		}
		return $this->connectorUrl;
	}

	protected function getUrl($path)
	{
		return $this->getConnectorUrl() . '&' . http_build_query(array(
			'action' => 'web/view',
			'path' => $path,
		), '', '&');
	}

	protected function getCacheFileName($path, $type = 'thumbnail')
	{
		$ext = 'jpg';
		if ($type != 'thumbnail') {
			$ext = @pathinfo($path, PATHINFO_EXTENSION);
			if (empty($ext)) {
				$ext = 'bin';
			}
		}
		$hash = md5(trim($path, '/'));
		return 'components/yandexdisk/' . $this->session->getUniqueKey() . '/' . $type . 's/' . substr($hash, 0, 2) . '/' . $hash . '.' . $ext;
	}

	protected function lexicon($key, array $params = [], $category = 'source')
	{
		return $this->xpdo->lexicon("yandexdisk.$category.$key", $params);
	}
}

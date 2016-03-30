<?php

namespace OCA\Files_Sharing\API;

abstract class ShareExecutor
{
	protected $meta;
	protected $versionMeta;
	
	protected $shareInfo;
	protected $backend;
	
	protected $itemSource;
	protected $itemSourceName;
	protected $itemType;
	protected $itemTarget;
	protected $fileTarget;
	protected $fileSource;
	protected $shareWith;
	protected $shareType;
	protected $permissions;
	protected $uidOwner;
	protected $shareWithinGroupOnly;
	protected $parent;
	protected $filePath;
	protected $token;
	
	protected $path;
	
	public function __construct($path)
	{
		$view = new \OC\Files\View('/'.\OCP\User::getUser().'/files');
		$this->meta = $view->getFileInfo($path);
		
		$this->itemType = $this->meta['mimetype'] === 'httpd/unix-directory' ? 'folder' : 'file';
		
		if($this->itemType === 'file')
		{
			$this->versionMeta = \OC\Files\ObjectStore\EosUtil::getVersionFolderFromFileId($this->meta['fileid']);
		}
		else
		{
			$this->versionMeta = $this->meta;
		}
		
		$this->itemSource = $this->versionMeta['fileid'];
		
		if($this->itemSource === null) 
		{
			throw new \Exception("wrong path, file/folder doesn't exist.");
		}
		
		$this->shareWith = isset($_POST['shareWith']) ? $_POST['shareWith'] : null;
		$this->shareType = isset($_POST['shareType']) ? (int)$_POST['shareType'] : null;
		
		$this->loadPreviousShareData();
	}
	
	protected function getFilePath()
	{
		$fakeRoot = '/' . \OC_User::getUser() . '/files';
		$manager = \OC\Files\Filesystem::getMountManager();
		$mounts = $manager->findIn($fakeRoot);
		$mounts[] = $manager->find($fakeRoot);
		// reverse the array so we start with the storage this view is in
		// which is the most likely to contain the file we're looking for
		$mounts = array_reverse($mounts);
		$internalPath = $this->versionMeta['path'];
		foreach ($mounts as $mount) {
			/**
			 * @var \OC\Files\Mount\MountPoint $mount
			 */
			if ($mount->getStorage()) {
				if (is_string($internalPath)) {
					$fullPath = $mount->getMountPoint() . $internalPath;
					if (!is_null($path = $this->getRelativePath($fakeRoot, $fullPath))) {
						return $path;
					}
				}
			}
		}
		return null;
	}
	
	protected function getRelativePath($fakeRoot, $path) 
	{
		$this->assertPathLength($path);
		if ($fakeRoot == '') 
		{
			return $path;
		}
	
		if (rtrim($path,'/') === rtrim($fakeRoot, '/')) 
		{
			return '/';
		}
	
		if (strpos($path, $fakeRoot) !== 0) 
		{
			return null;
		} 
		else 
		{
			$path = substr($path, strlen($fakeRoot));
			if (strlen($path) === 0) 
			{
				return '/';
			} 
			else 
			{
				return $path;
			}
		}
	}
	
	private function assertPathLength($path) {
		$maxLen = min(PHP_MAXPATHLEN, 4000);
		// Check for the string length - performed using isset() instead of strlen()
		// because isset() is about 5x-40x faster.
		if (isset($path[$maxLen])) {
			$pathLen = strlen($path);
			throw new \OCP\Files\InvalidPathException("Path length($pathLen) exceeds max path length($maxLen): $path");
		}
	}
	
	public function checkFileIntegrity()
	{
		$this->backend = \OCP\Share::getBackend($this->itemType);
		$l = \OC::$server->getL10N('lib');
		
		if ($this->backend->isShareTypeAllowed($this->shareType) === false) {
			$message = 'Sharing %s failed, because the backend does not allow shares from type %i';
			$message_t = $l->t('Sharing %s failed, because the backend does not allow shares from type %i', array($this->itemSourceName, $this->shareType));
			\OC_Log::write('OCP\Share', sprintf($message, $this->itemSourceName, $this->shareType), \OC_Log::ERROR);
			throw new \Exception($message_t);
		}
		
		$this->uidOwner = \OC_User::getUser();
		$this->shareWithinGroupOnly = \OC\Share\Share::shareWithGroupMembersOnly();
		
		if (is_null($this->itemSourceName)) {
			$this->itemSourceName = $this->itemSource;
		}
		
		return true;
	}
	
	public function getInsertResult()
	{
		return $this->token;
	}
	
	protected abstract function loadPreviousShareData();
	
	public abstract function checkForPreviousShares();
	
	public abstract function checkShareTarget();
	
	public abstract function insertShare();
}
<?php

namespace OCA\Files_Sharing\API;

class LinkShareExecutor extends ShareExecutor
{
	const SHARE_TYPE = 3;
	
	private $publicUploadEnabled;
	private $publicUpload;
	
	private $expirationDate;
		
	public function __construct($path)
	{
		parent::__construct($path);
		
		//allow password protection
		$this->shareWith = isset($_POST['password']) ? $_POST['password'] : null;
		//check public link share
		$this->publicUploadEnabled = \OC::$server->getAppConfig()->getValue('core', 'shareapi_allow_public_upload', 'yes') === 'yes'? TRUE : FALSE;
		if(isset($_POST['publicUpload']) && !$this->publicUploadEnabled) {
			throw new \Exception("public upload disabled by the administrator");
		}
		
		$this->publicUpload = isset($_POST['publicUpload']) ? ($_POST['publicUpload'] === 'true'? TRUE : FALSE) : FALSE;
		// read, create, update (7) if public upload is enabled or
		// read (1) if public upload is disabled
		$this->permissions = $this->publicUpload? 7 : 1;
		
		$this->parent = NULL;
	}
	
	protected function loadPreviousShareData()
	{
		$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE item_source = ? AND share_type = 3');
		$result = $query->execute([$this->itemSource]);
		$this->shareInfo = $result->fetchRow();
	}
	
	public function checkFileIntegrity()
	{
		if(!parent::checkFileIntegrity())
		{
			return false;
		}
		
		$l = \OC::$server->getL10N('lib');
		
		// check if file can be shared
		if ($this->itemType === 'file' or $this->itemType === 'folder') {
		
			$this->path = $this->getFilePath();//\OC\Files\Filesystem::getPath($this->itemSource);
			// verify that the file exists before we try to share it
			if (!$this->path) {
				$message = 'Sharing %s failed, because the file does not exist';
				$message_t = $l->t('Sharing %s failed, because the file does not exist', array($this->itemSourceName));
				\OC_Log::write('OCP\Share', sprintf($message, $this->itemSourceName), \OC_Log::ERROR);
				throw new \Exception($message_t);
			}
			// verify that the user has share permission
			if (\OC_Util::isSharingDisabledForUser()) {
				$message = 'You are not allowed to share %s';
				$message_t = $l->t('You are not allowed to share %s', array($this->itemSourceName));
				\OC_Log::write('OCP\Share', sprintf($message, $this->itemSourceName), \OC_Log::ERROR);
				throw new \Exception($message_t);
			}
			
			if ($this->itemType === 'folder') {
				$path = '/' . $this->uidOwner . '/files' . $this->path . '/';
				$mountManager = \OC\Files\Filesystem::getMountManager();
				$mounts = $mountManager->findIn($path);
				foreach ($mounts as $mount) {
					if ($mount->getStorage()->instanceOfStorage('\OCA\Files_Sharing\ISharedStorage')) {
						$message = 'Sharing "' . $this->itemSourceName . '" failed, because it contains files shared with you!';
						\OC_Log::write('OCP\Share', $message, \OC_Log::ERROR);
						throw new \Exception($message);
					}
			
				}
			}
			// single file shares should never have delete permissions
			else {
				$this->permissions = (int)$this->permissions & ~\OCP\Constants::PERMISSION_DELETE;
			}
		}
		
		return true;
	}
	
	public function checkForPreviousShares()
	{
		$updateExistingShare = false;
		if (\OC::$server->getAppConfig()->getValue('core', 'shareapi_allow_links', 'yes') == 'yes') {
		
			// when updating a link share
			// FIXME Don't delete link if we update it
			if ($this->shareInfo) 
			{
				// remember old token
				$oldToken = $this->shareInfo['token'];
				$oldPermissions = $this->shareInfo['permissions'];
				//delete the old share
				\OC\Share\Helper::delete($this->shareInfo['id']);
				$updateExistingShare = true;
			}
		
			// Generate hash of password - same method as user passwords
			if (!empty($this->shareWith)) {
				$this->shareWith = \OC::$server->getHasher()->hash($this->shareWith);
			} else {
				// reuse the already set password, but only if we change permissions
				// otherwise the user disabled the password protection
				if ($this->shareInfo && (int)$this->permissions !== (int)$oldPermissions) {
					$this->shareWith = $this->shareInfo['share_with'];
				}
			}

			if (\OCP\Util::isPublicLinkPasswordRequired() && empty($this->shareWith)) {
				$message = 'You need to provide a password to create a public link, only protected links are allowed';
				$message_t = $l->t('You need to provide a password to create a public link, only protected links are allowed');
				\OC_Log::write('OCP\Share', $message, \OC_Log::ERROR);
				throw new \Exception($message_t);
			}

			if ($updateExistingShare === false && \OC\Share\Share::isDefaultExpireDateEnabled() && empty($this->expirationDate)) 
			{
				$this->expirationDate = \OC\Share\Helper::calcExpireDate();
			}

			// Generate token
			if (isset($oldToken)) {
				$this->token = $oldToken;
			} else {
				$this->token = \OC::$server->getSecureRandom()->getMediumStrengthGenerator()->generate(\OC\Share\Share::TOKEN_LENGTH,
						\OCP\Security\ISecureRandom::CHAR_LOWER.\OCP\Security\ISecureRandom::CHAR_UPPER.
						\OCP\Security\ISecureRandom::CHAR_DIGITS);
			}
			
			return true;
		}
		
		$message = 'Sharing %s failed, because sharing with links is not allowed';
		$message_t = $l->t('Sharing %s failed, because sharing with links is not allowed', array($this->itemSourceName));
		\OC_Log::write('OCP\Share', sprintf($message, $this->itemSourceName), \OC_Log::ERROR);
		throw new \Exception($message_t);
	}
	
	private function getDefaultShareData()
	{
		$l = \OC::$server->getL10N('lib');
		$result = array();
		
		$result['parent'] = null;
		$result['suggestedItemTarget'] = null;
		$result['suggestedFileTarget'] = null;
		$result['itemSource'] = $this->itemSource;
		$result['expirationDate'] = $this->expirationDate;
		if (!$this->path) {
			$message = 'Sharing %s failed, because the sharing backend for '
					.'%s could not find its source';
					$message_t = $l->t('Sharing %s failed, because the sharing backend for %s could not find its source', array($this->itemSource, $this->itemType));
					\OC_Log::write('OCP\Share', sprintf($message, $this->itemSource, $this->itemType), \OC_Log::ERROR);
					throw new \Exception($message_t);
		}
		if ($this->backend instanceof \OCP\Share_Backend_File_Dependent) {
			$result['filePath'] = $this->path;//$this->backend->getFilePath($this->itemSource, $this->uidOwner);
			
			if ($this->itemType == 'file' || $this->itemType == 'folder') {
				$result['fileSource'] = $this->itemSource;
			} else {
				$meta = \OC\Files\Filesystem::getFileInfo($result['filePath']);
				$result['fileSource'] = $meta['fileid'];
			}
			if ($result['fileSource'] == -1) {
				$message = 'Sharing %s failed, because the file could not be found in the file cache';
				$message_t = $l->t('Sharing %s failed, because the file could not be found in the file cache', array($this->itemSource));
	
				\OC_Log::write('OCP\Share', sprintf($message, $this->itemSource), \OC_Log::ERROR);
				throw new \Exception($message_t);
			}
		} else {
			$result['filePath'] = null;
			$result['fileSource'] = null;
		}
		
		return $result;
	}
	
	public function checkShareTarget()
	{
		return true;
	}
	
	public function insertShare()
	{
		$queriesToExecute = array();
		$suggestedItemTarget = null;
		$suggestedFileTarget = null;
		
		$result = $this->getDefaultShareData();
		if(!empty($result)) {
			$this->parent = $result['parent'];
			$this->itemSource = $result['itemSource'];
			$this->fileSource = $result['fileSource'];
			$suggestedItemTarget = $result['suggestedItemTarget'];
			$suggestedFileTarget = $result['suggestedFileTarget'];
			$this->filePath = $result['filePath'];
			$this->expirationDate = $result['expirationDate'];
		}
		
		$this->itemTarget = $suggestedItemTarget;
		$this->fileTarget = $suggestedFileTarget;
		
		$queriesToExecute[] = array(
				'itemType'			=> $this->itemType,
				'itemSource'		=> $this->itemSource,
				'itemTarget'		=> $this->itemTarget,
				'shareType'			=> 3,
				'shareWith'			=> $this->shareWith,
				'uidOwner'			=> $this->uidOwner,
				'permissions'		=> $this->permissions,
				'shareTime'			=> time(),
				'fileSource'		=> $this->fileSource,
				'fileTarget'		=> $this->fileTarget,
				'token'				=> $this->token,
				'parent'			=> $this->parent,
				'expiration'		=> $this->expirationDate,
		);
		
		$id = false;
		
		foreach ($queriesToExecute as $shareQuery) {
			$id = $this->writeToDisk($shareQuery);
		}
		
		return $id ? $id : false;
	}
	
	private function writeToDisk(array $shareData)
	{
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*share` ('
				.' `item_type`, `item_source`, `item_target`, `share_type`,'
				.' `share_with`, `uid_owner`, `permissions`, `stime`, `file_source`,'
				.' `file_target`, `token`, `parent`, `expiration`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
		$query->bindValue(1, $shareData['itemType']);
		$query->bindValue(2, $shareData['itemSource']);
		$query->bindValue(3, $shareData['itemTarget']);
		$query->bindValue(4, $shareData['shareType']);
		$query->bindValue(5, $shareData['shareWith']);
		$query->bindValue(6, $shareData['uidOwner']);
		$query->bindValue(7, $shareData['permissions']);
		$query->bindValue(8, $shareData['shareTime']);
		$query->bindValue(9, $shareData['fileSource']);
		$query->bindValue(10, $shareData['fileTarget']);
		$query->bindValue(11, $shareData['token']);
		$query->bindValue(12, $shareData['parent']);
		$query->bindValue(13, $shareData['expiration'], 'datetime');
		$result = $query->execute();
		
		$id = false;
		if ($result) {
			$id =  \OC::$server->getDatabaseConnection()->lastInsertId();
			// Fallback, if lastInterId() doesn't work we need to perform a select
			// to get the ID (seems to happen sometimes on Oracle)
			if (!$id) {
				$getId = \OC_DB::prepare('
					SELECT `id`
					FROM`*PREFIX*share`
					WHERE `uid_owner` = ? AND `item_target` = ? AND `item_source` = ? AND `stime` = ?
					');
				$r = $getId->execute(array($shareData['uidOwner'], $shareData['itemTarget'], $shareData['itemSource'], $shareData['shareTime']));
				if ($r) {
					$row = $r->fetchRow();
					$id = $row['id'];
				}
			}
		
		}
		
		return $id;
	}
}
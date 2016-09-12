<?php

namespace OCA\Files_Sharing\API;

class UserShareExecutor extends ShareExecutor
{
	public function __construct($path)
	{
		parent::__construct($path);
	
		$this->permissions = isset($_POST['permissions']) ? (int)$_POST['permissions'] : 31;
	}
	
	protected function loadPreviousShareData()
	{
	
	}
	
	public function checkFileIntegrity()
	{
		return true;//parent::checkFileIntegrity();
	}
	
	public function checkForPreviousShares()
	{
		\OC\ShareUtil::checkParentDirShared($this->meta, false);
		
		return true;
	}
	
	public function checkShareTarget()
	{
		$ocBasePath = dirname($this->meta['path']);
		if($ocBasePath !== 'files')
		{
			return false;
		}
		return true;
	}
	
	public function insertShare()
	{
		$this->token = \OCP\Share::shareItem(
			$this->itemType,
			$this->itemSource,
			$this->shareType,
			$this->shareWith,
			$this->permissions
			);
	}
}
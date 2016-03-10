<?php

namespace OCA\Files_Sharing\API;

class GroupShareExecutor extends ShareExecutor
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
	
	public abstract function checkForPreviousShares()
	{
		return true;
	}
	
	public abstract function checkShareTarget()
	{
		return true;
	}
	
	public abstract function insertShare()
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
<?php

namespace OC\Cernbox\ShareEngine;

use OCP\Share\IShare;
use OC\Cernbox\Storage\EosUtil;

abstract class CernboxShareProvider 
{
	/** @var AbstractProvider */
	protected $masterProvider;
	
	public function __construct(AbstractProvider $provider)
	{
		$this->masterProvider = $provider;
	}
	
	/**
	 * Creates an IShare object holding all the share information
	 * @param array $rawData Raw EOS Metadata array
	 * @return IShare[] object LIST holding all the information of the share 
	 * 			(1 object per different user/group to which it was shared)
	 */
	public abstract function createShare(array $rawData);
	
	/**
	 * Check if a given file has been already shared. If so, will update the share to
	 * include the new recipents
	 * @param IShare $share the data for the new share
	 * @return bool True if the share already exists, false otherwise
	 */
	public abstract function updateShareAttributes(IShare $share);
	
	/**
	 * Sets the share attributes to the shared file's version folder (Share type, expiration, token, ACL)
	 * @param IShare $share object holding all information about the share
	 * @param string $symLinkEosPath path to the symlink that points to the shared file. If null, will be deducted from the
	 * 			share object data
	 */
	public abstract function setShareAttributes(IShare $share, $symLinkEosPath = null);
	
	/**
	 * Performs the necessary actions to delete a share of the type this provider handles. Returns
	 * true if the symlink should be deleted (the file is not shared anymore to anyone)
	 * @param IShare $share object holding all share information
	 * @return bool True if the symlink to the share should be deleted, false otherwise
	 */
	public abstract function shouldShareBeDelete(IShare $share);
	
	/**
	 * Peforms the necessary actions to create a share to a new recipent (whether the file
	 * was shared previously to someone else or not)
	 * @param IShare $share object holding all share information
	 */
	public abstract function shareeTargetCreation(IShare $share);
	
	/**
	 * Returns the name of the folder, (which should be located under the general eos share prefix)
	 * where the symlinks of the shares, of the type this provider handles, should be located
	 * @return string the name of the symlink container folder for this share type
	 */
	public abstract function getShareOwnerDestFolder();
	
	/**
	 * Builds the EOS path to this share owner's symlink on EOS
	 * @param IShare $share object holding all share information
	 * @return string the EOS path to the owner's share symlink
	 */
	public final function buildSharePath(IShare $share)
	{
		$eosSharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$owner = $share->getShareOwner();
				
		return ($eosSharePrefix . '/' . substr($owner, 0, 1) . '/' . $owner . '/'
				. $this->getShareOwnerDestFolder() . '/' . trim($share->getTarget(), '/'));
	}
}
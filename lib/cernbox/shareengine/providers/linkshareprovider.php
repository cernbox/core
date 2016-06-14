<?php

namespace OC\Cernbox\ShareEngine\Providers;

use OC\Cernbox\ShareEngine\AbstractProvider;
use OC\Cernbox\ShareEngine\CernboxShareProvider;
use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosParser;
use OCP\Share\IShare;
use OC\Cernbox\ShareEngine\ShareUtil;

final class LinkShareProvider extends CernboxShareProvider 
{
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\CernboxShareProvider::__construct()
	 */
	public function __construct(AbstractProvider $provider)
	{
		parent::__construct($provider);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::updateShareAttributes()
	 */
	public function updateShareAttributes(IShare $share)
	{
		$symLinkPath = $this->buildSharePath($share);
	
		$eosMeta = EosUtil::getFileByEosPath($symLinkPath);
		if(!$eosMeta) // Cannot locate the symlink, so the share does not exist
		{
			return false;
		}
	
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::setShareAttributes()
	 */
	public function setShareAttributes(IShare $share, $symLinkEosPath = null)
	{
		if($symLinkEosPath === null)
		{
			$symLinkEosPath = $this->buildSharePath($share);
		}
	
		if(!EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_type', \OCP\Share::SHARE_TYPE_LINK))
		{
			throw new \Exception('Could not set custom extended attributes when sharing ' . $symLinkEosPath);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::doShareDelete()
	 */
	public function doShareDelete(IShare $share)
	{
		$sharePath = $this->buildSharePath($share);
		$eosMeta = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($sharePath) 
		{ 
			return EosUtil::getFileByEosPath($sharePath);
		});
		
		//The symlink does not exist, we dont have to delete anything
		if(!$eosMeta)
		{
			return false;
		}
	
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::shareeTargetCreation()
	 */
	public function shareeTargetCreation(IShare $share)
	{
		$token = $share->getToken();
		$folder = ShareUtil::calcTokenHash($token);
		$sharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$globalLinkFolder = \OC::$server->getConfig()->getSystemValue('share_global_link_folder', 'global_links');
		
		
		$globalLinkPath = $sharePrefix . '/' . $globalLinkFolder . '/' . $folder . '/' . $token;
		$sharePath = $this->buildSharePath($share);
		
		if(!EosUtil::createSymLink($globalLinkPath, $sharePath))
		{
			throw new \Exception('Could not create global symlink for ' . $sharePath);
		}
		
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::getShareOwnerDestFolder()
	 */
	public function getShareOwnerDestFolder()
	{
		return \OC::$server->getConfig()->getSystemValue('share_type_link_folder', 'link_shares');
	}
}
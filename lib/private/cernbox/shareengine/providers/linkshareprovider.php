<?php

namespace OC\Cernbox\ShareEngine\Providers;

use OC\Cernbox\ShareEngine\AbstractProvider;
use OC\Cernbox\ShareEngine\CernboxShareProvider;
use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosParser;
use OCP\Share\IShare;
use OC\Cernbox\ShareEngine\ShareUtil;
use OC\Cernbox\ShareEngine\CernboxShare;

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
	 * @see \OC\Cernbox\ShareEngine\CernboxShareProvider::createShare()
	 */
	public function createShare(array $rawData)
	{
		$share = new CernboxShare($this->masterProvider->rootFolder);
		$share->setId((int)$rawData['fileid'])
		->setShareType(\OCP\Share::SHARE_TYPE_LINK)
		->setPermissions((int)$rawData['permissions'])
		->setTarget(trim($rawData['path'], '/'))
		->setMailSend(true);
		
		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$rawData['share_stime']);
		$share->setShareTime($shareTime);
		
		$share->setPassword($rawData['share_password']);
		$share->setToken($rawData['token']);
		
		if (!isset($rawData['uid_initiator']) || $rawData['uid_initiator'] === null)
		{
			//OLD SHARE
			$share->setSharedBy($rawData['uid_owner']);
			$path = $this->getNode($share->getSharedBy(), $rawData['fileid']);
		
			$owner = $path->getOwner();
			$share->setShareOwner($owner->getUID());
		}
		else
		{
			//New share!
			$share->setSharedBy($rawData['uid_initiator']);
			$share->setShareOwner($rawData['uid_owner']);
		}
		
		$share->setNodeId($rawData['fileid']);
		$share->setNodeType($rawData['eostype']);
		
		if ($rawData['expiration'] !== null)
		{
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $rawData['expiration']);
			$share->setExpirationDate($expiration);
		}
		
		$share->setProviderId($this->identifier());
		
		return $share;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::updateShareAttributes()
	 */
	public function updateShareAttributes(IShare $share)
	{
		$symLinkPath = $this->buildSharePath($share);
	
		$eosMeta = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use($symLinkPath)
		{
			return EosUtil::getFileByEosPath($symLinkPath);
		});
		
		if(!$eosMeta) // Cannot locate the symlink, so the share does not exist
		{
			return false;
		}
		
		if($share->getExpirationDate()->getTimestamp() !== $eosMeta['share_expiration'])
		{
			if(ShareUtil::validateExpirationDate($share))
			{
				if(!EosUtil::setExtendedAttribute($symLinkPath, 'cernbox.share_expiration', $share->getExpirationDate()->getTimestamp()))
				{
					throw new \Exception('Could not set custom extended attributes [cernbox.share_expiration] when sharing ' . $symLinkPath);
				}
			}
		}
		
		if($share->getPassword() !== $eosMeta['share_password'])
		{
			if(!EosUtil::setExtendedAttribute($symLinkPath, 'cernbox.share_password', $share->getPassword()))
			{
				throw new \Exception('Could not set custom extended attributes [cernbox.share_password] when sharing ' . $symLinkPath);
			}
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
		
		if(!EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_stime', $share->getShareTime()))
		{
			throw new \Exception('Could not set custom extended attributes [cernbox.share_stime] when sharing ' . $symLinkEosPath);
		}
		
		if(ShareUtil::validateExpirationDate($share) 
				&& !EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_expiration', $share->getExpirationDate()->getTimestamp()))
		{
			throw new \Exception('Could not set custom extended attributes [cernbox.share_expiration] when sharing ' . $symLinkEosPath);
		}
		
		if($share->getPassword() !== '' && !EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_password', $share->getPassword()))
		{
			throw new \Exception('Could not set custom extended attributes [cernbox.share_password] when sharing ' . $symLinkEosPath);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::shouldShareBeDelete()
	 */
	public function shouldShareBeDelete(IShare $share)
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
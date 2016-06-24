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
		$share = new CernboxShare($this->masterProvider->getRootFolder());
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
		
		if ($rawData['share_expiration'] !== '0')
		{
			$temp = new \DateTime();
			$temp->setTimestamp($rawData['share_expiration']);
			$share->setExpirationDate($temp);
		}
		
		$share->setProviderId($this->masterProvider->identifier());
		
		$share->setId($this->generateUniqueId($share));
		
		return [$share];
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::updateShareAttributes()
	 */
	public function updateShareAttributes(IShare $share)
	{
		$symLinkPath = $this->buildSharePath($share);
	
		$eosMeta = EosParser::parseShare(function() use($symLinkPath)
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
		
		if(!EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_stime', $share->getShareTime()->getTimestamp()))
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
		
		if(!EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_token', $share->getToken()))
		{
			throw new \Exception('Could not set custom extended attributes [cernbox.share_token] when sharing ' . $symLinkEosPath);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::shouldShareBeDelete()
	 */
	public function shouldShareBeDelete(IShare $share)
	{
		$sharePath = $this->buildSharePath($share);
		$eosMeta = EosParser::parseShare(function() use ($sharePath) 
		{ 
			return EosUtil::getFileByEosPath($sharePath);
		});
		
		//The symlink does not exist, we dont have to delete anything
		if(!$eosMeta)
		{
			return false;
		}
		
		$globalLinkFolder = ShareUtil::getGlobalLinksFolder();
		$sharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$tokenHash = ShareUtil::calcTokenHash($share->getToken());
		$globalLinkPath = $sharePrefix . '/' . $globalLinkFolder . '/' . $tokenHash . '/' . $share->getToken();
		
		if(!EosUtil::removeSymLink($globalLinkPath))
		{
			\OCP\Util::writeLog('SHARE ENGINE', 'Link share: Could not delete link global share link ' . $globalLinkPath, \OCP\Util::ERROR);
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
		$globalLinkFolder = ShareUtil::getGlobalLinksFolder();
		
		$globalLinkPath = $sharePrefix . '/' . $globalLinkFolder . '/' . $folder . '/' . $token;
		$sharePath = $this->masterProvider->buildFileEosPath($share);
		
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
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\CernboxShareProvider::generateUniqueId()
	 */
	public function generateUniqueId($share)
	{
		$fileId = $share->getNodeId();
		$token = $share->getToken();
		
		return $token.':'.$fileId;
	}
}
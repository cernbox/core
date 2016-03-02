<?php

namespace OCA\Files_Sharing\API;

use OC\Files\ObjectStore\EosUtil;
use OC\Files\ObjectStore\EosProxy;
class CustomLocal 
{
	private static function param($key)
	{
		if(isset($_GET[$key]))
			return $_GET[$key];
		
		return FALSE;
	}
	
	/**
	 * OCS API Entry to get information about shares
	 * https://doc.owncloud.org/server/8.0/developer_manual/core/ocs-share-api.html
	 * @param string[] $params
	 */
	public static function getAllShares($params) 
	{
		$path = self::param('path');
		// If a path is specified, get information about specific shares
		if($path !== FALSE)
		{
			// Get all shared files from a specific folder. 'path' points to the folder
			if(self::param('subfiles') === 'true')
			{
				return self::getSharedFilesInFolder($path);
			}
			// Get information about a specific share. 'path' points to the folder/file we want to know about
			else
			{
				return self::getSharedFileInfo($path);
			}
		}
		// Otherwise, get all shares by this user
		else
		{
			return self::getAllFilesSharedByMe();
		}
	}
	
	/**
	 * OCS API Entry to get information about a share specified by it's share ID (oc_share table key)
	 * @param string[] $params Array of parameters. Must contain key 'id'
	 */
	public static function getShare($params)
	{
		$id = isset($params['id'])? $params['id'] : null;
		if($id !== null)
		{
			$username = \OCP\User::getUser();
			try
			{
				// CACHE STORAGE ID
				$queryStorages = \OC_DB::prepare('SELECT numeric_id FROM oc_storages WHERE id = ?');
				$resultStorages = $queryStorages->execute(['object::user:'.$username]);
				$storageId = $resultStorages->fetchRow()['numeric_id'];
					
				$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE id = ?');
				$result = $query->execute([$id]);
					
				$row = $result->fetchRow();
				
				// Only return information if the asker is the same person who shared the file
				if($row['uid_owner'] === $username)
				{
					$versionMeta = EosUtil::getFileById($row['file_source']);
					
					$dirname = dirname($versionMeta['eospath']);
					$basename = basename($versionMeta['eospath']);
					if($row['item_type'] === 'file')
						$realfile = $dirname . "/" . substr($basename, 8);
					else
						$realfile = $dirname . "/" . $basename;
					
					$eosMeta = EosUtil::getFileByEosPath($realfile);
					
					
					$row['path'] = substr(EosProxy::toOc($eosMeta['eospath']), 5);
					$row['file_target'] = $row['item_type'] === 'file' ? $eosMeta['name'] : "/" . $eosMeta['name'];
					$row['storage'] = $storageId;
					unset($row['accepted']);
					unset($row['item_target']);
					$row['share_with_displayname'] = (isset($row['share_with']) && !empty($row['share_with'])) ? \OCP\User::getDisplayName($row['share_with']) : '';
					$row['displayname_owner'] = \OCP\User::getDisplayName($row['uid_owner']);
					
					return new \OC_OCS_Result([$row]);
				}
			}
			catch(Exception $e)
			{
				\OCP\Util::writeLog('files_sharing', 'OCS API: Failed to get file shared by ' .$username . ' with ID ' .$id. ': ' . $e->getMessage(), \OCP\Util::ERROR);
			}
		}
		
		return new \OC_OCS_Result([]);
	}
	
	/**
	 * OCS API Entry point to create a share 
	 * @param String[] $params
	 */
	public static function createShare($params)
	{
		$path = isset($_POST['path']) ? $_POST['path'] : null;
		
		if($path === null) {
			return new \OC_OCS_Result(null, 400, "please specify a file or folder path");
		}
		
		$shareType = isset($_POST['shareType']) ? (int)$_POST['shareType'] : null;
		
		/** @var ShareExecutor */
		$executor = null;
		
		switch($shareType) {
			case \OCP\Share::SHARE_TYPE_USER:
				$executor = new UserShareExecutor($path);
				break;
			case \OCP\Share::SHARE_TYPE_GROUP:
				$executor = new GroupShareExecutor($path);
				break;
			case \OCP\Share::SHARE_TYPE_LINK:
				$executor = new LinkShareExecutor($path);
				break;
			default:
				return new \OC_OCS_Result(null, 400, "unknown share type");
		}
		
		$token = 0;
		
		try	{
			if(!$executor->checkFileIntegrity())
			{
				throw new \Exception('Check file integrity failed');
			}
			else if(!$executor->checkForPreviousShares())
			{
				throw new \Exception('Check previous shares failed');
			}
			else if(!$executor->checkShareTarget())
			{
				throw new \Exception('Check share target failed');
			}
			
			$executor->insertShare();
			$token = $executor->getInsertResult();
			
		} catch (\Exception $e) {
			return new \OC_OCS_Result(null, 403, $e->getMessage());
		}
		
		if($token)
		{
			$data = [];
			$username = \OCP\User::getUser();
			$queryStorages = \OC_DB::prepare('SELECT numeric_id FROM oc_storages WHERE id = ?');
			$resultStorages = $queryStorages->execute(['object::user:'.$username]);
			$storageId = $resultStorages->fetchRow()['numeric_id'];
			
			$rows = [];
			if(is_string($token))
			{
				$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE token = ?');
				$result = $query->execute([$token]);
				$rows = $result->fetchAll();
			}
			else
			{
				$rows = self::getAllFilesSharedByMe();
			}
				
			foreach($rows as $key => $row)
			{
				// Only return information if the asker is the same person who shared the file
				if($token === $row['token'] || ($share['share_with'] === $shareWith && $share['share_type'] === $shareType))
				{
					$data['id'] = $row['id'];	
					if(is_string($token))
					{
						$url = \OCP\Util::linkToPublic('files&t='.$token);
						$data['url'] = $url; // '&' gets encoded to $amp;
						$data['token'] = $token;
					}
						
					return new \OC_OCS_Result([$data]);
				}
			}
		}
		else
		{
			return new \OC_OCS_Result(null, 403, 'Couldn not share the file');
		}
	}
	
	// ###########################################################
	// Auxiliar functions
	// ###########################################################
	
	/**
	 * Return the information of all the shares made by the user issueing the request
	 */
	private static function getAllFilesSharedByMe()
	{
		$username = \OCP\User::getUser();
		try
		{
			// CACHE STORAGE ID
			$queryStorages = \OC_DB::prepare('SELECT numeric_id FROM oc_storages WHERE id = ?');
			$resultStorages = $queryStorages->execute(['object::user:'.$username]);
			$storageId = $resultStorages->fetchRow()['numeric_id'];
			
			$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE uid_owner = ?');
			$result = $query->execute([$username]);
			
			$rows = $result->fetchAll();
			
			foreach($rows as $key => $row)
			{
				$eosMeta = EosUtil::getFileMetaFromVersionsFolderID($row['file_source']);
				//$row['item_source'] = $eosMeta['fileid'];
				//$row['file_source'] = $eosMeta['fileid'];
				$row['path'] = substr(EosProxy::toOc($eosMeta['eospath']), 5);
				$row['file_target'] = $eosMeta['name'];
				$row['storage'] = $storageId;
				unset($row['accepted']);
				unset($row['item_target']);
				$row['mimetype'] = $eosMeta['mimetype'];
				$row['share_with_displayname'] = (isset($row['share_with']) && !empty($row['share_with'])) ? \OCP\User::getDisplayName($row['share_with']) : '';
				
				$rows[$key] = $row;
			}
			
			return new \OC_OCS_Result($rows);
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('files_sharing', 'OCS API: Failed to get all files shared by the user ' .$username . ': ' . $e->getMessage(), \OCP\Util::ERROR);
		}
		
		return new \OC_OCS_Result([]);
	}
	
	/**
	 * Returns the share information of all the files shared within a folder
	 * @param string $path OC path to the folder where we want to look for shares
	 */
	private static function getSharedFilesInFolder($path)
	{
		$username = \OCP\User::getUser();
		$view = new \OC\Files\View('/'.$username.'/files');
		
		if(!$view->is_dir($path)) 
		{
			return new \OC_OCS_Result(null, 400, "not a directory");
		}
		
		$eosPath = EosProxy::toEos('files'.$path, 'object::user:'.$username);
		
		try
		{
			// CACHE STORAGE ID
			$queryStorages = \OC_DB::prepare('SELECT numeric_id FROM oc_storages WHERE id = ?');
			$resultStorages = $queryStorages->execute(['object::user:'.$username]);
			$storageId = $resultStorages->fetchRow()['numeric_id'];
				
			$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE uid_owner = ?');
			$result = $query->execute([$username]);
				
			$rows = $result->fetchAll();
				
			foreach($rows as $key => $row)
			{
				// CHECK IF THE FILE IS INSIDE THE GIVEN FOLDER
				$versionMeta = EosUtil::getFileById($row['file_source']);
				$dirname = dirname($versionMeta['eospath']);
				
				if(strpos($eosPath, $dirname) === FALSE)
				{
					unset($rows[$key]);
					continue;
				}
				
				$basename = basename($versionMeta['eospath']);
				
				if($row['item_type'] === 'file')
					$realfile = $dirname . "/" . substr($basename, 8);
					else
				$realfile = $dirname . "/" . $basename;
				
				//$realfile = $dirname . "/" . substr($basename, 8);
				
				$eosMeta = EosUtil::getFileByEosPath($realfile);
				
				//$row['item_source'] = $eosMeta['fileid'];	// TESTING VERSION FILE ID
				//$row['file_source'] = $eosMeta['fileid'];	// TESTING VERSION FILE ID
				$row['path'] = substr(EosProxy::toOc($eosMeta['eospath']), 5);
				$row['file_target'] = $eosMeta['name'];
				$row['storage'] = $storageId;
				unset($row['accepted']);
				unset($row['item_target']);
				$row['mimetype'] = $eosMeta['mimetype'];
				$row['share_with_displayname'] = (isset($row['share_with']) && !empty($row['share_with'])) ? \OCP\User::getDisplayName($row['share_with']) : '';
				
				$rows[$key] = $row;
			}
				
			return new \OC_OCS_Result($rows);
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('files_sharing', 'OCS API: Failed to get all files shared by the user ' .$username . ' in the folder ' . $path .': ' . $e->getMessage(), \OCP\Util::ERROR);
		}
		
		return new \OC_OCS_Result([]);
	}
	
	/**
	 * Get the share information of a single file
	 * @param string $path OC path to the file we want to retrieve the share information
	 */
	private static function getSharedFileInfo($path)
	{
		$username = \OCP\User::getUser();
		$view = new \OC\Files\View('/'.$username.'/files');
		
		$eosPath = EosProxy::toEos('files' . $path, 'object::user:'.$username);
		$originalEosMeta = EosUtil::getFileByEosPath($eosPath);
		
		try
		{
			$dirname = dirname($eosPath);
			$basename = basename($eosPath);
			$versionFolder = $dirname . "/.sys.v#." . $basename;
			
			$eosMeta = EosUtil::getFileByEosPath($versionFolder, false);
			
			if($eosMeta === null)
			{
				return new \OC_OCS_Result(null, 400, 'the file is not shared ' .$eosPath);
			}
			
			// CACHE STORAGE ID
			$queryStorages = \OC_DB::prepare('SELECT numeric_id FROM oc_storages WHERE id = ?');
			$resultStorages = $queryStorages->execute(['object::user:'.$username]);
			$storageId = $resultStorages->fetchRow()['numeric_id'];
				
			$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE item_source = ?');
			$result = $query->execute([$eosMeta['fileid']]);
				
			$rows = $result->fetchAll();
				
			foreach($rows as $key => $row)
			{	
				//$row['item_source'] = $eosMeta['fileid'];	// TESTING VERSION FILE ID
				//$row['file_source'] = $eosMeta['fileid'];	// TESTING VERSION FILE ID
				$row['path'] = $path;
				$row['file_target'] = $basename;
				$row['storage'] = $storageId;
				unset($row['accepted']);
				unset($row['item_target']);
				$row['displayname_owner'] = \OCP\User::getDisplayName($row['uid_owner']);
				$row['share_with_displayname'] = (isset($row['share_with']) && !empty($row['share_with'])) ? \OCP\User::getDisplayName($row['share_with']) : '';
				
				$rows[$key] = $row;
			}
				
			return new \OC_OCS_Result($rows);
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('files_sharing', 'OCS API: Failed to get all files shared by the user ' .$username . ' in the folder ' . $path .': ' . $e->getMessage(), \OCP\Util::ERROR);
		}
		
		return new \OC_OCS_Result([]);
	}
	
	// ####################################################################################################################
}
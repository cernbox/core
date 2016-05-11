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
		$sharedWithMe = self::param('shared_with_me');
		if($sharedWithMe === 'true')
		{
			return self::getAllFilesSharedWithMe();	
		}
		
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
					
					if(strpos($eosMeta['eospath'], EosUtil::getEosRecycleDir()) !== FALSE)
					{
						return new \OC_OCS_Result(null, 404, 'the requested file has been deleted');
					}
					
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
		$id = false;
		try	
		{
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
			
			$id = $executor->insertShare();
			$token = $executor->getInsertResult();
			
		} catch (\Exception $e) {
			return new \OC_OCS_Result(null, 403, $e->getMessage());
		}
		
		if($token)
		{
			$data = [];
			$data['id'] = $id;
			if(is_string($token))
			{
				$url = \OCP\Util::linkToPublic('files&t='.$token);
				$data['url'] = $url; // '&' gets encoded to $amp;
				$data['token'] = $token;
			}
			
			return new \OC_OCS_Result($data);
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
				if($row['expiration'] != null)
				{
					$timeStamp = strtotime($row['expiration']);
					// The share expired
					if($timeStamp < time())
					{
						unset($rows[$key]);
						continue;
					}
				}
				
				$row['id'] = (int)$row['id'];
				$row['file_source'] = (int)$row['file_source'];
				$row['stime'] = (int)$row['stime'];
				$row['permissions'] = (int)$row['permissions'];
				$row['share_type'] = (int)$row['share_type'];
				
				if($row['item_type'] === 'file')
				{
					$eosMeta = EosUtil::getFileMetaFromVersionsFolderID($row['file_source']);
				}
				else 
				{
					$eosMeta = EosUtil::getFileById($row['file_source']);
				}
				
				if($eosMeta == null)
				{
					unset($rows[$key]);
					continue;
				}
				
				if(strpos($eosMeta['eospath'], EosUtil::getEosRecycleDir()) !== FALSE)
				{
					unset($rows[$key]);
					continue;
				}
				
				//$row['item_source'] = $eosMeta['fileid'];
				//$row['file_source'] = $eosMeta['fileid'];
				$row['path'] = substr(EosProxy::toOc($eosMeta['eospath']), 5);
				if(!$row['path'])
				{
					unset($rows[$key]);
					continue;
				}
				$row['file_target'] = $eosMeta['name'];
				$row['storage'] = (int)$storageId;
				$row['eospath'] = $eosMeta['eospath'];
				unset($row['accepted']);
				$row['mimetype'] = $eosMeta['mimetype'];
				$row['share_with_displayname'] = (isset($row['share_with']) && !empty($row['share_with'])) ? \OCP\User::getDisplayName($row['share_with']) : '';
				$row['displayname_owner'] = \OCP\User::getDisplayName($row['uid_owner']);
				
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
	
	private static function getAllFilesSharedWithMe()
	{
		$username = \OCP\User::getUser();
		try
		{	
			$groups = \OC\LDAPCache\LDAPCacheManager::getUserEGroups($username);//OC_Group::getUserGroups($username);
			$groupsLen = count($groups);
			$placeHolder = "";
			for($i = 0; $i < $groupsLen; $i++)
			{
				$placeHolder .= "?,";
			}
			
			$placeHolder .= "?";
			$groups[] = $username;
			
			foreach($groups as $group)
			{
				\OCP\Util::writeLog('CUSTOM', 'Group: ' .$group, \OCP\Util::ERROR);
			}
				
			$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE share_with IN (' .$placeHolder .')');
			$result = $query->execute($groups);
				
			$rows = $result->fetchAll();
				
			foreach($rows as $key => $row)
			{
				if($row['expiration'] != null)
				{
					$timeStamp = strtotime($row['expiration']);
					// The share expired
					if($timeStamp < time())
					{
						unset($rows[$key]);
						continue;
					}
				}
				
				// CACHE STORAGE ID
				$queryStorages = \OC_DB::prepare('SELECT numeric_id FROM oc_storages WHERE id = ?');
				$resultStorages = $queryStorages->execute(['object::user:'.$row['uid_owner']]);
				$storageId = $resultStorages->fetchRow()['numeric_id'];
				
				$row['id'] = (int)$row['id'];
				$row['file_source'] = (int)$row['file_source'];
				$row['stime'] = (int)$row['stime'];
				$row['permissions'] = (int)$row['permissions'];
				$row['share_type'] = (int)$row['share_type'];
				
				if($row['item_type'] === 'file')
				{
					$eosMeta = EosUtil::getFileMetaFromVersionsFolderID($row['file_source']);
				}
				else 
				{
					$eosMeta = EosUtil::getFileById($row['file_source']);
				}
				
				if($eosMeta == null)
				{
					unset($rows[$key]);
					continue;
				}
				
				if(strpos($eosMeta['eospath'], EosUtil::getEosRecycleDir()) !== FALSE)
				{
					unset($rows[$key]);
					continue;
				}
				
				//$row['item_source'] = $eosMeta['fileid'];
				//$row['file_source'] = $eosMeta['fileid'];
				$row['path'] = EosProxy::toOc($eosMeta['eospath']);
				$row['storage'] = (int)$storageId;
				$row['eospath'] = $eosMeta['eospath'];
				unset($row['accepted']);
				if(strpos($eosMeta['eospath'], EosUtil::getEosProjectPrefix()) === 0)
				{
					$row['file_target'] = '/  project ' . $eosMeta['name'];
					$row['project_share'] = true;
					$row['projectname'] = EosUtil::getProjectNameForUser($row["uid_owner"]);
					
					$readers = $row;
					$readers['share_with'] = 'cernbox-project-'.$row['projectname'].'-readers';
					$readers['permissions'] = 1;
					
					$writers = $row;
					$writers['share_with'] = 'cernbox-project-'.$row['projectname'].'-writers';
					$writers['permissions'] = 15;
					
					$row['grouped'] = [];
					$row['grouped'][] = $readers;
					$row['grouped'][] = $writers;
					
				}
				else 
				{
					$row['project_share'] = false;
					$row['file_target'] = '/' . $eosMeta['name'] . ' (#' . $eosMeta['fileid'] . ')';
				}
				$row['mimetype'] = $eosMeta['mimetype'];
				$row['share_with_displayname'] = (isset($row['share_with']) && !empty($row['share_with'])) ? \OCP\User::getDisplayName($row['share_with']) : '';
				$row['displayname_owner'] = \OCP\User::getDisplayName($row['uid_owner']);
		
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
				
				if(!$versionMeta)
				{
					unset($rows[$key]);
				}
				
				$dirname = dirname($versionMeta['eospath']);
				
				if(strpos($eosPath, $dirname) === FALSE)
				{
					unset($rows[$key]);
					continue;
				}
				
				$basename = basename($versionMeta['eospath']);
				
				if($row['item_type'] === 'file')
				{
					$realfile = $dirname . "/" . substr($basename, 8);
				}
				else
				{
					$realfile = $dirname . "/" . $basename;
				}
				
				//$realfile = $dirname . "/" . substr($basename, 8);
				
				$eosMeta = EosUtil::getFileByEosPath($realfile);
				
				if(!$eosMeta || strpos($eosMeta['eospath'], EosUtil::getEosRecycleDir()) !== FALSE)
				{
					unset($rows[$key]);
					continue;
				}
				
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
		$fileInfo = $view->getFileInfo($path);
		
		$eosPath = EosProxy::toEos('files' . $path, 'object::user:'.$username);
		$originalEosMeta = EosUtil::getFileByEosPath($eosPath);
		
		try
		{
			$dirname = dirname($eosPath);
			$basename = basename($eosPath);
			$eosMeta = null;
			if($fileInfo['eostype'] === 'file')
			{
				$versionFolder = $dirname . "/.sys.v#." . $basename;
				$eosMeta = EosUtil::getFileByEosPath($versionFolder, false);
			}
			else
			{
				$eosMeta = $fileInfo;
			}
			
			if($eosMeta === null)
			{
				return new \OC_OCS_Result(null, 400, 'the file is not shared ' .$eosPath);
			}
			
			if(strpos($eosMeta['eospath'], EosUtil::getEosRecycleDir()) !== FALSE)
			{
				return new \OC_OCS_Result(null, 404, 'the requested file has been deleted');
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
<?php

/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/7/16
 * Time: 3:18 PM
 */

namespace OC\CernBox\Share;

class Util {

	/**
	 * Util constructor.
	 */
	public function __construct() {
	}


	public function getUsernameFromSharedToken()
	{
		// TODO(labkode): Double-check that the token is always send in that Header
		// and not in the URL as Nadir was doing before.
		// so we avoid URL matching.
		// Is still this URL matching needed for some corner cases ? Ask Nadir.
		if(!isset($_SERVER['PHP_AUTH_USER'])) {
			return false;
		}

		$token = $_SERVER['PHP_AUTH_USER'];
		\OC::$server->getLogger()->debug("token is $token");
		$result = \OC_DB::prepare('SELECT uid_owner FROM oc_share WHERE token = ? LIMIT 1')->execute([$token])->fetchAll();

		if($result && count($result) > 0) {
			return $result[0]['uid_owner'];
		} else {
			// get username from logged in user
			$username = \OC::$server->getUserSession()->getLoginName();
			if($username) {
				return $username;
			} else {
				return false;
			}
		}
	}

	/**
	 * CERNBox simplifies ownCloud permissions:
	 * 1 => R/O
	 * 15 => R/W
	 * ownCloud permissions is a combination of the following permission bits:
	 * PERMISSION_CREATE = 4;
	 * PERMISSION_READ = 1;
	 * PERMISSION_UPDATE = 2;
	 * PERMISSION_DELETE = 8;
	 * PERMISSION_SHARE = 16;
	 * PERMISSION_ALL = 31;
	 * @param int $ownCloudPermissions
	 * @return int
	 */
	public function simplifyOwnCloudPermissions($ownCloudPermissions) {
		$ownCloudPermissions = (int)$ownCloudPermissions;
		if($ownCloudPermissions <= 0) {
			return 0;
		} else if ($ownCloudPermissions === 1) {
			return 1;
		} else {
			return 15;
		}
	}

	/**
	 * If ownCloud permissions contains READ | UPDATE | DELETE
	 * we give RWX+D eos permissions
	 * @param int $ownCloudPermissions
	 * @return string Eos permissions are a string like 'rw'
	 */
	public function getEosPermissionsFromOwnCloudPermissions($ownCloudPermissions) {
		$ownCloudPermissions = (int)$ownCloudPermissions;
		$eosPermissions = "";
		if($ownCloudPermissions === 1) {
			$eosPermissions = 'rx';
		} else if ($ownCloudPermissions > 1) {
			$eosPermissions = 'rwx+d';
		}
		return $eosPermissions;
	}

	/**
	 * Returns ownCloud permissions.
	 * @param string $eosPermissions
	 * @return int
	 */
	public function getOwnCloudPermissionsFromEosPermissions($eosPermissions) {
		$eosPermissions = (string)$eosPermissions;
		$ownCloudPermissions = 0;
		if(strpos($eosPermissions, "r") !== false) {
			$ownCloudPermissions += 1;
		}
		if(strpos($eosPermissions, "w") !== false) {
			$ownCloudPermissions += 14;
		}
		return $ownCloudPermissions;
	}
}
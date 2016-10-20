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
}
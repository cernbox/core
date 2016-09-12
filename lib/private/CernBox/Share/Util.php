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
		// so we avoid URL matching.
		// Is stil URL matching needed for some corner cases ? Ask Nadir.
		$token = $_SERVER['PHP_AUTH_USER'];
		\OC::$server->getLogger()->debug("token is $token");
		$result = \OC_DB::prepare('SELECT uid_owner FROM oc_share WHERE token = ? LIMIT 1')->execute([$token])->fetchAll();

		if($result && count($result) > 0)
		{
			return $result[0]['uid_owner'];
		}

		return false;
	}
}
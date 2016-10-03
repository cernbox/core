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

		if($result && count($result) > 0)
		{
			return $result[0]['uid_owner'];
		}

		return false;
	}

	/*
	public function getUsernameFromSharedTokenLong() {
		$token = false;
		if(!isset($_SERVER['PHP_AUTH_USER'])) {
			$uri = $_SERVER['REQUEST_URI'];
			$uri = trim($uri, '/');
			if(strpos($uri, '?') !== false)
			{
				$paramsRaw = explode('&', explode('?', $uri)[1]);
				$paramMap = [];
				foreach($paramsRaw as $pRaw) {
					$parts = explode('=', $pRaw);
					if(count($parts) < 2) {
						continue;
					}
					$paramMap[$parts[0]] = $parts[1];
				}

				if(isset($paramMap['token'])) {
					$token = $paramMap['token'];
				} else if(isset($paramMap['t'])) {
					$token = $paramMap['t'];
				}
			}

			if(!$token && isset($_POST['dirToken'])) {
				$token = $_POST['dirToken'];
			}

			if(!$token) {
				$split = explode('/', $uri);

				if(count($split) >= 3) {
					$token = $split[2];
					if(($pos = strpos($token, '?')) !== false) {
						$token = substr($token, 0, $pos);
					}
				}
			}
		} else {
			$token = $_SERVER['PHP_AUTH_USER'];
		}

		\OC::$server->getLogger()->debug("token is $token");
		$result = \OC_DB::prepare('SELECT uid_owner FROM oc_share WHERE token = ? LIMIT 1')->execute([$token])->fetchAll();

		if($result && count($result) > 0) {
			return $result[0]['uid_owner'];
		}

		return false;
	}
	*/
}
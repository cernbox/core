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


	/**
	 * Attempts to get the user owner of a share by link using
	 * the token provided in the request.
	 * @return boolean|string Upon success, it will return the username owner of
	 * 							this anonymous share; Will return false otherwise
	 */
	public function isSharedLinkGuest()
	{
		$uri = $_SERVER['REQUEST_URI'];
		$uri = trim($uri, '/');

		$token = false;

		if(strpos($uri, '?') !== FALSE)
		{
			$paramsRaw = explode('&', explode('?', $uri)[1]);
			$paramMap = [];
			foreach($paramsRaw as $pRaw)
			{
				$parts = explode('=', $pRaw);
				if(count($parts) < 2)
				{
					continue;
				}

				$paramMap[$parts[0]] = $parts[1];
			}

			if(isset($paramMap['token']))
			{
				$token = $paramMap['token'];
			}
			else if(isset($paramMap['t']))
			{
				$token = $paramMap['t'];
			}
		}

		if(!$token && isset($_POST['dirToken']))
		{
			$token = $_POST['dirToken'];
		}

		if(!$token)
		{
			$split = explode('/', $uri);

			if(count($split) < 3)
			{
				return false;
			}

			$token = $split[2];
			if(($pos = strpos($token, '?')) !== FALSE)
			{
				$token = substr($token, 0, $pos);
			}
		}

		return $this->getShareByLinkOwner($token);
	}

	public function getShareByLinkOwner($token) {
		// TODO:copy from Nadir
	}

	// return the list of EGroups this member is part of, but NOT all, just the ones that appear in share database.
	/**
	 * Retrieves the e-groups of which the given $username is member of.
	 * NOTE: Currently limited to the e-groups present in the oc_share database table.
	 * @param string $username. The username of the user we want to get his/her e-groups
	 * @return array. An array containing the e-groups to which this user belongs
	 */
	public function getEGroups($username)
	{
		return $this->ldapCacheManager->getUserEGroups($username);
	}


	/**
	 * Tests wether the given URI (Used for the request to the webserver) is a shared file
	 * related URI [CURRENTLY USED BY KUBA'S OPTIMIZATION IN SHARED MOUNTS apps/files_sharing/lib/mountprovider.php)
	 * @param string $uri_path The URI issued against the server
	 * @return bool True if the uri is a request to some shared file, false otherwise
	 */
	public function isSharedURIPath($uri_path)
	{
		// uri paths always start with leading slash (e.g. ?dir=/bla) # assume that all shared items follow the same naming convention at the top directory (and they do not clash with normal files and directories)
		// the convention for top directory is: "name (#123)"
		// examples:
		// "/aaa (#1234)/jas" => true
		// "/ d s (#77663455)/jas" => true
		// "/aaa (#1)/jas" => false
		// "/aaa (#ssss)/jas" => false
		// "aaa (#1234)/jas" => false
		// "/(#7766)/jas" => false
		// "/ (#7766)/jas" => true (this is a flaw)
		if (startsWith ( $uri_path, '/' ))
		{
			$topdir = explode ( "/", $uri_path ) [1];
		}
		else
		{
			$topdir = explode ( "/", $uri_path ) [0];
		}

		$parts = explode ( " ", $topdir );
		if (count ( $parts ) < 2)
		{
			return false;
		}
		$marker = end ( $parts );
		return preg_match ( "/[(][#](\d{3,})[)]/", $marker ); // we match at least 3 digits enclosed within our marker: (#123)
	}
}
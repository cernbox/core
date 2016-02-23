<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Florin Peter <github@florin-peter.de>
 * @author Georg Ehrke <georg@owncloud.com>
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Scrutinizer Auto-Fixer <auto-fixer@scrutinizer-ci.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

/**
 * Versions
 *
 * A class to handle the versioning of files.
 */

namespace OCA\Files_Versions;

use OC\Files\ObjectStore\EosProxy;
use OC\Files\ObjectStore\EosUtil;
use OC\Files\ObjectStore\EosCmd;

class Storage {

	const DEFAULTENABLED = true;

	/** @var \OCA\Files_Versions\AppInfo\Application */
	private static $application;

	public static function getUidAndFilename($filename) {
		$uid = \OC\Files\Filesystem::getOwner($filename);
		\OC\Files\Filesystem::initMountPoints($uid);
		if ( $uid != \OCP\User::getUser() ) {
			$info = \OC\Files\Filesystem::getFileInfo($filename);
			$ownerView = new \OC\Files\View('/'.$uid.'/files');
			$filename = $ownerView->getPath($info['fileid']);
		}
		return array($uid, $filename);
	}

	/**
	 * store a new version of a file.
	 */
	public static function store($filename) {
	}

	/**
	 * rollback to an old version of a file.
	 */
	public static function rollback($file, $revision) {
		$file = "files" . $file;// we need to add the files prefix to send EOS to user root and not to m.etacernbox
		EosUtil::putEnv();
		$eos_prefix = EosUtil::getEosPrefix();
		$username   = \OCP\User::getUser();
		$uidAndGid  = EosUtil::getUidAndGid($username);
		if (!$uidAndGid) {
			return false;
		}
		list($uid, $gid) = $uidAndGid;
		if (\OCP\Config::getSystemValue('files_versions', Storage::DEFAULTENABLED) == 'true') {
			$eosPath = EosProxy::toEos($file, "object::user:$username");
			$eosPathEscaped = escapeshellarg($eosPath);
			$cmd     = "eos -b -r $uid $gid file versions $eosPathEscaped $revision";
			list($result, $errcode) = EosCmd::exec($cmd);
			if ($errcode === 0) {
				return true;
			} else {
				return false;
			}

		}
	}


	/**
	 * get a list of all available versions of a file in descending chronological order
	 * @param string $uid user id from the owner of the file
	 * @param string $filename file to find versions of, relative to the user files dir
	 * @param string $userFullPath
	 * @return array versions newest version first
	 */
	public static function getVersions($uid, $filename, $userFullPath = '') { // for better performance we should use file versions /eos/dev/user/l/labrador/versioned_file
		$uid      = \OCP\User::getUser();
		$pathinfo = pathinfo($filename);// information about the file
		$view     = new \OC\Files\View($uid . '/files');
		// versions folder in the same dir as the file
		$versionsFolder = $pathinfo['dirname'] . '/.sys.v#.' . $pathinfo['basename'];
		$files          = $view->getDirectoryContent($versionsFolder);
		$versions       = array();
		foreach ($files as $file) {
			if ($file['type'] === 'file') {
				$version                                  = $file['name'];
				$key                                      = $version . '#' . $filename;
				$versions[$key]['cur']                    = 0;
				$versions[$key]['version']                = $version;
				$versions[$key]['humanReadableTimestamp'] = self::getHumanReadableTimestamp($version);
				$versions[$key]['preview']                = '';
				$versions[$key]['preview']                = \OCP\Util::linkToRoute('core_ajax_versions_preview', array('file' => $userFullPath, 'version' => $version));
				$versions[$key]['path']                   = $filename;
				$versions[$key]['name']                   = $file['name'];
				$versions[$key]['size']                   = $file['size'];
			}
		}
		krsort($versions);
		return $versions;
	}

	/**
	 * translate a timestamp into a string like "5 days ago"
	 * @param int $timestamp
	 * @return string for example "5 days ago"
	 */
	private static function getHumanReadableTimestamp($timestamp) {

		//CERN START
		$version = explode(".", $timestamp);
		//CERN END
		$diff = time() - $version[0];

		if ($diff < 60) {// first minute
			return $diff . " seconds ago";
		} elseif ($diff < 3600) {//first hour
			return round($diff / 60) . " minutes ago";
		} elseif ($diff < 86400) { // first day
			return round($diff / 3600) . " hours ago";
		} elseif ($diff < 604800) { //first week
			return round($diff / 86400) . " days ago";
		} elseif ($diff < 2419200) { //first month
			return round($diff / 604800) . " weeks ago";
		} elseif ($diff < 29030400) { // first year
			return round($diff / 2419200) . " months ago";
		} else {
			return round($diff / 29030400) . " years ago";
		}

	}
}

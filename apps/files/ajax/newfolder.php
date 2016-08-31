<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Frank Karlitschek <frank@owncloud.org>
 * @author Georg Ehrke <georg@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
// Init owncloud


OCP\JSON::callCheck();
\OC::$server->getSession()->close();

// Get the params
$dir = isset($_POST['dir']) ? (string)$_POST['dir'] : '';
$folderName = isset($_POST['foldername']) ?(string) $_POST['foldername'] : '';
$token = isset($_POST['token']) ? (string) $_POST['token'] : false;

$l10n = \OC::$server->getL10N('files');

$result = array(
	'success' 	=> false,
	'data'		=> NULL
	);

if($token)
{
	\OC_User::setIncognitoMode(true);
	
	// return only read permissions for public upload
	//$allowedPermissions = \OCP\Constants::PERMISSION_READ;
	//$publicDirectory = !empty($_POST['subdir']) ? (string)$_POST['subdir'] : '/';
	
	$linkItem = OCP\Share::getShareByToken($token);
	if ($linkItem === false) {
		OCP\JSON::error(array('data' => array_merge(array('message' => $l10n->t('Invalid Token')))));
		die();
	}
	
	if (!($linkItem['permissions'] & \OCP\Constants::PERMISSION_CREATE)) {
		OCP\JSON::checkLoggedIn();
	} else {
		// resolve reshares
		$rootLinkItem = OCP\Share::resolveReShare($linkItem);
	
		OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
		// Setup FS with owner
		OC_Util::tearDownFS();
		OC_Util::setupFS($rootLinkItem['uid_owner']);
	
		// The token defines the target directory (security reasons)
		$path = \OC\Files\Filesystem::getPath($linkItem['file_source']);
		if($path === null) {
			OCP\JSON::error(array('data' => array_merge(array('message' => $l10n->t('Unable to set upload directory.')))));
			die();
		}
		$dir = sprintf(
				"/%s/%s",
				$path,
				$dir
				);
	
		if (!$dir || empty($dir) || $dir === false) {
			OCP\JSON::error(array('data' => array_merge(array('message' => $l10n->t('Unable to set upload directory.')))));
			die();
		}
	
		$dir = rtrim($dir, '/');
	}
}
else
{
	OCP\JSON::checkLoggedIn();
}

try {
	\OC\Files\Filesystem::getView()->verifyPath($dir, $folderName);
} catch (\OCP\Files\InvalidPathException $ex) {
	$result['data'] = [
		'message' => $ex->getMessage()];
	OCP\JSON::error($result);
	return;
}

if (!\OC\Files\Filesystem::file_exists($dir . '/')) {
	$result['data'] = array('message' => (string)$l10n->t(
			'The target folder has been moved or deleted.'),
			'code' => 'targetnotfound'
		);
	OCP\JSON::error($result);
	exit();
}

$target = $dir . '/' . $folderName;
		
if (\OC\Files\Filesystem::file_exists($target)) {
	$result['data'] = array('message' => $l10n->t(
			'The name %s is already used in the folder %s. Please choose a different name.',
			array($folderName, $dir))
		);
	OCP\JSON::error($result);
	exit();
}

try {
	if(\OC\Files\Filesystem::mkdir($target)) {
		if ( $dir !== '/') {
			$path = $dir.'/'.$folderName;
		} else {
			$path = '/'.$folderName;
		}
		$meta = \OC\Files\Filesystem::getFileInfo($path);
		$meta['type'] = 'dir'; // missing ?!
		OCP\JSON::success(array('data' => \OCA\Files\Helper::formatFileInfo($meta)));
		exit();
	}
} catch (\Exception $e) {
	$result = [
		'success' => false,
		'data' => [
			'message' => $e->getMessage()
		]
	];
	OCP\JSON::error($result);
	exit();
}

OCP\JSON::error(array('data' => array( 'message' => $l10n->t('Error when creating the folder') )));

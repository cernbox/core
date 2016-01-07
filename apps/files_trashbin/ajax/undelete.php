<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
\OC::$server->getSession()->close();

$files    = null;
$allfiles = null;
if (isset($_POST['files'])) {
	$files = json_decode($_POST['files']);
}
if (isset($_POST['allfiles'])) {
	$allfiles = json_decode($_POST['allfiles']);
}

$error = array();
$success = array();

if ($allfiles) {
	$files = \OCA\Files_Trashbin\EosTrashbin::getAllRestoreKeys();
}
$i = 0;
foreach ($files as $file) {
	$restoredFile = OCA\Files_Trashbin\EosTrashbin::restore($file);
	if(is_array($restoredFile)) {
		$success[$i]['filename']  = $file;
		$success[$i]['timestamp'] = strtotime($restoredFile['deletion-time']);
		$i++;
	} else {
		$fileInfo = OCA\Files_Trashbin\EosTrashbin::getFileByKey($file);
		$eosRestorePath = $fileInfo["restore-path"];
		$ocRestorePath = OC\Files\ObjectStore\EosProxy::toOc($eosRestorePath);
		$pathinfo = pathinfo($ocRestorePath);
		$dir = $pathinfo["dirname"];
		$dir = substr($dir,6); // remove the files/ part
		if($restoredFile === 17) {
			$error[] = $pathinfo["basename"] . " (folder/file already exists)";
		} else {
			$error[] = $pathinfo["basename"] . " (you need to create the folder: $dir)";
		}
		\OCP\Util::writeLog('trashbin', 'can\'t restore ' . $pathinfo['basename'], \OCP\Util::ERROR);
	}

}

if ( $error ) {
	$filelist = '';
	foreach ( $error as $e ) {
		$filelist .= $e.', ';
	}
	$l = OC::$server->getL10N('files_trashbin');
	$message = $l->t("Couldn't restore %s", array(rtrim($filelist, ', ')));
	OCP\JSON::error(array("data" => array("message" => $message,
										  "success" => $success, "error" => $error)));
} else {
	OCP\JSON::success(array("data" => array("success" => $success)));
}

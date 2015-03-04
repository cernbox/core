<?php

OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();

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
			$errors[] = $pathinfo["basename"] . " (folder/file already exists)";
		} else {
			$errors[] = $pathinfo["basename"] . " (you need to create the folder: $dir)";
		}
		OC_Log::write('trashbin', 'can\'t restore ' . $pathinfo['basename'], OC_Log::ERROR);
	}

}

if ( $error ) {
	$filelist = '';
	foreach ( $error as $e ) {
		$filelist .= $e.', ';
	}
	$l = OC_L10N::get('files_trashbin');
	$message = $l->t("Couldn't restore %s", array(rtrim($filelist, ', ')));
	OCP\JSON::error(array("data" => array("message" => $message,
										  "success" => $success, "error" => $error)));
} else {
	OCP\JSON::success(array("data" => array("success" => $success)));
}

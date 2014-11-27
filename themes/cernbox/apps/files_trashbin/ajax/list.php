<?php
// only need filesystem apps
$RUNTIME_APPTYPES = array('filesystem');

// Init owncloud

OCP\JSON::checkLoggedIn();

// Load the files
$dir           = isset($_GET['dir']) ? $_GET['dir'] : '';
$sortAttribute = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortDirection = isset($_GET['sortdirection']) ? ($_GET['sortdirection'] === 'desc') : false;
$data          = array();

// subsitutte by Files_Trashbin
$files = \OCA\Files_Trashbin\EosTrashbin::getTrashFiles($dir);

if ($files === null) {
	header("HTTP/1.0 404 Not Found");
	exit();
}

$data['permissions'] = 0;
$data['directory']   = $dir;
$data['files']       = $files;

OCP\JSON::success(array('data' => $data));
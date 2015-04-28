<?php

OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
\OC::$server->getSession()->close();

$folder = isset($_POST['dir']) ? $_POST['dir'] : '/';

// "empty trash" command
if (isset($_POST['allfiles']) and $_POST['allfiles'] === 'true'){
	\OCA\Files_Trashbin\EosTrashbin::deleteAll();
	OCP\JSON::success(array("data" => array("success" => array())));
}

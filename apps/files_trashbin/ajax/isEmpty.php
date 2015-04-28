<?php

/*
 * Check if trash bin is empty to re-enable the deleted files button if needed
 */

OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
\OC::$server->getSession()->close();

$trashStatus = \OCA\Files_Trashbin\EosTrashbin::isEmpty();

OCP\JSON::success(array("data" => array("isEmpty" => $trashStatus)));



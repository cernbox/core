<?php
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
$trashStatus = \OCA\Files_Trashbin\EosTrashbin::isEmpty();
OCP\JSON::success(array("data" => array("isEmpty" => $trashStatus)));

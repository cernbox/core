<?php

$l = \OC::$server->getL10N('files');

OCP\App::registerAdmin('files', 'admin');

OCP\App::addNavigationEntry(array("id" => "files_index",
	"order" => 0,
	"href" => OCP\Util::linkTo("files", "index.php"),
	"icon" => OCP\Util::imagePath("core", "places/files.svg"),
	"name" => $l->t("Files")));
// HUGO we don't register the Files app for searching because then it does a recursively find. This way it just searchs in the UI in the current directory.
//\OC::$server->getSearch()->registerProvider('OC\Search\Provider\File', array('apps' => array('files')));

$templateManager = OC_Helper::getFileTemplateManager();
$templateManager->registerTemplate('text/html', 'core/templates/filetemplates/template.html');
$templateManager->registerTemplate('application/vnd.oasis.opendocument.presentation', 'core/templates/filetemplates/template.odp');
$templateManager->registerTemplate('application/vnd.oasis.opendocument.text', 'core/templates/filetemplates/template.odt');
$templateManager->registerTemplate('application/vnd.oasis.opendocument.spreadsheet', 'core/templates/filetemplates/template.ods');

\OCA\Files\App::getNavigationManager()->add(
	array(
		"id" => 'files',
		"appname" => 'files',
		"script" => 'list.php',
		"order" => 0,
		"name" => $l->t('All files')
	)
);

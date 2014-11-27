<?php
OCP\JSON::checkAppEnabled('files_versions');
//OCP\JSON::callCheck();

$file = $_GET['file']; //only the name of the file test.txt
$revision= $_GET['revision']; // the revision number 123456789.123456
list($uid, $filename) = OCA\Files_Versions\Storage::getUidAndFilename($file);
$pathinfo = pathinfo($filename);
$version = '/'. $uid .'/files/' .$pathinfo['dirname'] . '/.sys.v#.'.$pathinfo['basename'].'/'. $revision;
$view = new OC\Files\View('/');

$ftype = $view->getMimeType('/'.$uid.'/files/'. $filename);

header('Content-Type:'.$ftype);
OCP\Response::setContentDispositionHeader(basename($filename), 'attachment');
OCP\Response::disableCaching();
header('Content-Length: '.$view->filesize($version));

OC_Util::obEnd();

$view->readfile($version);

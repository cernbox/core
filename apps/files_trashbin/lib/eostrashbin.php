<?php
/**
 * Created by Hugo Gonzalez Labrador IT-DSS-FDO
 * 21 Jul 2014 12:10
 */

namespace OCA\Files_Trashbin;

use OC\Files\ObjectStore\EosParser;
use OC\Files\ObjectStore\EosProxy;
use OC\Files\ObjectStore\EosUtil;
use OC\Files\ObjectStore\EosCmd;

class EosTrashbin {

	public static function deleteAll() {
		EosUtil::putEnv();
		$eos_prefix = EosUtil::getEosPrefix();
		$username   = \OCP\User::getUser();
		$uidAndGid  = EosUtil::getUidAndGid($username);
		if (!$uidAndGid) {
			exit();
		}
		list($uid, $gid) = $uidAndGid;
		$cmd             = "eos -b -r $uid $gid  recycle purge";
		list($result, $errcode) = EosCmd::exec($cmd);
		if ($errcode === 0) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Restore the file given by the EOS restore key
	 * @param $key The restore_key used by EOS recycle restore command
	 * @return file The file that has been restored or the error code
	 */
	public static function restore($key) {
		EosUtil::putEnv();
		$eos_prefix = EosUtil::getEosPrefix();
		$username   = \OCP\User::getUser();
		$uidAndGid  = EosUtil::getUidAndGid($username);
		if (!$uidAndGid) {
			exit();
		}
		list($uid, $gid) = $uidAndGid;
		$file            = self::getFileByKey($key);
		if ($file) {
			$cmd     = "eos -b -r $uid $gid  recycle restore " . $key;
			list($result, $errcode) = EosCmd::exec($cmd);
			if ($errcode === 0) {
				return $file;
			} else {
				return $errcode;
			}
		}
	}

	/**
	 * Return the list of files in the EOS trashbin
	 * @return array The fies in the EOS Trashbin
	 */
	public static function getAllFiles() {
		EosUtil::putEnv();
		$eos_prefix = EosUtil::getEosPrefix();
		$username   = \OCP\User::getUser();
		$uidAndGid  = EosUtil::getUidAndGid($username);
		if (!$uidAndGid) {
			exit();
		}
		list($uid, $gid) = $uidAndGid;
		$cmd             = "eos -b -r $uid $gid recycle ls -m";
		list($result, $errcode) = EosCmd::exec($cmd);
		$files = array();
		if ($errcode === 0) {// No error
			foreach ($result as $rawdata) {
				$line_to_parse = $rawdata;
				$file          = EosParser::parseRecycleLsMonitorMode($line_to_parse);
				// only list files in the trashbin that were in the files dir
				$filter        = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/";
				if (strpos($file["restore-path"], $filter) === 0) {
					$files[] = $file;
				}
			}
		} else {
			\OCP\Util::writeLog('eos', "trashbin getAllfiles $cmd $errcode", \OCP\Util::ERROR);
		}

		return $files;
	}

	/**
	 * Get the info of the file with the specified restore key
	 * @param $key The restore key used by EOS recycle restore
	 * @return The file with restore_key equal to key or null in not exists
	 */
	public static function getFileByKey($key) {
		$files = self::getAllFiles();
		foreach ($files as $file) {
			if ($file['restore-key'] == $key) {
				return $file;
			}
		}
	}

	/**
	 * Indicates if the trashbin is empty or not
	 * @return boolean
	 */
	public static function isEmpty() {
		$files = self::getAllFiles();
		if ($files) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Retrieves the contents of a trash bin directory. It formats the output from getAllFiles()
	 * @param string $dir path to the directory inside the trashbin
	 * or empty to retrieve the root of the trashbin
	 * @return array of files
	 */
	public static function getTrashFiles($dir) {
		$rawfiles = self::getAllFiles();
		$files    = array();

		foreach ($rawfiles as $rf) {
			$type                    = $rf['type'];
			$type === 'file' ? $type = 'file' : $type = 'httpd/unix-directory';

			$path      = EosProxy::toOc($rf['restore-path']);
			$pathinfo  = pathinfo($path);
			$extension = isset($pathinfo['extension']) ? $pathinfo["extension"] : "";
			if ($type == 'file') {
				switch ($extension) {
					case "txt":$file['mimetype'] = "txt/plain";
						$file['icon']               = \OC::$WEBROOT . "/core/img/filetypes/text.svg";
						break;
					case "pdf":$file["mimetype"] = "application/pdf";
						$file['icon']               = \OC::$WEBROOT ."/core/img/filetypes/application-pdf.svg";
						break;
					case "jpg":$file["mimetype"] = "image/jpeg";
						$file['icon']               = \OC::$WEBROOT ."/core/img/filetypes/image.svg";
						break;
					case "jpeg":$file["mimetype"] = "image/jpeg";
						$file['icon']                = \OC::$WEBROOT ."/core/img/filetypes/image.svg";
						break;
					case "png":$file["mimetype"] = "image/png";
						$file['icon']               = \OC::$WEBROOT ."/core/img/filetypes/image.svg";
						break;
					default:$file["mimetype"] = "application/x-php";
						$file['icon']            = \OC::$WEBROOT ."/core/img/filetypes/application.svg";
						break;
				}
			} else {
				$file['mimetype'] = 'httpd/unix-directory';
				$file['icon']     = \OC::$WEBROOT ."/core/img/filetypes/folder-external.svg";
			}

			if ($type === 'file') {
				$extension = isset($extension) ? ('.' . $extension) : '';
			}
			$timestamp         = (int)$rf['deletion-time'];
			$file['id']        = $rf['restore-key'];
			$file['name']      = $pathinfo['basename'];
			$file['date']      = \OCP\Util::formatDate($timestamp);
			$file['mtime'] = $timestamp * 1000;
			//The icon of the file is changed depending on the mime
			// We need to implement a mime type by extension may be in EosUtil
			$file['type'] = $type;
			if ($type === 'file') {
				$file['basename']  = $pathinfo['filename'];
				$file['extension'] = $extension;
			}
			$file['directory'] = $pathinfo['dirname'];
			if ($file['directory'] === '/') {
				$file['directory'] = '';
			}
			$file['permissions'] = \OCP\PERMISSION_READ;
			$files[]             = $file;
		}
		//usort($files, array('\OCA\Files\Helper', 'fileCmp'));
		return $files;

	}

	/**
	 * Splits the given path into a breadcrumb structure.
	 * @param string $dir path to process
	 * @return array where each entry is a hash of the absolute
	 * directory path and its name
	 */
	public static function makeBreadcrumb($dir) {
		// Make breadcrumb
		$pathtohere = '';
		$breadcrumb = array();
		foreach (explode('/', $dir) as $i) {
			if ($i !== '') {
				if (preg_match('/^(.+)\.d[0-9]+$/', $i, $match)) {
					$name = $match[1];
				} else {
					$name = $i;
				}
				$pathtohere .= '/' . $i;
				$breadcrumb[] = array('dir' => $pathtohere, 'name' => $name);
			}
		}
		return $breadcrumb;
	}

	/**
	 * @param $filename The filename of the file
	 * @return string The mime type of the file by extension
	 */
	public static function getMimeType($filename) {

	}

	/**
	 * Restores all the files in the trashbin of the user authenticated
	 */
	public static function restoreAll() {
		$files = self::getAllFiles();
		foreach ($files as $file) {
			self::restore($file['restore-key']);
		}
	}

	public static function getAllRestoreKeys(){
		$keys = array();
		$files = self::getAllFiles();
		foreach($files as $file){
			$keys[] = $file["restore-key"];
		}
		return $keys;
	}

}

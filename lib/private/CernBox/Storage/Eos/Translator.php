<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:05 PM
 */

namespace OC\CernBox\Storage\Eos;


class Translator {

	private $util;

	// if the storageId is a user storage we return the username else false (object::store:$eos_prefix)
	// when we are creating files (the storage id is still not defined) we receive the username of logged user
	/**
	 * Translator constructor.
	 */
	public function __construct(Util $util) {
		$this->util = $util;
	}

	public function getUsernameFromStorageId($storageId) {
		$splitted = explode(":", $storageId);
		$differenciator = isset($splitted[2]) ? $splitted[2] : "";
		if ($differenciator === "store") {
			return false;
		} else if ($differenciator === "user") {
			$username = $splitted[3];
			return $username;
		} else {// when we are creating files (the storage id is still not defined) we receive the username of logged user
			$username = $storageId;
			return $username;
		}

	}
	// ocPath is local oc like "" or "files" or "files/A/B/test_file./txt"
	// storageId is object::store:/eos/dev/user/ or object::user:labrador
	public function toEos($ocPath, $storageId) {//ocPath like files/abc.txt or cache/557 or files or ""
		$eos_project_prefix = $this->util->getEosProjectPrefix();
		$eos_prefix = $this->util->getEosPrefix();
		$eos_meta_dir = $this->util->getEosMetaDataDir();
		$username = self::getUsernameFromStorageId($storageId);
		if ($username === false) {
			return false;
			///$eosPath = $eos_prefix;
			//return $eosPath;
		}
		// if the user is a project owner,instead send him to his homedir we send him to the project dir.

		$tempOcPath = $ocPath;
		if (strpos($ocPath, 'files') === 0) {
			$tempOcPath = substr($tempOcPath, 5);
		}

		$tempOcPath = trim($tempOcPath, '/');

		if (strpos($tempOcPath, '  project') === 0) {
			$len = strlen('  project ');
			$nextSlash = strpos($tempOcPath, '/');
			if ($nextSlash === false) {
				$nextSlash = strlen($tempOcPath);
			}

			$project = substr($tempOcPath, $len, $nextSlash - $len);
			$pathLeft = substr($tempOcPath, $nextSlash);
			$relativePath = $this->util->getProjectRelativePath($project);

			if ($relativePath) {
				return (rtrim($eos_project_prefix, '/') . '/' . trim($relativePath, '/') . '/' . trim($pathLeft, '/'));
			}
		}

		/*$project = $this->util->getProjectNameForUser($username);
		if($project !== null) {
			$project_path=  rtrim($eos_project_prefix, '/') . '/' . rtrim($project, '/') . '/' . ltrim(substr($ocPath,6), '/'); #KUBA: added /
			return $project_path;
		}*/

		if ($ocPath === "") {
			$eosPath = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/";
			return $eosPath;
		}
		//we must be cautious becasue there is files_encryption, that is the reason we do this check
		$condition = false;
		$lenOcPath = strlen($ocPath);
		if (strpos($ocPath, "files") === 0) {
			if ($lenOcPath === 5) {
				$condition = true;
			} else if ($lenOcPath > 5 && $ocPath[5] === "/") {
				$condition = true;
			}
		}
		if ($condition) {
			$splitted = explode("/", $ocPath);// [files, hola.txt] or [files]
			$last = "";
			if (count($splitted) >= 2) {
				$last = implode("/", array_slice($splitted, 1));
			}
			$eosPath = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/" . $last;
			return $eosPath;
		} else {
			$eosPath = $eos_meta_dir . substr($username, 0, 1) . "/" . $username . "/" . $ocPath;
			return $eosPath;
		}

	}

	// EOS give us the path ended with a / so perfect
	public function toOc($eosPath) {//eosPath like /eos/dev/user/ or /eos/dev/user/l/labrador/abc.txt or /eos/dev/user/.metacernbox/l/labrador/cache/abc.txt or /eos/dev/user/.metacernbox/thumbnails/abc.txt
		$eos_project_prefix = $this->util->getEosProjectPrefix();
		$eos_prefix = $this->util->getEosPrefix();
		$eos_meta_dir = $this->util->getEosMetaDataDir();
		$eos_recycle_dir = $this->util->getEosRecycleDir();
		if ($eosPath == $eos_prefix) {
			return "";
		}
		if (strpos($eosPath, $eos_meta_dir) === 0) {
			$len_prefix = strlen($eos_prefix);
			$rel = substr($eosPath, $len_prefix);
			// splitted should be something like [.metacernbox, l, labrador, cache, 123]
			$splitted = explode("/", $rel);
			$ocPath = implode("/", array_slice($splitted, 3));
			return $ocPath;
		} else if (strpos($eosPath, $eos_prefix) === 0) {
			$len_prefix = strlen($eos_prefix);
			$rel = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			$lastPart = "";
			if (count($splitted) > 2 && $splitted[2] !== "") {
				$lastPart = implode("/", array_slice($splitted, 2));
				$ocPath = "files/" . $lastPart;
			} else {
				$ocPath = "files";
			}
			// we strip posible end slashes
			$ocPath = rtrim($ocPath, "/");
			return $ocPath;
		} else if (strpos($eosPath, $eos_recycle_dir) === 0) {
			return false;
		} else if (strpos($eosPath, $eos_project_prefix) === 0) {
			$len_prefix = strlen($eos_project_prefix);
			$rel = substr($eosPath, $len_prefix);

			$projectRelName = $this->util->getProjectNameForPath($rel);
			if ($projectRelName) {
				$rel = trim($rel);
				$pathLeft = substr($rel, strlen($projectRelName[1]));
				$ocPath = 'files/' . $projectRelName[0] . '/' . $pathLeft;
				return $ocPath;
			}
			\OC::$server->getLogger()->warning('Cannot find project mapping for path ' . $eosPath);
			return false;

			/*$splitted   = explode("/", $rel);
			$projectname     = $splitted[0];
			$ocPath = "files/" . substr($eosPath, strlen($eos_project_prefix . $projectname));
			$ocPath = rtrim($ocPath, "/");
			return $ocPath;*/
		} else {
			\OC::$server->getLogger()->warning("The eos_prefix,eos_meta_dir,eos_recycle_dir does not match this path: $eosPath");
			return false;
		}
	}
}
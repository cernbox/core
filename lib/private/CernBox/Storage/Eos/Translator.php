<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:05 PM
 */

namespace OC\CernBox\Storage\Eos;


/**
 * Class Translator
 *
 * @package OC\CernBox\Storage\Eos
 */
class Translator {

	/**
	 * @var string
	 */
	private $username;
	/**
	 * @var string
	 */
	private $eosPrefix;
	/**
	 * @var string
	 */
	private $eosMetaDataPrefix;
	/**
	 * @var string
	 */
	private $eosProjectPrefix;
	/**
	 * @var string
	 */
	private $eosRecycleDir;

	/**
	 * @var \OCP\ILogger
	 */
	private $logger;

	/**
	 * @var IProjectMapper
	 */
	private $projectMapper;

	/**
	 * Translator constructor.
	 *
	 * @param $username
	 * @param $eosPrefix
	 * @param $eosMetaDataPrefix
	 * @param $eosProjectPrefix
	 * @param $eosRecycleDir
	 */
	public function __construct($username, $eosPrefix, $eosMetaDataPrefix, $eosProjectPrefix, $eosRecycleDir) {
		$this->logger = \OC::$server->getLogger();
		$this->projectMapper = \OC::$server->getCernBoxProjectMapper();

		$letter = $username[0];

		// if the eosPrefix or eosMetaData prefix contains placeholders for
		// username first letter and username, we replace them.
		$eosPrefix = str_replace("<letter>", $letter, $eosPrefix);
		$eosPrefix = str_replace("<username>", $username, $eosPrefix);

		$eosMetaDataPrefix = str_replace("<letter>", $letter, $eosMetaDataPrefix);
		$eosMetaDataPrefix = str_replace("<username>", $username, $eosMetaDataPrefix);

		$this->eosPrefix = $eosPrefix;
		$this->eosMetaDataPrefix = $eosMetaDataPrefix;
		$this->eosProjectPrefix = $eosProjectPrefix;
		$this->username = $username;
	}

	// ocPath is a local owncloud path like "" or "files" or "files/A/B/test_file./txt"
	// this function converts the ocPath to an eos path according to the eos prefix
	// and username if the eos instance is an user instance.
	/**
	 * @param $ocPath
	 * @return string
	 */
	public function toEos($ocPath) {
		$eosPath = $this->_toEos($ocPath);
		$this->logger->debug("TRANSLATOR OC('$ocPath') => EOS('$eosPath')");
		return $eosPath;
	}

	private function _toEos($ocPath) {

		$tempOcPath = $ocPath;

		// if path starts with projects means that we are pointing
		// to the project prefix.
		if(strpos($ocPath, 'projects') === 0) {
			$tempOcPath = substr($tempOcPath, strlen('projects'));
			$eosPath =  rtrim($this->eosProjectPrefix, '/') . '/' . $tempOcPath;
			return $eosPath;
		}

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

			$project = substr($tempOcPath, $len, $nextSlash - $len); // skiclub
			$pathLeft = substr($tempOcPath, $nextSlash);
			$projectInfo = $this->projectMapper->getProjectInfoByProject($project);
			$relativePath = $projectInfo->getProjectRelativePath();

			if ($relativePath) {
				return (rtrim($this->eosProjectPrefix, '/') . '/' . trim($relativePath, '/') . '/' . trim($pathLeft, '/'));
			}
		}

		if ($ocPath === "") {
			//$eosPath = $this->eosPrefix. substr($this->username, 0, 1) . "/" . $this->username. "/";
			$eosPath = $this->eosPrefix;
			return $eosPath;
		}
		// we must be cautious because there is files_encryption, that is the reason we perform this check
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
			//$eosPath = $this->eosPrefix. substr($this->username, 0, 1) . "/" . $this->username. "/" . $last;
			$eosPath = $this->eosPrefix . $last;
			return $eosPath;
		} else {
			$eosPath = $this->eosMetaDataPrefix . $ocPath;
			return $eosPath;
		}
	}

	/**
	 * EOS give us the path ended with a / so perfect
	 * @param $eosPath
	 * @return bool|string
	 * Examples:
	 * - /eos/dev/user/
	 * - /eos/dev/user/l/labrador/abc.txt
	 * - /eos/dev/user/.metacernbox/l/labrador/cache/abc.txt
	 * - /eos/dev/user/.metacernbox/thumbnails/abc.txt
	 * - /eos/dev/projects/a/atlas-dist
	 */
	public function toOc($eosPath) {
		if ($eosPath == $this->eosPrefix) {
			return "";
		}
		if (strpos($eosPath, $this->eosMetaDataPrefix) === 0) {
			$len_prefix = strlen($this->eosPrefix);
			$rel = substr($eosPath, $len_prefix);
			// splitted should be something like [.metacernbox, l, labrador, cache, 123]
			$splitted = explode("/", $rel);
			$ocPath = implode("/", array_slice($splitted, 3));
			return $ocPath;
		} else if (strpos($eosPath, $this->eosPrefix) === 0) {
			return "files/" . substr($eosPath, strlen($this->eosPrefix));
		} else if (strpos($eosPath, $this->eosRecycleDir) === 0) {
			return false;
		} else if (strpos($eosPath, $this->eosProjectPrefix) === 0) {
			$len_prefix = strlen($this->eosProjectPrefix);
			// a/atlas-software-dist or skiclub/more/contents or just b
			$pathWithoutProjectPrefix = rtrim(substr($eosPath, $len_prefix), '/');
			$projectName = '';
			$projectRest = '';

			// $pathWithoutProjectPrefix can be:
			// - a
			// - a/atlas-project
			// - a/atlas-project/more/content
			// - it-storage
			// - it-storage/more/content
			// We need to omit projects that are only letters, like b
			$parts = explode("/", $pathWithoutProjectPrefix);
			if(count($parts) === 1) {
				// path can contain a letter or a project name, b or it-storage
				// if it is a letter we omit it.
				$letterOrProjectName = $parts[0];
				if(strlen($letterOrProjectName) > 1) { // projects are bigger that one letter
					$projectName = $letterOrProjectName;
				}
			} else {
				// at this point we can have
				// - a/atlas-project
				// - a/atlas-project/more/content
				// - it-storage/more/content
				// we need to check if the project name
				// is the first one or second part of the array
				$firstElement = $parts[0];
				if(strlen($firstElement) === 1) {
					// the firstElement is a letter so the second one must be
					// the project name.
					$projectName = $parts[1];
					if (count($parts) > 2) {
						// means that be need to append more/content to the ocpath
						$projectRest = implode("/", array_slice($parts, 2));
					}
				} else {
					// means the firstElement is already the project name.
					$projectName = $parts[0];
					$projectRest = implode('/', array_slice($parts, 1));
				}
			}

			if($projectName) {
				$ocPath = 'files/  project ' . $projectName . '/' . $projectRest;
				$this->logger->info("project shared path is: $ocPath");
				return $ocPath;
			} else {
				$this->logger->error("could not map $eosPath to any project");
				return false;
			}
		} else {
			$this->logger->error("configured eos prefixes cannot handle this path:$eosPath");
			return false;
		}
	}
}
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

		$letter = $username[0];

		// if the eosPrefix or eosMetaData prefix contains placeholders for
		// username first letter and username, we replace them.
		$eosPrefix = str_replace("<letter>", $letter, $eosPrefix);
		$eosPrefix = str_replace("<username>", $username, $eosPrefix);

		$eosMetaDataPrefix = str_replace("<letter>", $letter, $eosMetaDataPrefix);
		$eosMetaDataPrefix = str_replace("<username>", $username, $eosMetaDataPrefix);

		$this->eosPrefix = $eosPrefix;
		$this->eosMetaDataPrefix = $eosMetaDataPrefix;
		$this->eosProjectPrefix;
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
			$relativePath = $this->projectMapper->getProjectRelativePath($project);

			if ($relativePath) {
				return (rtrim($this->eosProjectPrefix, '/') . '/' . trim($relativePath, '/') . '/' . trim($pathLeft, '/'));
			}
		}

		if ($ocPath === "") {
			//$eosPath = $this->eosPrefix. substr($this->username, 0, 1) . "/" . $this->username. "/";
			$eosPath = $this->eosPrefix;
			return $eosPath;
		}
		//we must be cautious because there is files_encryption, that is the reason we perform this check
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

	// EOS give us the path ended with a / so perfect
	/**
	 * @param $eosPath
	 * @return bool|string
	 */
	public function toOc($eosPath) {//eosPath like /eos/dev/user/ or /eos/dev/user/l/labrador/abc.txt or /eos/dev/user/.metacernbox/l/labrador/cache/abc.txt or /eos/dev/user/.metacernbox/thumbnails/abc.txt
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
			$len_prefix = strlen($this->eosPrefix);
			$rel = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			if (count($splitted) > 2 && $splitted[2] !== "") {
				$lastPart = implode("/", array_slice($splitted, 2));
				$ocPath = "files/" . $lastPart;
			} else {
				$ocPath = "files";
			}
			// we strip possible end slashes
			$ocPath = rtrim($ocPath, "/");
			return $ocPath;
		} else if (strpos($eosPath, $this->eosRecycleDir) === 0) {
			return false;
		} else if (strpos($eosPath, $this->eosProjectPrefix) === 0) {
			$len_prefix = strlen($this->eosProjectPrefix);
			$rel = substr($eosPath, $len_prefix);

			$projectRelName = $this->projectMapper->getProjectNameForPath($rel);
			if ($projectRelName) {
				$rel = trim($rel);
				$pathLeft = substr($rel, strlen($projectRelName[1]));
				$ocPath = 'files/' . $projectRelName[0] . '/' . $pathLeft;
				return $ocPath;
			}
			$this->logger->error("could not map $eosPath to owncloud");
			return false;

		} else {
			$this->logger->error("configured eos prefixes cannot handle this path:$eosPath");
			return false;
		}
	}
}
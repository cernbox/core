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
	 * @var IInstance
	 */
	private $instance;

	/**
	 * @var \OCP\ILogger
	 */
	private $logger;

	/**
	 * @var IProjectMapper
	 */
	private $projectMapper;

	/**
	 * @var IInstanceMapper
	 */
	private $instanceMapper;

	/**
	 * Translator constructor.
	 *
	 * @param $username
	 * @param IInstance $instance
	 */
	public function __construct($username, IInstance $instance) {
		$this->logger = \OC::$server->getLogger();
		$this->instance = $instance;
		$this->projectMapper = \OC::$server->getCernBoxProjectMapper();
		$this->instanceMapper = \OC::$server->getCernBoxInstanceMapper();

		$letter = $username[0];

		// if the eosPrefix or eosMetaData prefix contains placeholders for
		// username first letter and username, we replace them.
		/*
		$eosPrefix = str_replace("<letter>", $letter, $eosPrefix);
		$eosPrefix = str_replace("<username>", $username, $eosPrefix);

		$eosMetaDataPrefix = str_replace("<letter>", $letter, $eosMetaDataPrefix);
		$eosMetaDataPrefix = str_replace("<username>", $username, $eosMetaDataPrefix);

		$this->eosPrefix = $eosPrefix;
		$this->eosMetaDataPrefix = $eosMetaDataPrefix;
		$this->eosProjectPrefix = $eosProjectPrefix;
		*/
		$this->username = $username;
	}

	/**
	 * Converts an owncloud path like 'files' or 'files/A/B/file.txt' to
	 * an eos path like '/eos/user/l/labrador/' or '/eos/user/l/labrador/A/B/file.txt'
	 * To convert an owncloud path to an eos path we always need an user context.
	 * @param $ocPath
	 * @return string
	 */
	public function toEos($ocPath) {
		$ocPath = ltrim($ocPath, '/');
		$eosPath = $this->_toEos($ocPath);
		$this->logger->debug("TRANSLATOR OC('$ocPath') => EOS('$eosPath') USER('" . $this->username . "')");
		return $eosPath;
	}

	private function _toEos($ocPath) {

		$eosPrefix = sprintf("%s/%s/%s/",
			rtrim($this->instance->getPrefix(), '/') ,
			$this->username[0], // first username letter
			$this->username);

		$eosMetaDataPrefix = sprintf("%s/%s/%s/",
			rtrim($this->instance->getMetaDataPrefix(), '/') ,
			$this->username[0], // first username letter
			$this->username);

		$tempOcPath = $ocPath;

		// if path starts with projects means that we are pointing
		// to the project prefix.
		if(strpos($ocPath, 'projects') === 0) {
			$tempOcPath = substr($tempOcPath, strlen('projects'));
			$eosPath =  rtrim($this->instance->getProjectPrefix(), '/') . '/' . $tempOcPath;
			return $eosPath;
		}


		/*
		if(strpos($ocPath, 'instances') === 0) {
			$tempOcPath = trim(substr($tempOcPath, strlen('instances')), '/');
			$instanceName = explode('/', $tempOcPath)[0];
			$instanceInfo = $this->instanceMapper->getInstanceInfoByName($instanceName);
			if(!$instanceInfo) {
				throw new \Exception("Instance $instanceName not found");
			}
			$eosPath = $instanceInfo->getInstanceRootPath();
			return $eosPath;
		}
		*/


		if (strpos($ocPath, 'files') === 0) {
			$tempOcPath = substr($tempOcPath, 5);
		}

		$tempOcPath = trim($tempOcPath, '/');

		if (strpos($tempOcPath, '  project') === 0 && \OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
			$len = strlen('  project ');
			$nextSlash = strpos($tempOcPath, '/');
			if ($nextSlash === false) {
				$nextSlash = strlen($tempOcPath);
			}

			$project = substr($tempOcPath, $len, $nextSlash - $len); // skiclub
			$pathLeft = substr($tempOcPath, $nextSlash);
			$projectInfo = $this->projectMapper->getProjectInfoByProject($project);
			if(!$projectInfo) {
				throw new \Exception("Project $project not found");
			}
			$relativePath = $projectInfo->getProjectRelativePath();

			if ($relativePath) {
				return (rtrim($this->instance->getProjectPrefix(), '/') . '/' . trim($relativePath, '/') . '/' . trim($pathLeft, '/'));
			}
		}

		if(\OC::$server->getAppManager()->isInstalled("files_eosbrowser")) {
			if(strpos($tempOcPath, '  eos') === 0) {
				$len = strlen('  eos ');
				$nextSlash = strpos($tempOcPath, '/');
				if($nextSlash === false) {
					$nextSlash = strlen($tempOcPath);
				}

				$instance = substr($tempOcPath, $len, $nextSlash - $len);
				$pathLeft = substr($tempOcPath, $nextSlash);
				$instanceInfo = $this->instanceMapper->getInstanceInfoByName($instance);
				if(!$instanceInfo) {
					throw new \Exception('Instance $instance not found');
				}
				$config = [
					"name" => $instanceInfo->getInstanceName(),
					"mgmurl" => $instanceInfo->getInstanceMGMUrl(),
					"prefix" => $instanceInfo->getInstanceRootPath(),
				];
				\OC::$server->getCernBoxEosInstanceManager()->addInstance(new Instance($instanceInfo->getInstanceName(), $config));
				\OC::$server->getCernBoxEosInstanceManager()->setCurrentInstance($instanceInfo->getInstanceName());
				$eosPath = rtrim($instanceInfo->getInstanceRootPath(), '/') . '/' . trim($pathLeft, '/');
				return $eosPath;
			}
		}

		if ($ocPath === "") {
			//$eosPath = $this->eosPrefix. substr($this->username, 0, 1) . "/" . $this->username. "/";
			$eosPath = $eosPrefix;
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
			$split = explode("/", $ocPath);// [files, hola.txt] or [files]
			$last = "";
			if (count($split) >= 2) {
				$last = implode("/", array_slice($split, 1));
			}
			//$eosPath = $this->eosPrefix. substr($this->username, 0, 1) . "/" . $this->username. "/" . $last;
			$eosPath = $eosPrefix . $last;
			return $eosPath;
		} else {
			$eosPath = $eosMetaDataPrefix. $ocPath;
			return $eosPath;
		}
	}

	public function toOc($eosPath) {
		$ocPath = $this->_toOc($eosPath);
		$this->logger->debug("TRANSLATOR EOS('$eosPath') => OC('$ocPath') USER('" . $this->username. "')");
		return $ocPath;
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
	public function _toOc($eosPath) {
		if(\OC::$server->getAppManager()->isInstalled("files_eosbrowser")) {
			// check for global read-only EOS instances
			$instanceInfo = $this->instanceMapper->getInstanceInfoByPath($eosPath);
			if ($instanceInfo) {
				$ocPath = trim(substr($eosPath, strlen($instanceInfo->getInstanceRootPath())), '/');
				$ocPath = 'files/  eos ' . $instanceInfo->getInstanceName() . '/' . $ocPath;
				return $ocPath;
			}
		}

		if ($eosPath == $this->instance->getPrefix()) {
			return "";
		}

		if (strpos($eosPath, $this->instance->getMetaDataPrefix()) === 0) {
			$prefixSize = strlen($this->instance->getMetaDataPrefix());
			$metaPath = trim(substr($eosPath, $prefixSize), '/');

			// split should be something like [l, labrador, cache, 123]
			$split = explode("/", $metaPath);
			array_shift($split); // shift first letter
			array_shift($split); // shift username
			$ocPath = '/' . implode("/", $split);
			return $ocPath;
		} else if (strpos($eosPath, $this->instance->getPrefix()) === 0) {
			$prefixSize = strlen($this->instance->getPrefix());
			$dataPath = trim(substr($eosPath, $prefixSize), '/');

			// split should be something like [l, labrador, photos, jamaica.png]
			$split   = explode("/", $dataPath);
			array_shift($split); // shift first letter
			array_shift($split); // shift username
			$ocPath = 'files/' . implode("/", $split);
			return $ocPath;
		} else if (strpos($eosPath, $this->instance->getRecycleDir()) === 0) {
			return false;
		} else if (strpos($eosPath, $this->instance->getProjectPrefix()) === 0) {
			$prefixSize = strlen($this->instance->getProjectPrefix());
			// a/atlas-software-dist or skiclub/more/contents or just b
			$pathWithoutProjectPrefix = rtrim(substr($eosPath, $prefixSize), '/');
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
			if (count($parts) === 1) {
				// path can contain a letter or a project name, b or it-storage
				// if it is a letter we omit it.
				$letterOrProjectName = $parts[0];
				if (strlen($letterOrProjectName) > 1) { // projects are bigger that one letter
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
				if (strlen($firstElement) === 1) {
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

			if ($projectName) {
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

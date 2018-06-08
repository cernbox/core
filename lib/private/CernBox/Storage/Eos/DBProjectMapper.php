<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/15/16
 * Time: 12:55 PM
 */

namespace OC\CernBox\Storage\Eos;


class DBProjectMapper implements  IProjectMapper {

	const EXPIRE_TIME_SECONDS = 1800; // 30 minutes
	const KEY_GET_ALL_PROJECTS = 'allProjects';

	private $logger;

	/**
	 * @var ProjectInfo[]
	 */
	private $infos;
	private $redis;

	private $groupManager;

	public function __construct() {
		$this->logger = \OC::$server->getLogger();
		$this->groupManager = \OC::$server->getGroupManager();
		$this->redis = \OC::$server->getGetRedisFactory()->getInstance();
		$infos = array();
		if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
			$data = \OC_DB::prepare('SELECT * FROM cernbox_project_mapping')->execute()->fetchAll();
			foreach($data as $projectData) {
				$project = $projectData['project_name'];
				$relativePath = $projectData['eos_relative_path'];
				$owner = $projectData['project_owner'];
				$info = new ProjectInfo($project, $owner, $relativePath);
				$infos[basename($relativePath)] = $info;
			}
		}

		$this->infos = $infos;
	}

	/**
	 * @param $relativeProjectPath. /eos/project/skiclub or /eos/project/skiclub/more/contents
	 * @return mixed|null|ProjectInfo
	 */
	public function getProjectInfoByPath($relativeProjectPath) {
		$this->logger->info(__FUNCTION__  . ": $relativeProjectPath");
		foreach($this->infos as $project => $info) {
			if(strpos($relativeProjectPath, $info->getProjectRelativePath()) === 0) {
				return $info;
			}
		}
		return null;
	}

	public function getProjectInfoByProject($projectName) {
		$this->logger->info(__FUNCTION__ . ": $projectName");
		foreach($this->infos as $project => $info) {
			if($project === $projectName) {
				return $info;
			}
		}
		return null;
	}

	public function getProjectInfoByUser($username) {
		$this->logger->info(__FUNCTION__ .": $username");
		foreach($this->infos as $project => $info) {
			if($info->getProjectOwner() === $username) {
				return $info;
			}
		}
		return null;
	}

	public function getAllMappings() {
		return $this->infos;
	}

	public function isReader($username, $projectName) {
		$project = $this->getProjectInfoByProject($projectName);
		if(!$project) {
			return false;
		}
		return $this->groupManager->isInGroup($username, $project->getProjectReaders());
	}

	public function isWriter($username, $projectName) {
		$project = $this->getProjectInfoByProject($projectName);
		if(!$project) {
			return false;
		}
		return $this->groupManager->isInGroup($username, $project->getProjectWriters());
	}

	public function isAdmin($username, $projectName) {
		$project = $this->getProjectInfoByProject($projectName);
		if(!$project) {
			return false;
		}
		return $this->groupManager->isInGroup($username, $project->getProjectAdmins());
	}

	public function hasAccess($username, $projectName) {
		$project = $this->getProjectInfoByProject($projectName);
		if(!$project) {
			return false;
		}
		if ($username === $project->getProjectOwner() ||
			$this->groupManager->isInGroup($username, $project->getProjectAdmins()) ||
			$this->groupManager->isInGroup($username, $project->getProjectWriters()) ||
			$this->groupManager->isInGroup($username, $project->getProjectReaders())) {
			return true;
		}
		return false;
	}

	public function getProjectsUserIsAdmin($username) {
		$projects = [];
		foreach($this->infos as $info) {
			if($this->groupManager->isInGroup($username, $info->getProjectAdmins())) {
				$projects[] = $info;
			}
		}
		return $projects;
	}

	public function getProjectsUserHasAccess($username) {
		$key = self::KEY_GET_ALL_PROJECTS . "-" . $username;
		$projects = [];
		$val = $this->redis->get($key);
		if ($val) {
			$val = json_decode($val, true);
			foreach($val as $v) {
				$projects[basename($v[2])] = new ProjectInfo($v[0], $v[1], $v[2]);
			}
		} else {
			$entries = [];
			foreach($this->infos as $info) {
				if($this->hasAccess($username, $info->getProjectName())) {
					$entries[] = [$info->getProjectName(), $info->getProjectOwner(), $info->getProjectRelativePath()];
					$projects[] = $info;
				}
			}
			$this->redis->setex($key, self::EXPIRE_TIME_SECONDS, json_encode($entries));
		}
		return $projects;

	}
}

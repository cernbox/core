<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/15/16
 * Time: 12:55 PM
 */

namespace OC\CernBox\Storage\Eos;


class DBProjectMapper implements  IProjectMapper {

	private $logger;

	/**
	 * @var ProjectInfo[]
	 */
	private $infos;

	public function __construct() {
		if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
			$this->logger = \OC::$server->getLogger();
			$data = \OC_DB::prepare('SELECT * FROM cernbox_project_mapping')->execute()->fetchAll();
			$infos = array();
			foreach($data as $projectData)
			{
				$project = $projectData['project_name'];
				$relativePath = $projectData['eos_relative_path'];
				$owner = $projectData['project_owner'];
				$info = new ProjectInfo($project, $owner, $relativePath);
				$infos[$project] = $info;
			}

			$this->infos = $infos;
		}
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
			if($info->getProjectName() === $projectName) {
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


}

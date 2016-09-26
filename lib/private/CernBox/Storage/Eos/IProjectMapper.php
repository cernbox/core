<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/9/16
 * Time: 10:11 AM
 */

namespace OC\CernBox\Storage\Eos;


interface IProjectMapper {
	/**
	 * @param $relativeProjectPath
	 * @return ProjectInfo
	 */
 	public function getProjectInfoByPath($relativeProjectPath); // a/atlas-software or skiclub

	/**
	 * @param $projectName
	 * @return ProjectInfo
	 */
	public function getProjectInfoByProject($projectName);

	/**
	 * @param $username
	 * @return ProjectInfo
	 */
	public function getProjectInfoByUser($username);

	/**
	 * @return ProjectInfo[]
	 */
	public function getAllMappings();
}

class ProjectInfo {
	private $projectName;
	private $projectOwner;
	private $projectRelativePath;

	public function __construct($projectName, $projectOwner, $projectRelativePath) {
		$this->projectName = $projectName;
		$this->projectOwner = $projectOwner;
		$this->projectRelativePath = $projectRelativePath;
	}

	public function getProjectName() {
		return $this->projectName;
	}

	public function getProjectOwner()  {
		return $this->projectOwner;
	}

	public function getProjectRelativePath() {
		return $this->projectRelativePath;
	}

}
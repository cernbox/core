<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 10/20/16
 * Time: 9:12 AM
 */

namespace OC\CernBox\Storage\Eos;


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

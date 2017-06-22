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
	private $projectReaders;
	private $projectWriters;
	private $projectAdmins;

	public function __construct($projectName, $projectOwner, $projectRelativePath) {
		$this->projectName = $projectName;
		$this->projectOwner = $projectOwner;
		$this->projectRelativePath = $projectRelativePath;
		$basename = basename($this->projectRelativePath);
		$this->projectReaders = 'cernbox-project-' . $basename . '-readers';
		$this->projectWriters = 'cernbox-project-' . $basename. '-writers';
		$this->projectAdmins = 'cernbox-project-' . $basename. '-admins';
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

	public function getProjectReaders() {
		return $this->projectReaders;
	}

	public function getProjectWriters() {
		return $this->projectWriters;
	}

	public function getProjectAdmins() {
		return $this->projectAdmins;
	}

}

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
		// the project e-groups are lower case, see: INC1501057 
		$name = strtolower($this->projectName);
		$this->projectReaders = 'cernbox-project-' . $name . '-readers';
		$this->projectWriters = 'cernbox-project-' . $name . '-writers';
		$this->projectAdmins = 'cernbox-project-' . $name . '-admins';
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

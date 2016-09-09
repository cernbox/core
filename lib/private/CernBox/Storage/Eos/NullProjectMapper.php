<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/9/16
 * Time: 10:19 AM
 */

namespace OC\CernBox\Storage\Eos;


class NullProjectMapper implements IProjectMapper {
	public function getProjectNameForPath($path) {
		return null;
	}

	public function getProjectRelativePath($project) {
		return null;
	}

	public function getProjectOwner($projectName) {
		return null;
	}
}
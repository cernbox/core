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


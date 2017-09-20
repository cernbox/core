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


	/**
	 * @param $username string
	 * @param $projectName string
	 * @return bool
	 */
	public function isReader($username, $projectName);

	/**
	 * @param $username
	 * @param $projectName
	 * @return bool
	 */
	public function isWriter($username, $projectName);

	/**
	 * @param $username
	 * @param $projectName
	 * @return bool
	 */
	public function isAdmin($username, $projectName);

	/**
	 * @param $username
	 * @param $projectName
	 * @return  bool
	 */
	public function hasAccess($username, $projectName);

	/**
	 * @param $username
	 * @return []ProjectInfo
	 */
	public function getProjectsUserIsAdmin($username);

	/**
	 * @param $username
	 * @return []ProjectInfo
	 */
	public function getProjectsUserHasAccess($username);
}


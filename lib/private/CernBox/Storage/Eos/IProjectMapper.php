<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/9/16
 * Time: 10:11 AM
 */

namespace OC\CernBox\Storage\Eos;


interface IProjectMapper {
 	public function getProjectNameForPath($eosPath);
	public function getProjectRelativePath($projectName);
	public function getProjectOwner($projectName);
}
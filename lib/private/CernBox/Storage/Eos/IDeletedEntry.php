<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/21/16
 * Time: 11:53 AM
 */

namespace OC\CernBox\Storage\Eos;


interface IDeletedEntry {
	public function getRestoreKey();
	public function getOriginalPath();
	public function getSize();
	public function getDeletionMTime();
	public function getType();
}
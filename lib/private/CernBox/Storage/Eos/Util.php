<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/7/16
 * Time: 3:58 PM
 */

namespace OC\CernBox\Storage\Eos;


use OC\CernBox\Storage\MetaDataCache\IMetaDataCache;

class Util {
	private $metaDataCache;

	/**
	 * Util constructor.
	 *
	 * @param $metaDataCache
	 */
	public function __construct(IMetaDataCache $metaDataCache) {
		$this->metaDataCache = $metaDataCache;
	}

	public function getUidAndGidForUsername($username) {
		$cached = $this->metaDataCache->getUidAndGid($username);
		if($cached) {
			$cached[0] = (int)$cached[0];
			$cached[1] = (int)$cached[1];
			return $cached;
		}

		$cmd     = "id " . $username;
		$result  = null;
		$errcode = null;
		exec($cmd, $result, $errcode);
		$list = array();
		if ($errcode === 0) {
			$lines    = explode(" ", $result[0]);
			$line_uid = $lines[0];
			$line_gid = $lines[1];

			$split_uid = explode("=", $line_uid);
			$split_gid = explode("=", $line_gid);

			$end_uid = explode("(", $split_uid[1]);
			$end_gid = explode("(", $split_gid[1]);

			$uid = $end_uid[0];
			$gid = $end_gid[0];

			$list[] = $uid;
			$list[] = $gid;
		} else {
			return false;
		}

		if (count($list) != 2) {
			return false;
		}

		$list[0] = (int)$list[0];
		$list[1] = (int)$list[1];
		$this->metaDataCache->setUidAndGid($username, $list);
		return $list;
	}
}

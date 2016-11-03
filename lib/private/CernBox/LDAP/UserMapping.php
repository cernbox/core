<?php


namespace OC\CernBox\LDAP;

class UserMapping extends \OCA\User_LDAP\Mapping\UserMapping {
	private $util;
	private $ldapSQLDatabase;

	public function __construct(\OCP\IDBConnection $dbc) {
		$this->util = new Util();
		$this->ldapSQLDatabase = new CachedDatabase();
		parent::__construct($dbc);
	}

	public function getDNByName($name) {
		return $this->util->getUserDN($name);
	}

	public function getNameByDN($fdn) {
		return $this->util->getUserCN($fdn);
	}

	public function getNamesBySearch($search) {
		$names = $this->ldapSQLDatabase->getAllUsersMatchingCriteria($search, ['cn'], ['cn']);
		return array_map(function ($item) {
			return $item['cn'];
		}, $names);
	}

	public function getNameByUUID($uuid) {
		return $uuid;
	}

	public function getList($offset = null, $limit = null) {
		$users = $this->ldapSQLDatabase->getAllUsersMatchingCriteria('', ['cn'], ['cn'], $limit);
		$list = [];
		$start = ($offset == null? 0 : $offset);
		$end = ($limit == null? count($result) : $limit);
		for($i = $start; $i < $end; $i++)
		{
			$cn = $users[$i]['cn'];
			$token = [];
			$token['dn'] = $this->util->getUserDN($cn);
			$token['name'] = $cn;
			$token['uuid'] = $cn;
			$list[] = $token;
		}
		return $list;
	}

	public function map($fdn, $name, $uuid) {
		// nop
	}

	public function clear() {
		// nop
	}

	public function setDNbyUUID($fdn, $uuid) {
		// nop
	}
}
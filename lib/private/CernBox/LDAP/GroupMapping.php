<?php

namespace OC\CernBox\LDAP;

use OCP\IDBConnection;

class GroupMapping  extends \OCA\User_LDAP\Mapping\GroupMapping {
	private $util;
	private $ldapSQLDatabase;

	public function __construct(IDBConnection $dbc) {
		$this->util = new Util();
		$this->ldapSQLDatabase = new CachedDatabase();
		parent::__construct($dbc);
	}

	public function getDNByName($name) {
		return $this->util->getGroupDN($name);
	}


	public function getNameByDN($fdn) {
		return $this->util->getGroupCN($fdn);
	}

	public function getNamesBySearch($search) {
		$names = $this->ldapSQLDatabase->getAllGroupsMatchingCriteria($search);
		return array_map(function($item) {
			return $item['name'];
		}, $names);
	}

	public function getNameByUUID($uuid) {
		return $uuid;
	}

	public function getList($offset = null, $limit = null) {
		$groups = $this->ldapSQLDatabase->getAllGroupsMatchingCriteria('', ['cn'], ['cn'], $limit);
		$list = [];
		$start = ($offset == null? 0 : $offset);
		$end = ($limit == null? count($groups) : $limit);
		for($i = $start; $i < $end; $i++)
		{
			$cn = $groups[$i]['cn'];
			$token = [];
			$token['dn'] = $this->util->getGroupDN($cn);
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
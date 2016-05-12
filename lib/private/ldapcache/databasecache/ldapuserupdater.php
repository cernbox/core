<?php

namespace OC\LDAPCache\DatabaseCache;

class LDAPUserUpdater extends \OC\LDAPCache\LDAPStandardUpdater
{
	public function __construct(&$ldapAccess)
	{
		parent::__construct($ldapAccess);
		$this->sqlInsertTypes = 'siss';
		$this->table = 'cernbox_ldap_users';
		$this->tableColumns = ['cn','uidnumber','displayname','employeetype'];
		
		$this->ldapFunc = 'searchUsers';
		$this->ldapAttr = ['cn', 'uidNumber', 'displayName', 'employeeType'];
	}
	
	public function fillCache()
	{
		// TODO Cache most used data into redis
		$this->fillDatabase();
	}
}
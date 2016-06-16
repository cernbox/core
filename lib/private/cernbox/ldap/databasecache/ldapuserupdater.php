<?php

namespace OC\Cernbox\LDAP\DatabaseCache;

class LDAPUserUpdater extends \OC\Cernbox\LDAP\LDAPStandardUpdater
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
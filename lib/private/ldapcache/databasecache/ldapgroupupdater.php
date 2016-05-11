<?php

namespace OC\LDAPCache\DatabaseCache;

class LDAPGroupUpdater extends \OC\LDAPCache\LDAPStandardUpdater
{
	public function __construct(&$ldapAccess)
	{
		parent::__construct($ldapAccess);
		$this->sqlInsertTypes = 's';
		$this->table = 'cernbox_ldap_groups';
		$this->tableColumns = ['cn'];
		
		$this->ldapFunc = 'searchGroups';
		$this->ldapAttr = ['cn'];
	}
	
	public function fillCache()
	{
		$this->fillDatabase();
	}
}
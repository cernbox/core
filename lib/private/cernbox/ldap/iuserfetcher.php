<?php

namespace OC\Cernbox\LDAP;

interface IUserFetcher
{
	public function fetchUsers($priority);
}
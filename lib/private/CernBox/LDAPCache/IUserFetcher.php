<?php

namespace OC\CernBox\LDAPCache;

interface IUserFetcher
{
	public function fetchUsers($priority);
}
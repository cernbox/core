<?php

namespace OC\LDAPCache;

interface IUserFetcher
{
	public function fetchUsers($priority);
}
<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Dominik Schmidt <dev@dominik-schmidt.de>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\user_ldap;

use OC\Cache\LDAPDatabase;
use OCA\user_ldap\lib\BackendUtility;
use OCA\user_ldap\lib\Access;
use OCA\user_ldap\lib\user\OfflineUser;
use OCA\User_LDAP\lib\User\User;
use OCP\IConfig;
//use OCA\user_ldap\lib\LDapCache;
use OCA\user_ldap\lib\LDAPUtil;

class USER_LDAP extends BackendUtility implements \OCP\IUserBackend, \OCP\UserInterface {
	/** @var string[] $homesToKill */
	protected $homesToKill = array();

	/** @var \OCP\IConfig */
	protected $ocConfig;

	/**
	 * @param \OCA\user_ldap\lib\Access $access
	 * @param \OCP\IConfig $ocConfig
	 */
	public function __construct(Access $access, IConfig $ocConfig) {
		parent::__construct($access);
		$this->ocConfig = $ocConfig;
	}

	/**
	 * checks whether the user is allowed to change his avatar in ownCloud
	 * @param string $uid the ownCloud user name
	 * @return boolean either the user can or cannot
	 */
	public function canChangeAvatar($uid) {
		/*$user = $this->access->userManager->get($uid);
		if(!$user instanceof User) {
			return false;
		}
		if($user->getAvatarImage() === false) {
			return true;
		}*/

		return false;
	}
	
	/**
	 * returns the username for the given login name, if available
	 *
	 * @param string $loginName
	 * @return string|false
	 */
	public function loginName2UserName($loginName) {
		
		try {
			$ldapRecord = $this->getLDAPUserByLoginName($loginName);
			$user = $this->access->userManager->get($ldapRecord);
			if($user instanceof OfflineUser) {
				return false;
			}
			
			return $user->getUsername();
		} catch (\Exception $e) {
			return false;
		}
	}
	
	/**
	 * returns an LDAP record based on a given login name
	 *
	 * @param string $loginName
	 * @return array
	 * @throws \Exception
	 */
	public function getLDAPUserByLoginName($loginName) {
		return \OCA\user_ldap\lib\LDAPUtil::getUserDN($loginName);
	}

	/**
	 * Check if the password is correct
	 * @param string $uid The username
	 * @param string $password The password
	 * @return false|string
	 *
	 * Check if the password is correct without logging in the user
	 */
	public function checkPassword($uid, $password) {
		try {
			$ldapRecord = $this->getLDAPUserByLoginName($uid);
		} catch(\Exception $e) {
			return false;
		}
		$dn = $ldapRecord;
		$user = $this->access->userManager->get($dn);
		
		if(!$user instanceof User) {
			\OCP\Util::writeLog('user_ldap',
				'LDAP Login: Could not get user object for DN ' . $dn .
				'. Maybe the LDAP entry has no set display name attribute?',
				\OCP\Util::WARN);
			return false;
		}
		if($user->getUsername() !== false) {
			//are the credentials OK?
			if(!$this->access->areCredentialsValid($dn, $password)) {
				return false;
			}

			$this->access->cacheUserExists($user->getUsername());
			$user->processAttributes($ldapRecord);
			$user->markLogin();

			return $user->getUsername();
		}

		return false;
	}

	/**
	 * Get a list of all users
	 * @param string $search
	 * @param null|int $limit
	 * @param null|int $offset
	 * @return string[] an array of all uids
	 */
	public function getUsers($search = '', $limit = null, $offset = 0, $searchParams = null) {
		$search = $this->access->escapeFilterPart($search, true);
		
		$slicedParams = str_split($searchParams);
		$ldap_users = [];
		if($slicedParams[0] == 'a')
		{
			$ldap_users = \OC\Cache\LDAPDatabase::fetchUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn'], $limit);
		}
		else
		{
			$ldap_users = \OC\Cache\LDAPDatabase::fetchPrimaryUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn'], $limit);
		}
		
		return $ldap_users;
	}

	/**
	 * checks whether a user is still available on LDAP
	 * @param string|\OCA\User_LDAP\lib\user\User $user either the ownCloud user
	 * name or an instance of that user
	 * @return bool
	 */
	public function userExistsOnLDAP($user) {
		
		if(is_string($user)) 
		{
			$name = $user;// = $this->access->userManager->get($user);
		}
		else if(!$user instanceof User) 
		{
			return false;
		}
		else
		{
			$name = $user->getUsername();
		}
		
		$result = \OC\Cache\LDAPDatabase::getUserData($name);
		
		return ($result && count($result) > 0);
	}

	/**
	 * check if a user exists
	 * @param string $uid the username
	 * @return boolean
	 * @throws \Exception when connection could not be established
	 */
	public function userExists($uid) {
		
		if(!$uid || empty($uid))
			return false;
		
		//getting dn, if false the user does not exist. If dn, he may be mapped only, requires more checking.
		$user = $this->access->userManager->get($uid);
		
		if(is_null($user)) {
			\OCP\Util::writeLog('user_ldap', 'No DN found for '.$uid.' on '.
				$this->access->connection->ldapHost, \OCP\Util::DEBUG);
			$this->access->connection->writeToCache('userExists'.$uid, false);
			return false;
		} else if($user instanceof OfflineUser) {
			//express check for users marked as deleted. Returning true is
			//necessary for cleanup
			return true;
		}

		$result = $this->userExistsOnLDAP($user);
		//$this->access->connection->writeToCache('userExists'.$uid, $result);
		if($result === true) {
			$user->update();
		}
		
		return $result;
	}

	/**
	* returns whether a user was deleted in LDAP
	*
	* @param string $uid The username of the user to delete
	* @return bool
	*/
	public function deleteUser($uid) {
		$marked = $this->ocConfig->getUserValue($uid, 'user_ldap', 'isDeleted', 0);
		if(intval($marked) === 0) {
			\OC::$server->getLogger()->notice(
				'User '.$uid . ' is not marked as deleted, not cleaning up.',
				array('app' => 'user_ldap'));
			return false;
		}
		\OC::$server->getLogger()->info('Cleaning up after user ' . $uid,
			array('app' => 'user_ldap'));

		//Get Home Directory out of user preferences so we can return it later,
		//necessary for removing directories as done by OC_User.
		$home = $this->ocConfig->getUserValue($uid, 'user_ldap', 'homePath', '');
		$this->homesToKill[$uid] = $home;
		$this->access->getUserMapper()->unmap($uid);

		return true;
	}

	/**
	* get the user's home directory
	* @param string $uid the username
	* @return string|bool
	*/
	public function getHome($uid) {
		
		if(isset($this->homesToKill[$uid]) && !empty($this->homesToKill[$uid])) {
			//a deleted user who needs some clean up
			return $this->homesToKill[$uid];
		}
		
		// user Exists check required as it is not done in user proxy!
		if(!$this->userExists($uid)) {
			return false;
		}

		$cacheKey = 'getHome'.$uid;
		if($this->access->connection->isCached($cacheKey)) {
			return $this->access->connection->getFromCache($cacheKey);
		}
		$user = $this->access->userManager->get($uid);
		$path = $user->getHomePath();
		$this->access->cacheUserHome($uid, $path);
		
		return $path;
	}

	/**
	 * get display name of the user
	 * @param string $uid user ID of the user
	 * @return string|false display name
	 */
	public function getDisplayName($uid) {
		$result = \OC\Cache\LDAPDatabase::getUserData($uid, ['cn'], ['displayname'])[0];

		if(!$result)
			return false;
		
		return $result['displayname'];
	}

	/**
	 * Get a list of all display names
	 * @param string $search
	 * @param string|null $limit
	 * @param string|null $offset
	 * @return array an array of all displayNames (value) and the corresponding uids (key)
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null, $searchParams = null) {
		$search = $this->access->escapeFilterPart($search, true);
		
		$slicedParams = str_split($searchParams);
		$ldap_users = [];
		if($slicedParams[0] == 'a')
		{
			$ldap_users = \OC\Cache\LDAPDatabase::fetchUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn', 'displayname'], $limit);
		}
		else
		{
			$ldap_users = \OC\Cache\LDAPDatabase::fetchPrimaryUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn', 'displayname'], $limit);
		}
		
		$users = [];
		foreach($ldap_users as $token)
		{
			$users[$token['cn']] = $token['displayname'];
		}
		
		return $users;
	}

	/**
	* Check if backend implements actions
	* @param int $actions bitwise-or'ed actions
	* @return boolean
	*
	* Returns the supported actions as int to be
	* compared with OC_USER_BACKEND_CREATE_USER etc.
	*/
	public function implementsActions($actions) {
		return (bool)((\OC_User_Backend::CHECK_PASSWORD
			| \OC_User_Backend::GET_HOME
			| \OC_User_Backend::SET_DISPLAYNAME
			| \OC_User_Backend::GET_DISPLAYNAME
			| \OC_User_Backend::PROVIDE_AVATAR
			| \OC_User_Backend::COUNT_USERS)
			& $actions);
	}
	
	// NADIR: TODO Implement me
	public function setDisplayName($userId, $displayName) {
		return true;
	}
	
	/**
	 * @return bool
	 */
	public function hasUserListings() {
		return true;
	}

	/**
	 * counts the users in LDAP
	 *
	 * @return int|bool
	 */
	public function countUsers() {
		return \OC\Cache\LDAPDatabase::countNumberOfUsers();
	}

	/**
	 * Backend name to be shown in user management
	 * @return string the name of the backend to be shown
	 */
	public function getBackendName(){
		return 'LDAP';
	}

}

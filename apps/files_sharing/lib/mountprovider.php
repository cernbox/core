<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCA\Files_Sharing;

use OCA\Files_Sharing\Propagation\PropagationManager;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\IUser;
use OC\Files\ObjectStore\EosUtil;

class MountProvider implements IMountProvider {
	
	private $notMountingURLs = ['ocs/v1.php/apps/files_sharing/api', 'core/ajax/share.php', '.css'];
	
	/**
	 * @var \OCP\IConfig
	 */
	protected $config;

	/**
	 * @var \OCA\Files_Sharing\Propagation\PropagationManager
	 */
	protected $propagationManager;

	/**
	 * @param \OCP\IConfig $config
	 * @param \OCA\Files_Sharing\Propagation\PropagationManager $propagationManager
	 */
	public function __construct(IConfig $config, PropagationManager $propagationManager) {
		$this->config = $config;
		$this->propagationManager = $propagationManager;
	}


	/**
	 * Get all mountpoints applicable for the user and check for shares where we need to update the etags
	 *
	 * @param \OCP\IUser $user
	 * @param \OCP\Files\Storage\IStorageFactory $storageFactory
	 * @return \OCP\Files\Mount\IMountPoint[]
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $storageFactory) {
		/** CERNBOX SHARE OPTIMIZATION PULL REQUEST PATCH */
		// HUGO in an normal request this hook is called at least twice, reducing performance, so we cached
		if (isset ( $GLOBALS ["shared_setup_hook"] )) {
			return;
		}
		
		// KUBA: avoid mounting all shared storages if not necessary:
		// \OCP\Util::writeLog('KUBA',"PATH" . __FUNCTION__ . "($kuba_path) dir=".$_GET["dir"]." REQ_URI=".$_SERVER['REQUEST_URI'], \OCP\Util::ERROR);
		
		if(\OC\Files\ObjectStore\EosInstanceManager::isInGlobalInstance())
		{
			return;
		}
		
		$mount_shared_stuff = false;
		
		$keepChecking = true;
		$request = $_SERVER['REQUEST_URI'];
		foreach($this->notMountingURLs as $url)
		{
			if(strpos($request, $url) !== FALSE)
			{
				$keepChecking = false;
				break;
			}
		}
		
		if ($keepChecking)
		{
		
		if (isset($_GET['view']) && ($_GET ["view"] == "sharingin" or $_GET ["view"] == "sharingout" or $_GET ["view"] == "sharinglinks")) {
			$mount_shared_stuff = true;
		} else {
			$uri_path_array = array ();
				
			if ($_SERVER ['REQUEST_METHOD'] === 'POST') {
				if (isset ( $_POST ["dir"] )) {
					array_push ( $uri_path_array, $_POST ["dir"] );
				} else {
					$mount_shared_stuff = true; // if dir is not defined, take the safe bet
				}
			} else {
				if (isset ( $_GET ["dir"] )) {
					array_push ( $uri_path_array, $_GET ["dir"] );
				} else {
					$mount_shared_stuff = true; // if dir is not defined, take the safe bet
				}
		
				if (isset ( $_GET ["files"] )) {
						
					if (is_array ( $_GET ["files"] )) {
						$uri_path_array = array_merge ( $uri_path_array, $_GET ["files"] );
					} else {
						array_push ( $uri_path_array, $_GET ["files"] );
					}
				}
			}
				
			foreach ( $uri_path_array as $uri_path ) {
				//\OCP\Util::writeLog ( 'KUBA', "OPTIMIZATION" . __FUNCTION__ . " files=" . $_GET ["files"] . " uri_path=" . $uri_path . " ", \OCP\Util::ERROR );
				if ($uri_path) {
						
					if (EosUtil::isProjectURIPath ( $uri_path )) {
						$mount_shared_stuff = true;
					} elseif (EosUtil::isSharedURIPath ( $uri_path )) {
						$mount_shared_stuff = true;
					}
						
					// \OCP\Util::writeLog('KUBA',"EosUtil::isSharedURIPath" . __FUNCTION__ . "uri_path=|${uri_path}| ->".EosUtil::isSharedURIPath($uri_path), \OCP\Util::ERROR);
				}
			}
		}
		}
		
		if (! $mount_shared_stuff) {
			// \OCP\Util::writeLog('KUBA',"PATH" . __FUNCTION__ . "($kuba_path) dir=".$_GET["dir"]." NOT MOUNTING=".$mount_shared_stuff, \OCP\Util::ERROR);
				
			return; // OPTIMIZATION ON/OFF
		}
		
		// \OCP\Util::writeLog('KUBA',"PATH" . __FUNCTION__ . "($kuba_path) dir=".$_GET["dir"]." MOUNTING=".$mount_shared_stuff, \OCP\Util::ERROR);
		
		$shares = \OCP\Share::getItemsSharedWithUser('file', $user->getUID());
		$propagator = $this->propagationManager->getSharePropagator($user);
		$propagator->propagateDirtyMountPoints($shares);
		$shares = array_filter($shares, function ($share) {
			return $share['permissions'] > 0;
		});
		$shares = array_map(function ($share) use ($user, $storageFactory) {
			// for updating etags for the share owner when we make changes to this share.
			$ownerPropagator = $this->propagationManager->getChangePropagator($share['uid_owner']);

			return new SharedMount(
				'\OC\Files\Storage\Shared',
				'/' . $user->getUID() . '/' . $share['file_target'],
				array(
					'propagationManager' => $this->propagationManager,
					'propagator' => $ownerPropagator,
					'share' => $share,
					'user' => $user->getUID()
				),
				$storageFactory
			);
		}, $shares);
		// array_filter removes the null values from the array
		/** CERNBOX SHARE OPTIMIZATION PULL REQUEST PATCH */
		$GLOBALS ["shared_setup_hook"] = true;
		
		return array_filter($shares);
	}
}

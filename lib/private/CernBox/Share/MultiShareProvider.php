<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/28/16
 * Time: 1:14 PM
 */

namespace OC\CernBox\Share;


use OCP\Files\Node;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShareProvider;


class MultiShareProvider implements  IShareProvider {


	private $rootFolder;
	private $logger;
	private $providerHandlers = array();

	public function __construct($userRootFolder) {
		$this->logger = \OC::$server->getLogger();
		$this->rootFolder = $userRootFolder;
		$this->providerHandlers = [
			Share::SHARE_TYPE_USER => new UserShareProvider($this->rootFolder),
			Share::SHARE_TYPE_GROUP => new GroupShareProvider($this->rootFolder),
			Share::SHARE_TYPE_LINK => new LinkShareProvider($this->rootFolder)
		];
	}

	public function identifier() {
		//return "cernbox-multishare-provider";
		return "ocinternal";
	}

	public function create(\OCP\Share\IShare $share) {
		$provider = $this->getShareProvider($share->getShareType());
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function update(\OCP\Share\IShare $share) {
		$provider = $this->getShareProvider($share->getShareType());
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function delete(\OCP\Share\IShare $share) {
		$provider = $this->getShareProvider($share->getShareType());
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function deleteFromSelf(\OCP\Share\IShare $share, $recipient) {
		$provider = $this->getShareProvider($share->getShareType());
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function move(\OCP\Share\IShare $share, $recipient) {
		$provider = $this->getShareProvider($share->getShareType());
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$provider = $this->getShareProvider($shareType);
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function getShareById($id, $recipientId = null) {
		foreach($this->providerHandlers as $type => $provider) {
			$args = func_get_args();
			$share = call_user_func_array(array($provider, __FUNCTION__), $args);
			if($share) {
				return $share;
			}
		}
		throw new ShareNotFound();
	}

	public function getSharesByPath(Node $path) {
		$shares = array();
		foreach($this->providerHandlers as $type => $provider) {
			if($type !== Share::SHARE_TYPE_LINK ) {
				$args = func_get_args();
				array_merge($shares, call_user_func_array(array($provider, __FUNCTION__), $args));
			}
		}
		return $shares;
	}

	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		$provider = $this->getShareProvider($shareType);
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function getShareByToken($token) {
		$provider = $this->getShareProvider(Share::SHARE_TYPE_LINK);
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function userDeleted($uid, $shareType) {
		$provider = $this->getShareProvider($shareType);
		$args = func_get_args();
		return call_user_func_array(array($provider, __FUNCTION__), $args);
	}

	public function groupDeleted($gid) {
		foreach($this->providerHandlers as $type => $provider) {
			if($type !== Share::SHARE_TYPE_LINK ) {
				$args = func_get_args();
				call_user_func_array(array($provider, __FUNCTION__), $args);
			}
		}
	}

	public function userDeletedFromGroup($uid, $gid) {
		foreach($this->providerHandlers as $type => $provider) {
			if($type !== Share::SHARE_TYPE_LINK ) {
				$args = func_get_args();
				call_user_func_array(array($provider, __FUNCTION__), $args);
			}
		}
	}

	/**
	 *FIXME(labkode): ownCloud adds this method to its provider despite not being
	 * listed in the IProvider.
	 * @return array
	 */
	public function getChildren() {
		return array();
	}

	/**
	 * @param $shareType
	 * @return IShareProvider
	 * @throws \Exception
	 */
	private function getShareProvider($shareType) {
		/** @var ICernboxProvider */
		$provider = isset($this->providerHandlers[$shareType]) ? $this->providerHandlers[$shareType] : false;

		if(!$provider) {
			$this->logger->error(\OC_User::getUser() . ' tried to share using an unknown share type : ' . $shareType);
			throw new \Exception('Share type not supported');
		}

		return $provider;
	}
}
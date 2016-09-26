<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 03/08/16
 * Time: 12:02
 */

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\ICacheEntry;
class CacheEntry implements ICacheEntry, \ArrayAccess, \JsonSerializable
{

    /**
     * @var array
     */
    private $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function getId() {
        return (int)$this->data['fileid'];
    }

    public function getStorageId() {
        return $this->data['storage'];
    }


    public function getPath() {
        return $this->data['path'];
    }


    public function getName() {
        return $this->data['name'];
    }


    public function getMimeType() {
        return $this->data['mimetype'];
    }


    public function getMimePart() {
        return $this->data['mimepart'];
    }

    public function getSize() {
        return $this->data['size'];
    }

    public function getMTime() {
        return $this->data['mtime'];
    }

    public function getStorageMTime() {
        return $this->data['storage_mtime'];
    }

    public function getEtag() {
        return $this->data['etag'];
    }

    public function getPermissions() {
        return $this->data['permissions'];
    }

    public function isEncrypted() {
        return isset($this->data['encrypted']) && $this->data['encrypted'];
    }

	public function jsonSerialize() {
		return $this->data;
	}

	public function getOwnCloudACL() {
		$ownCloudACL = array();
		$userSysACL = array();

		// $users => ["u:ourense:rwx", "u:labrador:r"]
		$users= explode(",", $this->data['eos.sys.acl']);

		if(count($userParts) >0 ) {
			foreach($userParts as $user) {
				$tokens = explode(":", $user);
				if(count($tokens) >= 3) {
					$userSysACL[] = $tokens;
				}
			}
		}

		foreach($userSysACL as $user) {
			$ownCloudACL[$user[1]] = array(
				"type" => $user[0],
				"ocperm" => $this->eosPermissionsToOwnCloudPermissions($user[2]),
				"eosperm" => $user[2],
			);
		}

		return $ownCloudACL;
	}

	public function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		if (isset($this->data[$offset])) {
			return $this->data[$offset];
		} else {
			return null;
		}
	}

	private function eosPermissionsToOwnCloudPermissions($eosPerm) {
		$total = 0;
		if(strpos($eosPerm, "r") !== false) {
			$total += 1;
		}
		if(strpos($eosPerm, "w") !== false){
			$total += 14;
		}
		return $total;
	}
}
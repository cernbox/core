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

	// TODO(labkode): report not in interface
	public function getData() {
		return $this->data;
	}
}
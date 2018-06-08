<?php

/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 29/07/16
 * Time: 15:56
 */

namespace OC\CernBox\Storage\Eos;

use OCP\Files\IHomeStorage;

class HomeStorage extends Storage  implements IHomeStorage
{
    // TODO(labkode) This method is called by storage Wrapper class thus making the ICache and IStorage useless.
    // Ask ocdevs to fix this and to not depend on this.
    public function getStorageCache() {
        return $this->catalog;
    }

	// TODO(labkode) This method is not defined on any interface. Ask ocdevs.
	public function getMetaData($path) {
		return parent::getCache()->get($path);
	}

	// TODO(labkode) This method is not defined on any interface. Ask ocdevs.
	// It is called on legacy Util
	public function getUser() {
		return $this->username;
	}
}
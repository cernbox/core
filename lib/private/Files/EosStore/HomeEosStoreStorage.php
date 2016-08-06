<?php

/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 29/07/16
 * Time: 15:56
 */

namespace OC\Files\EosStore;

use OCP\Files\IHomeStorage;

class HomeEosStoreStorage extends \OC\Files\EosStore\EosStoreStorage implements IHomeStorage
{
    // TODO(labkode) This method is called by storage Wrapper class thus making the ICache and IStorage useless.
    // Ask ocdevs to fix this and to not depend on this.
    public function getStorageCache() {
        return $this->namespace;
    }
}
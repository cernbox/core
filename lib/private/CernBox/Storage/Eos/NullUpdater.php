<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 02/08/16
 * Time: 12:26
 */

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Storage\IStorage;

class NullUpdater implements IUpdater
{
    /**
     * Get the propagator for etags and mtime for the view the updater works on
     *
     * @return IPropagator
     * @since 9.0.0
     */
    public function getPropagator()
    {
        return new EosNullPropagator();
    }

    /**
     * Propagate etag and mtime changes for the parent folders of $path up to the root of the filesystem
     *
     * @param string $path the path of the file to propagate the changes for
     * @param int|null $time the timestamp to set as mtime for the parent folders, if left out the current time is used
     * @since 9.0.0
     */
    public function propagate($path, $time = null)
    {
        return;
    }

    /**
     * Update the cache for $path and update the size, etag and mtime of the parent folders
     *
     * @param string $path
     * @param int $time
     * @since 9.0.0
     */
    public function update($path, $time = null)
    {
        return;
    }

    /**
     * Remove $path from the cache and update the size, etag and mtime of the parent folders
     *
     * @param string $path
     * @since 9.0.0
     */
    public function remove($path)
    {
        return;
    }

    /**
     * Rename a file or folder in the cache and update the size, etag and mtime of the parent folders
     *
     * @param IStorage $sourceStorage
     * @param string $source
     * @param string $target
     * @since 9.0.0
     */
    public function renameFromStorage(IStorage $sourceStorage, $source, $target)
    {
        return;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 02/08/16
 * Time: 12:26
 */

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IWatcher;

class NullWatcher implements IWatcher
{
    /**
     * @param int $policy either IWatcher::CHECK_NEVER, IWatcher::CHECK_ONCE, IWatcher::CHECK_ALWAYS
     * @since 9.0.0
     */
    public function setPolicy($policy)
    {
        // TODO: Implement setPolicy() method.
    }

    /**
     * @return int either IWatcher::CHECK_NEVER, IWatcher::CHECK_ONCE, IWatcher::CHECK_ALWAYS
     * @since 9.0.0
     */
    public function getPolicy()
    {
        // TODO: Implement getPolicy() method.
    }

    /**
     * check $path for updates and update if needed
     *
     * @param string $path
     * @param ICacheEntry|null $cachedEntry
     * @return boolean true if path was updated
     * @since 9.0.0
     */
    public function checkUpdate($path, $cachedEntry = null)
    {
        // TODO: Implement checkUpdate() method.
    }

    /**
     * Update the cache for changes to $path
     *
     * @param string $path
     * @param ICacheEntry $cachedData
     * @since 9.0.0
     */
    public function update($path, $cachedData)
    {
        // TODO: Implement update() method.
    }

    /**
     * Check if the cache for $path needs to be updated
     *
     * @param string $path
     * @param ICacheEntry $cachedData
     * @return bool
     * @since 9.0.0
     */
    public function needsUpdate($path, $cachedData)
    {
        return false;
    }

    /**
     * remove deleted files in $path from the cache
     *
     * @param string $path
     * @since 9.0.0
     */
    public function cleanFolder($path)
    {
        return;
    }


}
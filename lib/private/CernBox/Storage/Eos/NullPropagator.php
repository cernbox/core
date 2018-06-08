<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 02/08/16
 * Time: 12:43
 */

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\IPropagator;

class NullPropagator implements IPropagator
{
    /**
     * Mark the beginning of a propagation batch
     *
     * Note that not all cache setups support propagation in which case this will be a noop
     *
     * Batching for cache setups that do support it has to be explicit since the cache state is not fully consistent
     * before the batch is committed.
     *
     * @since 9.1.0
     */
    public function beginBatch()
    {
        return;
    }

    /**
     * Commit the active propagation batch
     *
     * @since 9.1.0
     */
    public function commitBatch()
    {
        return;
    }

    /**
     * @param string $internalPath
     * @param int $time
     * @since 9.0.0
     */
    public function propagateChange($internalPath, $time)
    {
        return;
    }

}
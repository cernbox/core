<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 02/08/16
 * Time: 12:25
 */

namespace OC\CernBox\Storage\Eos;


use Aws\S3\Exception\ExpiredTokenException;
use OC\OCS\Exception;
use OCP\Files\Cache\IScanner;

class NullScanner implements IScanner
{
    /**
     * scan a single file and store it in the cache
     *
     * @param string $file
     * @param int $reuseExisting
     * @param int $parentId
     * @param array | null $cacheData existing data in the cache for the file to be scanned
     * @param bool $lock set to false to disable getting an additional read lock during scanning
     * @return array an array of metadata of the scanned file
     * @throws \OC\ServerNotAvailableException
     * @throws \OCP\Lock\LockedException
     * @since 9.0.0
     */
    public function scanFile($file, $reuseExisting = 0, $parentId = -1, $cacheData = null, $lock = true)
    {
        // TODO: Implement scanFile() method.
    }

    /**
     * scan a folder and all its children
     *
     * @param string $path
     * @param bool $recursive
     * @param int $reuse
     * @param bool $lock set to false to disable getting an additional read lock during scanning
     * @return array an array of the meta data of the scanned file or folder
     * @since 9.0.0
     */
    public function scan($path, $recursive = self::SCAN_RECURSIVE, $reuse = -1, $lock = true)
    {
        // TODO: Implement scan() method.
    }

    /**
     * check if the file should be ignored when scanning
     * NOTE: files with a '.part' extension are ignored as well!
     *       prevents unfinished put requests to be scanned
     *
     * @param string $file
     * @return boolean
     * @since 9.0.0
     */
    public static function isPartialFile($file)
    {
        return false;
    }

    /**
     * walk over any folders that are not fully scanned yet and scan them
     *
     * @since 9.0.0
     */
    public function backgroundScan()
    {
       throw new Exception('Not supported');
    }

    // TODO(labkode) NOT DEFINED IN INTERFACE
	// {"reqId":"WDQILALj-KbowSnG89c9ggAAAA0","remoteAddr":"::1","app":"PHP","message":"Call to undefined method OC\\CernBox\\Storage\\Eos\\NullScanner::listen() at \/var\/www\/html\/core\/lib\/private\/Files\/Utils\/Scanner.php#100","level":3,"time":"2016-11-22T08:56:20+00:00","method":"GET","url":"\/core\/cron.php","user":"--"}
	public function listen() {

	}
}
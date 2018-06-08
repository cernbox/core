<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 29/07/16
 * Time: 14:49
 */

namespace OC\CernBox\Storage\Eos;


use OC\Files\Mount\MountPoint;
use OCP\Files\Config\IHomeMountProvider;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IConfig;

/**
 * Class EosHomeMountProvider
 * @package OC\Files\Mount
 */
class HomeMountProvider implements IHomeMountProvider
{
    /**
     * @var IConfig
     */
    private $config;

    /**
     * EosHomeMountProvider constructor.
     * @param IConfig $config
     */
    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }


    /**
     * @param IUser $user
     * @param IStorageFactory $loader
     * @return IMountPoint
     */
    public function getHomeMountForUser(IUser $user, IStorageFactory $loader) {

        $config = $this->getEosStoreConfig($user);
        if ($config === null) {
            return null;
        }

        return new MountPoint('\OC\CernBox\Storage\Eos\HomeStorage', '/' . $user->getUID(), $config['arguments'], $loader);
    }

    /**
     * @param IUser $user
     * @return array|mixed|null
     */
    private function getEosStoreConfig(IUser $user) {
        $config = $this->config->getSystemValue('eosstore');
        if (!is_array($config)) {
            return null;
        }

        // sanity checks
        if (empty($config['class'])) {
            \OCP\Util::writeLog('files', 'No class given for eosstore', \OCP\Util::ERROR);
        }
        if (!isset($config['arguments'])) {
            $config['arguments'] = [];
        }
        $config['arguments']['user'] = $user;
        // instantiate eos store implementation
        $config['arguments']['eosstore'] = new $config['class']($config['arguments']);
        return $config;
    }
}
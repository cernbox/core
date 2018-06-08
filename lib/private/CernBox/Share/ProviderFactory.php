<?php
namespace OC\CernBox\Share;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\DiscoveryManager;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;
use OCP\Share;
use OCP\Share\IProviderFactory;
use OC\Share20\Exception\ProviderException;
use OCP\IServerContainer;

/**
 * Class ProviderFactory
 *
 * @package OC\Share20
 */
class ProviderFactory implements IProviderFactory {

	/** @var IServerContainer */
	private $serverContainer;
	private $logger;

	private $provider;
	private $federatedProvider;

	/**
	 * IProviderFactory constructor.
	 * @param IServerContainer $serverContainer
	 */
	public function __construct(IServerContainer $serverContainer) {
		$this->serverContainer = $serverContainer;
		$this->provider = new MultiShareProvider($this->serverContainer->getRootFolder());
		$this->federatedProvider = $this->getOwnCloudFederatedShareProvider();
		$this->logger = \OC::$server->getLogger();
	}

	/**
	 * @inheritdoc
	 */
	public function getProvider($id) {
		$this->logger->debug("sharing getProvider:$id");
		if($id === "ocinternal") {
			return $this->provider;
		} else if ($id === "ocFederatedSharing") {
			return $this->federatedProvider;
		} else {
				throw new ProviderException('No provider with id:' . $id . ' found.');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getProviderForType($shareType) {
		$this->logger->debug("sharing getProviderForType:$shareType");
		if ($shareType === Share::SHARE_TYPE_USER  ||
			$shareType === Share::SHARE_TYPE_GROUP ||
			$shareType === Share::SHARE_TYPE_LINK) {
			return $this->provider;
		} else if ($shareType === Share::SHARE_TYPE_REMOTE) {
			return $this->federatedProvider;
		} else {
			throw new ProviderException('No share provider for share type ' . $shareType);
		}
	}

	/**
	 * Create the federated share provider
	 *
	 * @return FederatedShareProvider
	 */
	private function getOwnCloudFederatedShareProvider() {
		if ($this->federatedProvider === null) {
			/*
			 * Check if the app is enabled
			 */
			$appManager = $this->serverContainer->getAppManager();
			if (!$appManager->isEnabledForUser('federatedfilesharing')) {
				return null;
			}

			/*
			 * TODO: add factory to federated sharing app
			 */
			$l = $this->serverContainer->getL10N('federatedfilessharing');
			$addressHandler = new AddressHandler(
				$this->serverContainer->getURLGenerator(),
				$l
			);
			$discoveryManager = new DiscoveryManager(
				$this->serverContainer->getMemCacheFactory(),
				$this->serverContainer->getHTTPClientService()
			);
			$notifications = new Notifications(
				$addressHandler,
				$this->serverContainer->getHTTPClientService(),
				$discoveryManager,
				$this->serverContainer->getJobList(),
				$this->serverContainer->getConfig()
			);
			$tokenHandler = new TokenHandler(
				$this->serverContainer->getSecureRandom()
			);

			$this->federatedProvider = new FederatedShareProvider(
				$this->serverContainer->getDatabaseConnection(),
				$addressHandler,
				$notifications,
				$tokenHandler,
				$l,
				$this->serverContainer->getLogger(),
				$this->serverContainer->getLazyRootFolder(),
				$this->serverContainer->getConfig(),
				$this->serverContainer->getUserManager()
			);
		}
	}
}

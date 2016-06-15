<?php

namespace OC\Cernbox\ShareEngine;

use OCP\Share\IProviderFactory;
use OCP\IServerContainer;

final class CernboxProviderFactory implements IProviderFactory  
{
	/** @var IServerContainer */
	private $serverInstance;
	
	/**
	 * {@inheritDoc}
	 * @see \OCP\Share\IProviderFactory::__construct()
	 */
	public function __construct(IServerContainer $serverContainer) 
	{
		$this->serverInstance = $serverContainer;
	}

	/**
	 * {@inheritDoc}
	 * @see \OCP\Share\IProviderFactory::getProvider()
	 */
	public function getProvider($id) 
	{
		return new AbstractProvider($this->serverInstance->getRootFolder());
	}

	/**
	 * {@inheritDoc}
	 * @see \OCP\Share\IProviderFactory::getProviderForType()
	 */
	public function getProviderForType($shareType) 
	{
		if($shareType == \OCP\Share::SHARE_TYPE_REMOTE)
		{
			throw new \Exception('Remote shares are not allowed');
		}
		
		return new AbstractProvider($this->serverInstance->getRootFolder());
	}
}
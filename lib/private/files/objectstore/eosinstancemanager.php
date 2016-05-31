<?php

namespace OC\Files\ObjectStore;

class EosInstanceManager 
{
	const EOS_MAPPING_DB_TABLE = 'cernbox_eos_instances_mapping';
	const EOS_MAPPING_REDIS_KEY = 'eos_mappings';
	
	private static $currentInstanceId = '-2';
	private static $currentInstance = null;
	
	private static function loadCacheFromDB()
	{
		$instances = \OC_DB::prepare('SELECT * FROM ' . self::EOS_MAPPING_DB_TABLE)->execute()->fetchAll();
		$temp = [];
		foreach($instances as $instance)
		{
			Redis::writeToCacheMap(self::EOS_MAPPING_REDIS_KEY, $instance['id'], json_encode($instance));
			$temp[$instance['id']] = $instance;
		}
	
		return $temp;
	}
	
	public static function getAllMappings()
	{
		$temp = [];
		$all = Redis::readHashFromCacheMap(self::EOS_MAPPING_REDIS_KEY);
		if(!$all || $all === NULL)
		{
			$all = self::loadCacheFromDB();
		}
		else
		{
			foreach($all as $i)
			{
				$temp[] = json_decode($i, TRUE);
			}
				
			$all = $temp;
		}
	
		return $all;
	}
	
	public static function getMappingById($id)
	{
		if($id === self::$currentInstanceId && self::$currentInstance !== null)
		{
			return self::$currentInstance;	
		}
		
		$instance = json_decode(Redis::readFromCacheMap(self::EOS_MAPPING_REDIS_KEY, $id), TRUE);
		if(!$instance || $instance === NULL)
		{
			$temp = self::loadCacheFromDB();
			if(isset($temp[$id]))
			{
				$instance = $temp[$id];
			}
		}
	
		return $instance;
	}
	
	public static function setUserInstance($id)
	{
		self::$currentInstanceId = $id;
		self::$currentInstance = self::getMappingById($id);
	}
	
	public static function getUserInstance()
	{
		return self::$currentInstanceId;
	}
	
	public static function isInGlobalInstance()
	{
		return (self::$currentInstanceId !== '-2');
	}
}
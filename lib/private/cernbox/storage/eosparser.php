<?php

namespace OC\Cernbox\Storage;

use OC\Cernbox\Storage\EosParsers\DefaultEosParser;
use OC\Cernbox\Storage\EosParsers\ShareEosParser;

final class EosParser
{
	const DEFAUL_PARSER = new DefaultEosParser();
	const SHARE_PARSER = new ShareEosParser();
	
	/** @var AbstractEosParser */
	private static $parser = self::DEFAUL_PARSER;
	
	public static function executeWithParser(AbstractEosParser $parser, $callback)
	{
		if($parser === null)
		{
			self::$parser = self::DEFAUL_PARSER;
		}
		else
		{
			self::$parser = $parser;
		}
		
		$result = $callback();
		
		self::$parser = self::DEFAUL_PARSER;
		
		return $result;
	}
	
	public static function parseFileInfoMonitorMode($line)
	{
		self::$parser->parseFileInfo($line);
	}
	
	public static function parseMember($line)
	{
		self::$parser->parseMember($line);
	}
	
	public static function parseQuota($line)
	{
		self::$parser->parseQuota($line);
	}
	
	public static function parseRecycleLsMonitorMode($line)
	{
		self::$parser->parseRecycleFileInfo($line);
	}
}
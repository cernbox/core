<?php

namespace OC\Cernbox\Storage;

use OC\Cernbox\Storage\EosParsers\DefaultEosParser;
use OC\Cernbox\Storage\EosParsers\ShareEosParser;

final class EosParser
{
	public static $DEFAULT_PARSER = new DefaultEosParser();
	public static $SHARE_PARSER = new ShareEosParser();
	
	/** @var AbstractEosParser */
	private static $parser = self::$DEFAULT_PARSER;
	
	public static function executeWithParser(AbstractEosParser $parser, $callback)
	{
		if($parser === null)
		{
			self::$parser = self::$DEFAULT_PARSER;
		}
		else
		{
			self::$parser = $parser;
		}
		
		$result = $callback();
		
		self::$parser = self::$DEFAULT_PARSER;
		
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
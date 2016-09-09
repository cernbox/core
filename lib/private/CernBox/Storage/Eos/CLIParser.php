<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/9/16
 * Time: 1:40 PM
 */

namespace OC\CernBox\Storage\Eos;


class CLIParser {

	public static function parseEosFileInfoMResponse($line) {
		$rawMap = explode(' ', $line);

		$eosFileName = self::extractFileName($rawMap);
		$map = self::buildAttributeMap($rawMap);
		$map['file'] = $eosFileName;
		$map['mtime'] = self::getCorrectMTime($map);

		// prefix all keys in the map with eos to not interfere with
		// owncloud keys
		$prefixedMap = array();
		foreach($map as $key => $value) {
			$prefixedMap["eos." . $key] = $value;
		}

		return $prefixedMap;
	}

	/**
	 * Builds and extract the name from the EOS answer. This function will remove
	 * the keylength and file properties from the array
	 * @param array $rawMap array of EOS data splitted by white space
	 * @return string the name (full eos path) of the file
	 */
	private static function extractFileName(&$rawMap)
	{
		$keyLen = $rawMap[0];
		array_shift($rawMap);
		$keyLen = explode('=', $keyLen)[1];

		$name = $rawMap[0];
		array_shift($rawMap);
		$name = explode('=', $name)[1];

		// If the name contains spaces, they were splitted when creating the attribute map
		// we have to rebuild it
		$len = strlen($name);
		while($len < $keyLen)
		{
			$name .= ' ' . $rawMap[0];
			$len += strlen($rawMap[0]) + 1 ; //string len + 1 for white space
			array_shift($rawMap);
		}

		return $name;
	}

	/**
	 * Builds a map of attribute => value for the given file metadata.
	 * @param array $rawMap Raw EOS data splitted by white space
	 * @return array $map A map
	 */
	private static function buildAttributeMap($rawMap)
	{
		$map = [];
		$arrayLen = count($rawMap);
		for($i = 0; $i < $arrayLen; $i++)
		{
			$parts = explode('=', $rawMap[$i]);
			if($parts[0] === 'xattrn')
			{
				$key = $parts[1];
				$i++;
				$value = explode('=', $rawMap[$i])[1];
			}
			else
			{
				$key = $parts[0];
				$value = $parts[1];
			}

			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * Attempts to retrieve an attribute value from the attribute map. If this is not set,
	 * it will return a default value given as 3rd argument
	 * @param array $haystack The attribute map [property name => value]
	 * @param string $propertyName The name of the property we want to retrieve
	 * @param mixed $defaultValue The default value it should return in case the property is not found in the map
	 * @return mixed The property value or $defaultValue if the property could not be found in the map
	 */
	private static function parseField(array $haystack, $propertyName, $defaultValue = NULL)
	{
		if(isset($haystack[$propertyName]))
		{
			return $haystack[$propertyName];
		}

		return $defaultValue;
	}

	private static function getCorrectMTime($map)
	{
		$mtimeTest = self::parseField($map, 'mtime', 0);
		if($mtimeTest === '0.0')
		{
			$mtimeTest = self::parseField($map, 'ctime', 0);
		}

		return $mtimeTest;
	}
}
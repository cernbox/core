<?php

namespace OC\Cernbox\Storage;

abstract class AbstractEosParser
{
	/**
	 * Parses the raw data from EOS into a map useable by owncloud core
	 * @param string $data The output line given by EOS
	 * @return array map of attributes => values
	 */
	public function parseFileInfo($data);
	
	/**
	 * Parses the raw data from EOS recycle commands into a map useable by owncloud core
	 * @param string $data The output line given by EOS
	 * @return array map of attributes => values
	 */
	public function parseRecycleFileInfo($data);
	
	/**
	 * Parses the EOS answer when asking about the membership of an user to an e-group
	 * @param stirng $data The output line answered by EOS
	 * @return true|false whether the user is member or not of the e-group
	 */
	public function parseMember($data);
	
	/**
	 * Parses the raw data from EOS Quota into a map useable by owncloud core
	 * @param string $data The output line given by EOS
	 * @return array map of attributes => values
	 */
	public function parseQuota($data);
	
	/**
	 * Builds and extract the name from the EOS answer. This function will remove
	 * the keylenght and file properties from the array
	 * @param array $rawMap array of EOS data splitted by white space
	 * @return string the name (full eos path) of the file
	 */
	protected function extractFileName(&$rawMap)
	{
		$keyLen = $rawMap[0];
		array_shift($rawMap);
		$keyLen = explode('=', $keyLen)[1];
		
		$name = $rawMap[1];
		array_shift($rawMap);
		$name = explode('=', $name);
		
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
	protected function buildAttributeMap($rawMap)
	{
		$map = [];
		$arrayLen = count($rawMap);
		for($i = 0; $i < $arrayLen; $i++)
		{
			$parts = explode('=', $rawMap[$i]);
			if($parts === 'xattrn')
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
	protected function parseField(array $haystack, $propertyName, $defaultValue = NULL)
	{
		if(isset($haystack[$propertyName]))
		{
			return $haystack[$propertyName];
		}
		
		return $defaultValue;
	}
}
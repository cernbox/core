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

	// Parse the output of exec a find --count
	// $ find --count /eos/backup/proc/recycle/2763/95491
	// nfiles=1 ndirectories=2
	public static function parseFindCountReponse($line) {
		$fields = explode(" ", $line);
		$firstPair = explode("=", $fields[0]);
		$secondPair= explode("=", $fields[1]);
		$nfiles = (int)$firstPair[1];
		$ndirs = (int)$secondPair[1];
		return ["nfiles" => $nfiles, "ndirectories" => $ndirs];
	}

	public static function parseRecycleLSMResponse($line) {
		$fields = explode(" ", $line);
		$keylength = explode("=",$fields[8]);
		$keylength = $keylength[1];
		$realFile = explode("=",$fields[9]);
		$realFile = implode("=",array_slice($realFile,1));
		$total = strlen($realFile);

		if($total != $keylength)
		{
		  	$index = 10; $stop = false;
		  	while(!$stop)
		  	{
		    	$realFile .= " " . $fields[$index];
		    	$total += strlen($fields[$index]) + 1;
		    	unset($fields[$index]);
		    	$index++;
		    	if($total == $keylength)
		    	{
		    		$stop = true;
		    	}
		  	}
		  	$fields[9] = "restore-path=" . $realFile;
		  	$fields = array_values($fields);
		}

		$info = [];
		foreach ($fields as $value)
		{
			$splitted           = explode("=", $value);
			$info[$splitted[0]] = implode("=",array_slice($splitted,1));
		}
		// prefix all keys in the map with eos to not interfere with
		// owncloud keys
		$prefixedMap = array();
		foreach($info as $key => $value) {
			if($key) {
				$prefixedMap["eos." . $key] = $value;
			}
		}

		// owncloud expects 12 digits mtimes
		$prefixedMap['eos.deletion-time'] = $prefixedMap['eos.deletion-time'] * 1000;
		/**
		 * prefixedMap is this:
		 * /*
		Array
		(
		    [eos.recycle] => ls
		    [eos.recycle-bin] => /eos/devbox/proc/recycle/
		    [eos.uid] => labrador
		    [eos.gid] => it
		    [eos.size] => 0
		    [eos.deletion-time] => 1414600390
		    [eos.type] => file
		    [eos.keylength.restore-path] => 48
		    [eos.restore-path] => /eos/user/l/labrador/more / contents with spaces
		    [eos.restore-key] => 0000000000017bd3
		)
		*/
		return $prefixedMap;
	}

	/**
	 * Parses the output of the eos member command
	 *
	 * Ex: $ eos -r 95491 2763 member cernbox-admins
	 * egroup=cernbox-admins user=gonzalhu member=true lifetime=1800
	 *
	 * Ex: $ eos -r 95491 2763 member
	 * egroup=cernbox user=gonzalhu member=false lifetime=77
	 * egroup=cernbox-admins user=gonzalhu member=true lifetime=40
	 * egroup=eos-admins user=gonzalhu member=true lifetime=1797
	 *
	 * @param $lines
	 * @return array
	 */
	public static function parseMemberResponse($lines) {
		$memberMap = array();
		foreach($lines as $line) {
			$pairs = explode(" ", $line);
			foreach($pairs as $pair)
			{
				$entry = array();
				list($key, $value) = explode("=", $pair);
				$entry[$key] = $value; $memberMap[] = $entry;
			}
		}
		return $memberMap;
	}
	
	public static function parseQuotaResponse($lines) {
		if(!is_array($lines) || count($lines) <= 0) {
			throw new \Exception("cannot get quota for user");
                }
		$line = $lines[0];
                $line = explode(' ', $line);
                $data = [];
                foreach($line as $token)
                {
                        $parts = explode('=', $token);
                        $data[$parts[0]] = $parts[1];
                }

                if(!isset($data['usedlogicalbytes']) || !isset($data['maxlogicalbytes'])) {
			throw new \Exception("cannot get quota for user: no userlogicalbytes or maxlogicalbytes keys");
		}

                $used = intval($data['usedlogicalbytes']);
                $total = intval($data['maxlogicalbytes']);
		return [$used, $total];
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
		$parts = explode(".", $mtimeTest);
		return $parts[0];
	}
}

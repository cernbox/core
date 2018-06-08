<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 10/31/16
 * Time: 9:02 AM
 */

namespace OC\CernBox\Storage\Eos;


class ACLEntry {
	const USER_TYPE = "u";
	const GROUP_TYPE = "egroup";
	const UNIX_TYPE = "g";

	private $type;
	private $grantee;
	private $permissions;

	private $logger;

	public function __construct($singleEosSysAcl) {
		$this->logger = \OC::$server->getLogger();

		$singleEosSysAcl = (string)$singleEosSysAcl;
		$tokens = explode(":", $singleEosSysAcl);
		if(count($tokens) !== 3) {
			$error = "unit(ACLEntry) method(__construct) singleEosSysAcl($singleEosSysAcl) number of tokens != 3";
			$this->logger->error($error);
			throw  new \Exception($error);
		}

		$type = $tokens[0];
		$grantee = $tokens[1];
		$permissions = $tokens[2];


		if($type !== ACLEntry::USER_TYPE && $type !== ACLEntry::GROUP_TYPE && $type !== ACLEntry::UNIX_TYPE) {
			$error = "unit(ACLEntry) method(__construct) type($type) type not supported";
			$this->logger->error($error);
			throw  new \Exception($error);
		}

		if(!$grantee) {
			$error = "unit(ACLEntry) method(__construct) grantee cannot be empty";
			$this->logger->error($error);
			throw  new \Exception($error);
		}

		if(!$permissions) {
			$error = "unit(ACLEntry) method(__construct) permissions cannot be empty";
			$this->logger->error($error);
			throw  new \Exception($error);
		}

		$this->type = $type;
		$this->grantee = $grantee;
		$this->permissions = $permissions;
	}

	public function getType() {
		return $this->type;
	}
	public function getGrantee() {
		return $this->grantee;
	}
	public function getPermissions() {
		return $this->permissions;
	}
	public function hasReadPermission() {
		if(strpos($this->permissions, 'r') !== false) {
			return true;
		}
	}
	public function hasWritePermission() {
		if(strpos($this->permissions, 'w') !== false) {
			return true;
		}
	}
	public function serializeToEos() {
		return implode(":", array($this->getType(), $this->getGrantee(), $this->getPermissions()));
	}
}

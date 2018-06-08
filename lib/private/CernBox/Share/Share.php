<?php
namespace OC\CernBox\Share;


use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Share\IShare;

class Share implements IShare {

	/** @var string */
	private $id;
	/** @var string */
	private $providerId;
	/** @var Node */
	private $node;
	/** @var int */
	private $fileId;
	/** @var string */
	private $nodeType;
	/** @var int */
	private $shareType;
	/** @var string */
	private $sharedWith;
	/** @var string */
	private $sharedBy;
	/** @var string */
	private $shareOwner;
	/** @var int */
	private $permissions;
	/** @var \DateTime */
	private $expireDate;
	/** @var string */
	private $password;
	/** @var string */
	private $token;
	/** @var string */
	private $target;
	/** @var \DateTime */
	private $shareTime;
	/** @var bool */
	private $mailSend;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * Share constructor.
	 *
	 * @param IRootFolder $rootFolder
	 */
	public function __construct(IRootFolder $rootFolder) {
		$this->rootFolder = $rootFolder;
	}
	
	/**
	 * Set a user-defined name for this share
	 *
	 * @param string $name
	 * @since 10.0.0
	 */
	public function setName($name) {}

	/**
	 * Get user-defined name for this share
	 *
	 * @return string $name
	 * @since 10.0.0
	 */
	public function getName() {}

	/**
	 * Set share accepted state
	 *
	 * @param int $state
	 * @since 10.0.9
	 */
	public function setState($state) {}

	/**
	 * Get share accepted state
	 *
	 * @return int state
	 * @since 10.0.9
	 */
	public function getState() {}

	/**
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 *
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 *
	 */
	public function getFullId() {
		if($this->providerId === null || $this->id === null) {
			throw new \UnexpectedValueException();
		}
		return $this->providerId . ':' . $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setProviderId($id) {
		$this->providerId = $id;
	}

	/**
	 * @param Node $node
	 */
	public function setNode(Node $node) {
		$this->fileId = null;
		$this->nodeType = null;
		$this->node = $node;
		return $this;
	}

	/**
	 * @return Node
	 * @throws NotFoundException
	 */
	public function getNode() {
		if($this->node === null) {
			if($this->shareOwner === null || $this->fileId === null) {
				throw new NotFoundException('shareOwner and fileId are null');
			}

			$fileId = $this->fileId;
			$userFolder = $this->rootFolder->getUserFolder($this->shareOwner);
			$nodes = $userFolder->getById($fileId);
			if(empty($nodes)) {
				throw new NotFoundException("cannot find file with id:$fileId on storage");
			}
			$this->node = $nodes[0];
		}
		return $this->node;
	}

	/**
	 * @param int $fileId
	 */
	public function setNodeId($fileId) {
		$this->node = null;
		$this->fileId = $fileId;
		return $this;
	}

	/**
	 *
	 */
	public function getNodeId() {
		if($this->fileId === null) {
			$this->fileId = $this->getNode()->getId();
		}
		return $this->fileId;
	}

	/**
	 * @param string $type
	 */
	public function setNodeType($type) {
		if($type !== 'file' && $type !== 'folder') {
			throw new \InvalidArgumentException();
		}
		$this->nodeType = $type;
		return $this;
	}

	/**
	 *
	 */
	public function getNodeType() {
		if($this->nodeType === null) {
			$node = $this->getNode();
			$this->nodeType = $node instanceof File? 'file' : 'folder';
		}

		return $this->nodeType;
	}

	/**
	 * @param int $shareType
	 */
	public function setShareType($shareType) {
		$this->shareType = $shareType;
		return $this;
	}

	/**
	 *
	 */
	public function getShareType() {
		return $this->shareType;
	}

	/**
	 * @param string $sharedWith
	 */
	public function setSharedWith($sharedWith) {
		if(!is_string($sharedWith)) {
			throw new \InvalidArgumentException();
		}
		$this->sharedWith = $sharedWith;
		return $this;
	}

	/**
	 *
	 */
	public function getSharedWith() {
		return $this->sharedWith;
	}

	/**
	 * @param int $permissions
	 */
	public function setPermissions($permissions) {
		// TODO(labkode): check to what perm map on EOS
		$this->permissions = $permissions;
		return $this;
	}

	/**
	 *
	 */
	public function getPermissions() {
		return $this->permissions;
	}

	/**
	 * @param \DateTime $expireDate
	 */
	public function setExpirationDate($expireDate) {
		// TODO(labkode): check for sane values
		$this->expireDate = $expireDate;
		return $this;
	}

	/**
	 *
	 */
	public function getExpirationDate() {
		return $this->expireDate;
	}

	/**
	 * @param string $sharedBy
	 */
	public function setSharedBy($sharedBy) {
		if(!is_string($sharedBy)) {
			throw new \InvalidArgumentException();
		}
		// TODO(labkode): check that sharedBy is valid user
		$this->sharedBy = $sharedBy;
		return $this;
	}

	/**
	 *
	 */
	public function getSharedBy() {
		return $this->sharedBy;
	}

	/**
	 * @param string $shareOwner
	 */
	public function setShareOwner($shareOwner) {
		if(!is_string($shareOwner)) {
			throw new \InvalidArgumentException();
		}
		// TODO(labkode): check owner is valid user ?
		$this->shareOwner = $shareOwner;
		return $this;
	}

	/**
	 *
	 */
	public function getShareOwner() {
		return $this->shareOwner;
	}

	/**
	 * @param string $password
	 */
	public function setPassword($password) {
		$this->password = $password;
		return $this;
	}

	/**
	 *
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @param string $token
	 */
	public function setToken($token) {
		$this->token = $token;
		return $this;
	}

	/**
	 *
	 */
	public function getToken() {
		return $this->token;
	}

	/**
	 * @param string $target
	 */
	public function setTarget($target) {
		$this->target = $target;
		return $this;
	}

	/**
	 *
	 */
	public function getTarget() {
		return $this->target;
	}

	/**
	 * @param \DateTime $shareTime
	 */
	public function setShareTime(\DateTime $shareTime) {
		$this->shareTime = $shareTime;
		return $this;
	}

	/**
	 *
	 */
	public function getShareTime() {
		return $this->shareTime;
	}

	/**
	 * @param bool $mailSend
	 */
	public function setMailSend($mailSend) {
		$this->mailSend = $mailSend;
		return $this;
	}

	/**
	 *
	 */
	public function getMailSend() {
		return $this->mailSend;
	}

}

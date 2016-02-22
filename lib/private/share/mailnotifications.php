<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Share;

use DateTime;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Mail\IMailer;
use OCP\ILogger;
use OCP\Defaults;

/**
 * Class MailNotifications
 *
 * @package OC\Share
 */
class MailNotifications {

	/** @var string sender userId */
	private $userId;
	/** @var string sender email address */
	private $replyTo;
	/** @var string */
	private $senderDisplayName;
	/** @var IL10N */
	private $l;
	/** @var IConfig */
	private $config;
	/** @var IMailer */
	private $mailer;
	/** @var Defaults */
	private $defaults;
	/** @var ILogger */
	private $logger;

	/**
	 * @param string $uid user id
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param IMailer $mailer
	 * @param ILogger $logger
	 * @param Defaults $defaults
	 */
	public function __construct($uid,
								IConfig $config,
								IL10N $l10n,
								IMailer $mailer,
								ILogger $logger,
								Defaults $defaults) {
		$this->l = $l10n;
		$this->userId = $uid;
		$this->config = $config;
		$this->mailer = $mailer;
		$this->logger = $logger;
		$this->defaults = $defaults;

		$this->replyTo = $this->config->getUserValue($this->userId, 'settings', 'email', null);
		$this->senderDisplayName = \OCP\User::getDisplayName($this->userId);
	}

	/**
	 * inform users if a file was shared with them
	 *
	 * @param array $recipientList list of recipients
	 * @param string $itemSource shared item source
	 * @param string $itemType shared item type
	 * @return array list of user to whom the mail send operation failed
	 */
	public function sendInternalShareMail($recipientList, $itemSource, $itemType) {
		$noMail = [];

		foreach ($recipientList as $recipient) {
			$recipientDisplayName = \OCP\User::getDisplayName($recipient);
			$to = $this->config->getUserValue($recipient, 'settings', 'email', '');

			if ($to === '') {
				$noMail[] = $recipientDisplayName;
				continue;
			}

			$items = \OCP\Share::getItemSharedWithUser($itemType, $itemSource, $recipient);
			$filename = trim($items[0]['file_target'], '/');
			$subject = (string) $this->l->t('%s shared »%s« with you', array($this->senderDisplayName, $filename));
			$expiration = null;
			if (isset($items[0]['expiration'])) {
				try {
					$date = new DateTime($items[0]['expiration']);
					$expiration = $date->getTimestamp();
				} catch (\Exception $e) {
					$this->logger->error("Couldn't read date: ".$e->getMessage(), ['app' => 'sharing']);
				}
			}

			// Link to folder, or root folder if a file

			if ($itemType === 'folder') {
				$args = array(
					'dir' => $filename,
				);
			} else if (strpos($filename, '/')) {
				$args = array(
					'dir' => '/' . dirname($filename),
					'scrollto' => basename($filename),
				);
			} else {
				$args = array(
					'dir' => '/',
					'scrollto' => $filename,
				);
			}

			$link = \OCP\Util::linkToAbsolute('files', 'index.php', $args);

			list($htmlBody, $textBody) = $this->createMailBody($filename, $link, $expiration);

			// send it out now
			try {
				$message = $this->mailer->createMessage();
				$message->setSubject($subject);
				$message->setTo([$to => $recipientDisplayName]);
				$message->setHtmlBody($htmlBody);
				$message->setPlainBody($textBody);
				$message->setFrom([
					\OCP\Util::getDefaultEmailAddress('sharing-noreply') =>
						(string)$this->l->t('%s via %s', [
							$this->senderDisplayName,
							$this->defaults->getName()
						]),
					]);
				if(!is_null($this->replyTo)) {
					$message->setReplyTo([$this->replyTo]);
				}

				$this->mailer->send($message);
			} catch (\Exception $e) {
				$this->logger->error("Can't send mail to inform the user about an internal share: ".$e->getMessage(), ['app' => 'sharing']);
				$noMail[] = $recipientDisplayName;
			}
		}

		return $noMail;

	}

	/**
	 * inform recipient about public link share
	 *
	 * @param string $recipient recipient email address
	 * @param string $filename the shared file
	 * @param string $link the public link
	 * @param int $expiration expiration date (timestamp)
	 * @return array $result of failed recipients
	 */
	public function sendLinkShareMail($recipient, $filename, $link, $expiration) {
		$subject = (string)$this->l->t('%s shared »%s« with you', [$this->senderDisplayName, $filename]);
		list($htmlBody, $textBody) = $this->createMailBody($filename, $link, $expiration);

		$recipient = str_replace([', ', '; ', ',', ';', ' '], ',', $recipient);
		$recipients = explode(',', $recipient);
		try {
			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setTo($recipients);
			$message->setHtmlBody($htmlBody);
			$message->setPlainBody($textBody);
			$message->setFrom([
				\OCP\Util::getDefaultEmailAddress('sharing-noreply') =>
					(string)$this->l->t('%s via %s', [
						$this->senderDisplayName,
						$this->defaults->getName()
					]),
			]);
			if(!is_null($this->replyTo)) {
				$message->setReplyTo([$this->replyTo]);
			}

			return $this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error("Can't send mail with public link to $recipient: ".$e->getMessage(), ['app' => 'sharing']);
			return [$recipient];
		}
	}

	/**
	 * create mail body for plain text and html mail
	 *
	 * @param string $filename the shared file
	 * @param string $link link to the shared file
	 * @param int $expiration expiration date (timestamp)
	 * @return array an array of the html mail body and the plain text mail body
	 */
	private function createMailBody($filename, $link, $expiration) {

		$formattedDate = $expiration ? $this->l->l('date', $expiration) : null;

		$html = new \OC_Template("core", "mail", "");
		$html->assign ('link', $link);
		$html->assign ('user_displayname', $this->senderDisplayName);
		$html->assign ('filename', $filename);
		$html->assign('expiration',  $formattedDate);
		$htmlMail = $html->fetchPage();

		$plainText = new \OC_Template("core", "altmail", "");
		$plainText->assign ('link', $link);
		$plainText->assign ('user_displayname', $this->senderDisplayName);
		$plainText->assign ('filename', $filename);
		$plainText->assign('expiration', $formattedDate);
		$plainTextMail = $plainText->fetchPage();

		return [$htmlMail, $plainTextMail];
	}
	
	/**
	 * inform recipient about eos/normal share
	 *
	 * @param string $recipient recipient email address
	 * @param string $filename the shared file
	 * @param string $link the public link
	 * @param int $expiration expiration date (timestamp)
	 * @return array $result of failed recipients
	 */
	public function sendLinkEosUser($recipient, $filename, $path, $recipient_username) {
		$link = \OCP\Config::getSystemValue("hostname_in_mail","https://box.cern.ch");
		$subject = (string)$this->l->t('%s shared »%s« with you', array(\OCP\User::getUser(), $filename));
		list($htmlMail, $alttextMail) = $this->createMailBodyEosUser($filename, $link, $recipient_username, $path);
		$rs = explode(' ', $recipient);
		$failed = array();
		foreach ($rs as $r) {
			try {
				\OCP\Util::sendMail($r, $r, $subject, $htmlMail,  \OCP\User::getUser() . "@cern.ch" , \OCP\User::getUser() . " via CERNBox", 1, $alttextMail);
			} catch (\Exception $e) {
				\OCP\Util::writeLog('sharing', "Can't send mail to $r: " . $e->getMessage(), \OCP\Util::ERROR);
				$failed[] = $r;
			}
		}
		return $failed;
	}
	
	public function sendLinkEosEGroup($recipient, $filename, $path, $recipient_username) {
		$link = \OCP\Config::getSystemValue("hostname_in_mail","https://box.cern.ch");
		$subject = (string)$this->l->t('%s shared »%s« with you through an e-group', array(\OCP\User::getUser(), $filename));
		list($htmlMail, $alttextMail) = $this->createMailBodyEosEGroup($filename, $link, $recipient_username, $path);
		$rs = explode(' ', $recipient);
		$failed = array();
		foreach ($rs as $r) {
			try {
				\OCP\Util::sendMail($r, $r, $subject, $htmlMail,  \OCP\User::getUser() . "@cern.ch" , \OCP\User::getUser() . " via CERNBox", 1, $alttextMail);
			} catch (\Exception $e) {
				\OCP\Util::writeLog('sharing', "Can't send mail to $r: " . $e->getMessage(), \OCP\Util::ERROR);
				$failed[] = $r;
			}
		}
		return $failed;
	}
	
	/**
	 * create mail body for plain text and html mail
	 *
	 * @param string $filename the shared file
	 * @param string $link link to the shared file
	 * @param int $expiration expiration date (timestamp)
	 * @return array an array of the html mail body and the plain text mail body
	 */
	private function createMailBodyEosUser($filename, $link, $recipient_username, $path) {
		$html = new \OC_Template("core", "maileosuser", "");
		$html->assign ('user_displayname',\OC::$server->getUserSession()->getUser()->getDisplayName());
		$html->assign ('user_id', \OC::$server->getUserSession()->getUser()->getUID());
		$html->assign ('filename', $filename);
		$html->assign ('link', $link);
		$html->assign ('recipient', $recipient_username);
		$html->assign ('path', $path);
		$htmlMail = $html->fetchPage();
	
		$alttext = new \OC_Template("core", "altmaileosuser", "");
		$alttext->assign ('user_displayname', \OCP\User::getUser());
		$alttext->assign ('filename', $filename);
		$alttext->assign ('link', $link);
		$alttext->assign ('recipient', $recipient_username);
		$alttext->assign('path', $path);
		$alttextMail = $alttext->fetchPage();
	
	
		return array($htmlMail, $alttextMail);
	}
	
	private function createMailBodyEosEGroup($filename, $link, $recipient_username, $path) {
		$html = new \OC_Template("core", "maileosegroup", "");
		$html->assign ('user_displayname',\OC::$server->getUserSession()->getUser()->getDisplayName());
		$html->assign ('user_id', \OC::$server->getUserSession()->getUser()->getUID());
		$html->assign ('filename', $filename);
		$html->assign ('link', $link);
		$html->assign ('recipient', $recipient_username);
		$html->assign ('path', $path);
		$htmlMail = $html->fetchPage();
	
		$alttext = new \OC_Template("core", "altmaileosegroup", "");
		$alttext->assign ('user_displayname', \OCP\User::getUser());
		$alttext->assign ('filename', $filename);
		$alttext->assign ('link', $link);
		$alttext->assign ('recipient', $recipient_username);
		$alttext->assign('path', $path);
		$alttextMail = $alttext->fetchPage();
	
	
		return array($htmlMail, $alttextMail);
	}
}

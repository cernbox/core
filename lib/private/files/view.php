<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author cmeh <cmeh@users.noreply.github.com>
 * @author Florin Peter <github@florin-peter.de>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Klaas Freitag <freitag@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Luke Policinski <lpolicinski@gmail.com>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Sam Tuke <mail@samtuke.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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


namespace OC\Files;

use OC\Files\Storage\Storage;
use OC\User\User;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\InvalidPathException;

/**
 * Class to provide access to ownCloud filesystem via a "view", and methods for
 * working with files within that view (e.g. read, write, delete, etc.). Each
 * view is restricted to a set of directories via a virtual root. The default view
 * uses the currently logged in user's data directory as root (parts of
 * OC_Filesystem are merely a wrapper for OC\Files\View).
 *
 * Apps that need to access files outside of the user data folders (to modify files
 * belonging to a user other than the one currently logged in, for example) should
 * use this class directly rather than using OC_Filesystem, or making use of PHP's
 * built-in file manipulation functions. This will ensure all hooks and proxies
 * are triggered correctly.
 *
 * Filesystem functions are not called directly; they are passed to the correct
 * \OC\Files\Storage\Storage object
 */
class View extends OwncloudView 
{
	private function assertPathLength($path)
	{
		$maxLen = min(PHP_MAXPATHLEN, 4000);
		// Check for the string length - performed using isset() instead of strlen()
		// because isset() is about 5x-40x faster.
		if (isset($path[$maxLen]))
		{
			$pathLen = strlen($path);
			throw new \OCP\Files\InvalidPathException("Path length($pathLen) exceeds max path length($maxLen): $path");
		}
	}
	
	public function getDirectoryContent($directory, $mimetype_filter = '') 
	{
		$this->assertPathLength($directory);
		if (!Filesystem::isValidPath($directory)) 
		{
			return [];
		}
		
		$path = $this->getAbsolutePath($directory);
		$path = Filesystem::normalizePath($path);
		$mount = $this->getMount($directory);
		$storage = $mount->getStorage();
		$internalPath = $mount->getInternalPath($path);
		
		if ($storage) 
		{
			$cache = $storage->getCache($internalPath);
			$data = $this->getCacheEntry($storage, $internalPath, $directory);
	
			if (!$data instanceof ICacheEntry || !isset($data['fileid']) || !($data->getPermissions() && Constants::PERMISSION_READ)) 
			{
				return [];
			}
	
			$folderId = $data['fileid'];
			$contents = $cache->getFolderContentsById($folderId); //TODO: mimetype_filter
	
			$sharingDisabled = \OCP\Util::isSharingDisabledForUser();
			/**
			 * @var \OC\Files\FileInfo[] $files
			 */
			$files = array_map(function (ICacheEntry $content) use ($path, $storage, $mount, $sharingDisabled) 
			{
				if ($sharingDisabled) 
				{
					$content['permissions'] = $content['permissions'] & ~\OCP\Constants::PERMISSION_SHARE;
				}
				$owner = $this->getUserObjectForOwner($storage->getOwner($content['path']));
				return new FileInfo($path . '/' . $content['name'], $storage, $content['path'], $content, $mount, $owner);
			}, 
			$contents);
	
			if ($mimetype_filter) 
			{
				$files = array_filter($files, function (FileInfo $file) use ($mimetype_filter) 
				{
					if (strpos($mimetype_filter, '/')) 
					{
						return $file->getMimetype() === $mimetype_filter;
					} 
					else 
					{
						return $file->getMimePart() === $mimetype_filter;
					}
				});
			}

			return $files;
		} 
		else 
		{
			return [];
		}
	}
}

<?php
/*
 * Copyright (c) 2016 Chris Wells (https://chriswells.io)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace CWA\IO;

use \CWA\IO\DirectoryListing;
use \CWA\IO\FileInfo;
use \CWA\IO\FileNotFoundException;
use \CWA\MVC\Controllers\InvalidArgumentException;
use \CWA\Net\HTTP\HttpResponse;

require_once \CWA\LIB_PATH . 'cwa/io/DirectoryListing.php';
require_once \CWA\LIB_PATH . 'cwa/io/FileInfo.php';
require_once \CWA\LIB_PATH . 'cwa/io/FileNotFoundException.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/controllers/InvalidArgumentException.php';
require_once \CWA\LIB_PATH . 'cwa/net/http/HttpResponse.php';

// Each controller needing storage should create a subdirectory in the following location. -- cwells
if (!defined('CWA\IO\STORAGE_PATH')) {
	define('CWA\IO\STORAGE_PATH', '../storage');
}

class FileManager
{
	/* Protected variables: */
	protected $realRootDirectory;


	/* Constructor: */
	public function __construct($rootDirectory = null) {
		global $app;
		if (is_null($rootDirectory)) {
			$rootDirectory = \CWA\IO\STORAGE_PATH . DIRECTORY_SEPARATOR . $app->getControllerName();
		}
		$this->realRootDirectory = realpath($rootDirectory);
	}


	/* Public methods: */

	public function delete($path) {
		if (!$this->exists($path)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$path = realpath($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);

		$result = false;
		if (is_file($path)) {
			$result = unlink($path);
		} else {
			$result = rmdir($path);
		}
		return $result;
	}

	public function download($path) {
		if (is_null($path) || empty($path)) {
			throw new InvalidArgumentException('You must specify the item to download.', 400);
		} else if (!$this->exists($path) || !is_file($this->realRootDirectory . DIRECTORY_SEPARATOR . $path)) {
			throw new FileNotFoundException('The specified file does not exist.', 404);
		}

		$fileObject = new \SplFileObject($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);
		HttpResponse::setContentDisposition($fileObject->getBasename());
		HttpResponse::setContentType('application/octet-stream');
		HttpResponse::setHeader('Content-Length', $fileObject->getSize());
		$fileObject->fpassthru();
		exit(0);
	}

	public function exists($path = '') {
		$path = realpath($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);
		return ($path !== false && strpos($path, $this->realRootDirectory) === 0);
	}

	public function getDirectoryListing($path = '') {
		if (!$this->exists($path)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$path = realpath($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);
		$directory = new DirectoryListing($path);
		if (is_null($directory)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$itemID = preg_replace('/^' . str_replace('/', '\/', $this->realRootDirectory) . '\/?/', '', $path);
		$directory->Path = $itemID;
		return $directory;
	}

	public function getFileInfo($path) {
		if (!$this->exists($path)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$path = realpath($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);
		$fileInfo = new FileInfo($path);
		if (is_null($fileInfo)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$itemID = preg_replace('/^' . str_replace('/', '\/', $this->realRootDirectory) . '\/?/', '', $path);
		$fileInfo->Path = $itemID;
		return $fileInfo;
	}

	public function getObject($path) {
		$item = null;
		if (is_file($this->realRootDirectory . DIRECTORY_SEPARATOR . $path)) {
			$item = $this->getFileInfo($path);
		} else {
			$item = $this->getDirectoryListing($path);
		}
		return $item;
	}

	public function isFile($path) {
		$path = realpath($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);
		return ($path !== false && strpos($path, $this->realRootDirectory) === 0 && is_file($path));
	}

	public function isDirectory($path) {
		$path = realpath($this->realRootDirectory . DIRECTORY_SEPARATOR . $path);
		return ($path !== false && strpos($path, $this->realRootDirectory) === 0 && is_dir($path));
	}

	public function mkdir($path) {
		if (empty($path)) {
			throw new InvalidArgumentException('You must specify a directory name.', 400);
		} else if (!$this->exists(dirname($path))) {
			throw new InvalidArgumentException('The destination directory does not exist.', 400);
		} else if ($this->exists($path)) {
			throw new InvalidArgumentException('The specified directory already exists on the server.', 409);
		}

		return mkdir($this->realRootDirectory . DIRECTORY_SEPARATOR . $path, 0770);
	}

	public function rename($from, $to) {
		if (empty($from) || empty($to)) {
			throw new InvalidArgumentException('You cannot rename the root directory.', 400);
		} else if (!$this->exists($from)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		} else if (!$this->exists(dirname($to))) {
			throw new InvalidArgumentException('The destination directory does not exist.', 400);
		} else if ($this->exists($to)) {
			throw new InvalidArgumentException('The specified path already exists on the server.', 409);
		}

		return rename($this->realRootDirectory . DIRECTORY_SEPARATOR . $from,
					  $this->realRootDirectory . DIRECTORY_SEPARATOR . $to);
	}

	public function saveUploadedFile(array $file, $destination, $allowOverwrite = false) {
		if (!isset($file['name']) || empty($file['name'])) {
			throw new InvalidArgumentException('Please select a file to upload.', 400);
		} else if ($file['size'] === 0) {
			throw new InvalidArgumentException('The file you have uploaded is too large.', 400);
		} else if (!$this->exists(dirname($destination))) {
			throw new InvalidArgumentException('The path you have specified does not exist.', 400);
		} else if (!$allowOverwrite && $this->exists($destination)) {
			throw new InvalidArgumentException('A file with the specified name already exists.', 409);
		}

		return move_uploaded_file($file['tmp_name'], $this->realRootDirectory . DIRECTORY_SEPARATOR . $destination);
	}

}

?>
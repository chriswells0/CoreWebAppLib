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

use \CWA\IO\FileNotFoundException;
use \CWA\MVC\Models\Model;

require_once \CWA\LIB_PATH . 'cwa/io/FileNotFoundException.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/models/Model.php';

if (!defined('CWA\IO\HASH_ALGORITHM')) {
	define('CWA\IO\HASH_ALGORITHM', 'sha1');
}

class FileInfo extends Model
{
	/* Protected variables: */
	protected $fileInfo;


	/* Constructor: */
	public function __construct($path) {
		$this->fileInfo = new \SplFileInfo($path);
		if (!$this->fileInfo->isFile()) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$properties = array('Name' => $this->fileInfo->getBaseName(),
							'Path' => $path,
							'Updated' => $this->fileInfo->getCTime(),
							'Modified' => $this->fileInfo->getMTime(),
							'Size' => $this->fileInfo->getSize(),
							'Hash' => hash_file(\CWA\IO\HASH_ALGORITHM, $path));
		parent::__construct($properties);
	}


	/* Public methods */

	// Pass all undefined method calls to the SplFileInfo object. -- cwells
	public function __call($method, array $arguments) {
		if (count($arguments) === 0) {
			return $this->fileInfo->{$method}();
		} else {
			return $this->fileInfo->{$method}($arguments);
		}
	}

	public function getFriendlySize() {
		$bytes = $this->fileInfo->getSize();
		$sz = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.1f", $bytes / pow(1024, $factor)) . ' ' . @$sz[$factor];
	}

}
?>
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

class DirectoryListing extends Model
{
	/* Constructor: */
	public function __construct($path) {
		if (!is_dir($path)) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		try {
			$iterator = new \DirectoryIterator($path);
		} catch (\Exception $ex) {
			throw new FileNotFoundException('The specified path does not exist.', 404);
		}
		$dirs = array();
		$files = array();
		foreach ($iterator as $file) {
			if ($file->isDot() || !$file->isReadable()) {
				continue;
			} else if ($file->isDir()) {
				$dirs[] = $file->getFilename();
			} else {
				$files[] = $file->getFilename();
			}
		}
		sort($dirs);
		sort($files);
		$properties = array('Name' => basename($path),
							'Path' => $path,
							'Directories' => $dirs,
							'Files' => $files);
		parent::__construct($properties);
	}
}
?>
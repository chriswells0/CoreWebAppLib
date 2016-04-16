<?php
/*
 * Copyright (c) 2014 Chris Wells (https://chriswells.io)
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

namespace CWA\MVC\Views;

require_once \CWA\LIB_PATH . 'cwa/mvc/views/View.php';

class ErrorView extends View
{
	/* Protected methods: */

	protected function detectFormat() {
		parent::detectFormat();
		// Prevent recursive "partial not found" errors. -- cwells
		if ($this->format !== 'json' && !file_exists("views/Error/view.$this->format.php")) {
			$this->format = 'html';
		}
	}
}

?>
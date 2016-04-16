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

namespace CWA\MVC\Controllers;

use \CWA\MVC\Views\ErrorView;

require_once \CWA\LIB_PATH . 'cwa/mvc/controllers/Controller.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/views/ErrorView.php';

class ErrorController extends Controller
{
	/* Protected variables: */


	/* Constructor: */
	public function __construct() {
		parent::__construct();
		$this->viewInfo['view']['title'] = 'Error';
		$this->viewInfo['view']['description'] = 'Error';
	}


	/* Public methods: */

	public function view($statusCode, $statusMessage = '') {
		if (empty($statusCode) || !is_numeric($statusCode)) $statusCode = '0';
		$this->logger->fatal("In ErrorController::view($statusCode): " . $_SERVER['REQUEST_URI'] . " - $statusMessage");
		$this->view = new ErrorView($this->modelType, 'view');
		$this->view->setStatus($statusMessage, $statusCode);
	}

	/* Another approach:
	public function _404($url) {
		parent::_404($url);
	}
	*/

}

?>
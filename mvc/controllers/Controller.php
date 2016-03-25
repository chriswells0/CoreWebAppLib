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

use \CWA\MVC\Views\View;
use \CWA\Util\Logger;

require_once \CWA\LIB_PATH . 'cwa/mvc/views/View.php';
require_once \CWA\LIB_PATH . 'cwa/util/Logger.php';

abstract class Controller
{
	/* Private variables: */


	/* Protected variables: */
	protected $app;
	protected $logger;
	protected $modelType;
	protected $pathInURL;
	protected $view;
//	protected $viewInfo = array('index' => array('template' => 'index'));
	protected $viewInfo = array();


	/* Constructor: */
	public function __construct() {
		global $app;
		$this->app = $app;
		$this->logger = $app->getLogger(\CWA\Util\LOG_NAME);

		// If the naming convention does not work well for a given controller, override this constructor,
		// set $this->modelType in the new constructor, and then call parent::__construct(). -- cwells
		if (!isset($this->modelType)) {
			$this->modelType = str_replace('Controller', '', get_class($this));
		}

		// If the naming convention does not work well for a given controller, override this constructor,
		// set $this->pathInURL in the new constructor, and then call parent::__construct(). -- cwells
		if (!isset($this->pathInURL)) {
			$this->pathInURL = \CWA\APP_ROOT . strtolower($this->modelType);
		}

		$modelFilePath = "models/$this->modelType.php";
		if (file_exists($modelFilePath)) {
			require_once $modelFilePath;
		}
	}


	/* Private methods: */

	// ???: Move to a Util class? -- cwells
	private function replaceStrings($string, $replacements, $beginDelimiter = '{', $endDelimiter = '}') {
		if (strlen($string) === 0 || strpos($string, $beginDelimiter) === false || strpos($string, $endDelimiter) === false) return $string;
		foreach ($replacements as $name => $value) {
			if (is_string($value) || is_numeric($value)) $string = str_replace("$beginDelimiter{$name}$endDelimiter", $value, $string);
		}
		return $string;
	}


	/* Protected methods: */

	protected function getTemplate($method) {
		return (isset($this->viewInfo[$method]['template']) ? $this->viewInfo[$method]['template'] : $method);
	}

	// Make public in a subclass to enable this method. -- cwells
	protected function index() {
		$this->loadView('index');
	}

	protected function loadView($method, $layout = 'default') {
		$this->view = new View($this->modelType, $this->getTemplate($method), $layout);
		$this->view->setData('ModelType', $this->modelType);
		$this->updateMetaInfo($method);
	}

	protected function updateMetaInfo($method, $properties = array()) {
		// ???: Should the view define and set its own title and description?
		if (isset($this->viewInfo[$method]['title'])) {
			$this->view->setTitle($this->replaceStrings($this->viewInfo[$method]['title'], $properties));
		}
		if (isset($this->viewInfo[$method]['description'])) {
			$this->view->setDescription($this->replaceStrings($this->viewInfo[$method]['description'], $properties));
		}
		if (isset($this->viewInfo[$method]['canonicalURL'])) {
			$this->view->setCanonicalURL($this->replaceStrings($this->viewInfo[$method]['canonicalURL'], $properties));
		}
	}


	/* Public methods: */

	public function __showView() {
		$this->view->setData('ControllerURL', $this->pathInURL);
		$this->view->setData('CurrentUser', $this->app->getCurrentUser());
		if ($this->view->getFormat() === 'atom') {
			$items = $this->view->getData($this->modelType . 'List');
			if (!is_null($items)) {
				$lastUpdated = null;
				$field = $items[0]->getUpdatedFieldName();
				foreach ($items as $item) { // Use the timestamp of the most recently updated item. -- cwells
					if ($item->$field > $lastUpdated) $lastUpdated = $item->$field;
				}
				$lastUpdated = new \DateTime($lastUpdated);
				$this->view->setData('LastUpdated', $lastUpdated);
			}
		}
		$this->view->show();
	}

}

?>
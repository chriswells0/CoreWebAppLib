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
			$this->modelType = str_replace('Controller', '', implode('', array_slice(explode('\\', get_class($this)), -1)));
		}

		// If the naming convention does not work well for a given controller, override this constructor,
		// set $this->pathInURL in the new constructor, and then call parent::__construct(). -- cwells
		if (!isset($this->pathInURL)) {
			$this->pathInURL = \CWA\APP_ROOT . $this->app->getControllerName();
		}

		$modelFilePath = "models/$this->modelType.php";
		if (file_exists($modelFilePath)) {
			require_once $modelFilePath;
		}
	}


	/* Private methods: */


	/* Protected methods: */

	protected function getTemplate($viewName) {
		return (isset($this->viewInfo[$viewName]['template']) ? $this->viewInfo[$viewName]['template'] : null);
	}

	// Make public in a subclass to enable this method. -- cwells
	protected function index() {
		$this->loadView('index');
	}

	protected function loadView($name, $layout = 'default') {
		$this->view = new View($this->modelType, $name, $this->getTemplate($name), $layout);
	}

	// ???: Move to a Util class? -- cwells
	protected function replaceStrings($string, $replacements, $beginDelimiter = '{', $endDelimiter = '}') {
		if (strlen($string) === 0 || strpos($string, $beginDelimiter) === false || strpos($string, $endDelimiter) === false) return $string;
		foreach ($replacements as $name => $value) {
			if (is_string($value) || is_numeric($value)) $string = str_replace("$beginDelimiter{$name}$endDelimiter", $value, $string);
		}
		return $string;
	}

	protected function updateMetaInfo($properties = array()) {
		// ???: Should the view define and set its own title and description?
		$viewName = $this->view->getName();
		if (isset($this->viewInfo[$viewName]['title'])) {
			$this->viewInfo[$viewName]['title'] = $this->replaceStrings($this->viewInfo[$viewName]['title'], $properties);
			$this->view->setTitle($this->viewInfo[$viewName]['title']);
		}
		if (isset($this->viewInfo[$viewName]['description'])) {
			$this->viewInfo[$viewName]['description'] = $this->replaceStrings($this->viewInfo[$viewName]['description'], $properties);
			$this->view->setDescription($this->viewInfo[$viewName]['description']);
		}
		if (isset($this->viewInfo[$viewName]['canonicalURL'])) {
			$this->viewInfo[$viewName]['canonicalURL'] = $this->replaceStrings($this->viewInfo[$viewName]['canonicalURL'], $properties);
			$this->view->setCanonicalURL($this->viewInfo[$viewName]['canonicalURL']);
		}
	}


	/* Public methods: */

	public function __showView() {
		if (!is_null($this->view->getData('NextURL')) && $this->view->getFormat() === 'html') {
			$this->app->redirect($this->view->getData('NextURL'));
		}
		// Set data that should be available in all views. -- cwells
		$this->view->setData(array('ControllerURL' => $this->pathInURL,
									'CurrentUser' => $this->app->getCurrentUser(),
									'ModelType' => $this->modelType));
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
		// Update the meta info using data from the view. -- cwells
		$item = $this->view->getData($this->modelType);
		if (!is_null($item)) {
			$this->updateMetaInfo($item->toArray());
		}
		$this->updateMetaInfo($this->view->getData());
		// Display the view. -- cwells
		$this->view->show();
	}

}

?>
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

use \CWA\IO\FileNotFoundException;
use \CWA\Net\HTTP\HttpResponse;
use \CWA\Util\Logger;

require_once \CWA\LIB_PATH . 'cwa/io/FileNotFoundException.php';
require_once \CWA\LIB_PATH . 'cwa/net/http/HttpResponse.php';
require_once \CWA\LIB_PATH . 'cwa/util/Logger.php';

if (!defined('CWA\MVC\VIEWS\HEADERS\CONTENT_SECURITY_POLICY')) {
	define('CWA\MVC\VIEWS\HEADERS\CONTENT_SECURITY_POLICY', "frame-ancestors 'none'");
}
if (!defined('CWA\MVC\VIEWS\HEADERS\X_CONTENT_TYPE_OPTIONS')) {
	define('CWA\MVC\VIEWS\HEADERS\X_CONTENT_TYPE_OPTIONS', 'nosniff');
}
if (!defined('CWA\MVC\VIEWS\HEADERS\X_FRAME_OPTIONS')) {
	define('CWA\MVC\VIEWS\HEADERS\X_FRAME_OPTIONS', 'DENY');
}
if (!defined('CWA\MVC\VIEWS\HEADERS\X_XSS_PROTECTION')) {
	define('CWA\MVC\VIEWS\HEADERS\X_XSS_PROTECTION', '1; mode=block');
}

class View
{
	/* Protected variables: */
	protected $canonicalURL;
	protected $data;
	protected $description;
	protected $format;
	protected $isPartial;
	protected $logger;
	protected $name;
	protected $pathToLayout;
	protected $pathToPartial;
	protected $statusCode;
	protected $statusMessage;
	protected $supportedFormats;
	protected $title;


	/* Constructor: */
	public function __construct($modelType, $name, $template = null, $layout = 'default') {
		$this->logger = $GLOBALS['app']->getLogger(\CWA\Util\LOG_NAME);
		// Remove the namespace from the modelType if present. -- cwells
		$lastSlash = strrpos($modelType, '\\');
		if ($lastSlash !== false) {
			$modelType = substr($modelType, $lastSlash + 1);
		}
		if (!isset($this->supportedFormats)) {
			$this->supportedFormats = array('html', 'json', 'atom');
		}
		$this->detectFormat();
		$this->name = $name;
		$this->pathToLayout = "views/_layouts/$layout.$this->format.php";
		$this->isPartial = (isset($_GET['partial']) && $_GET['partial'] === 'true');
		if (!$this->isPartial && !file_exists($this->pathToLayout)) {
			throw new FileNotFoundException("Layout not found: $this->pathToLayout", 404);
		}
		if (is_null($template)) {
			$template = $this->name;
		}
		$this->pathToPartial = "views/$modelType/$template.$this->format.php";
		if ($this->format !== 'json' && !file_exists($this->pathToPartial)) { // The partial is required for both the full and partial HTML views. -- cwells
			throw new FileNotFoundException("Partial not found: $this->pathToPartial", 404);
		}
		$this->data = array();
		$this->setStatus();
	}


	/* Destructor: */
	public function __destruct() {
		if (ob_get_length() !== false) {
			ob_end_flush();
		}
	}


	/* Protected methods: */

	protected function detectFormat() {
		if (!empty($_GET['format']) && array_search($_GET['format'], $this->supportedFormats, true) !== false) {
			$this->format = $_GET['format'];
		} else if (isset($_SERVER['HTTP_ACCEPT'])) {
			foreach ($this->supportedFormats as $supported) {
				if (strpos($_SERVER['HTTP_ACCEPT'], $supported) !== false) {
					$this->format = $supported;
					break;
				}
			}
		}

		if (empty($this->format)) {
			$this->logger->debug('Failed to detect content type.');
			$this->format = 'html';
		}
	}

	protected function performReplacements() {
		$syncToken = $GLOBALS['app']->getSyncToken();
		if (!is_null($syncToken)) {
			echo str_ireplace('</form>',
								'<input type="hidden" name="' . $syncToken['name'] . '" value="' . $syncToken['value'] . '" /></form>',
								ob_get_clean());
		}
	}

	protected function sanitize($values, $usage = null) {
		if (is_null($values)) return $values;
		if (is_null($usage)) $usage = $this->format;

		if ($usage === 'html' || $usage === 'json' || $usage === 'atom' || $usage === 'xml' || $usage === 'js') {
			if (is_array($values)) {
				foreach ($values as $key => $value) {
					if (is_array($value)) {
						$values[$key] = self::sanitize($value, $usage);
					} else if (is_string($value)) {
						$values[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
					} else if (!is_numeric($value) && !is_bool($value)) {
						$this->logger->debug('Not sanitizing object of type: ' . get_class($value));
					}
				}
			} else {
				$values = htmlspecialchars($values, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
			}
		} else {
			$values = '';
		}
		return $values;
	}

	protected function setHeaders() {
		if (function_exists('http_response_code')) { // This method is defined in PHP 5.4. -- cwells
			http_response_code($this->statusCode);
		} else {
			header("X-PHP-Response-Code: $this->statusCode", true, $this->statusCode);
		}

		if ($this->format === 'json') {
			HttpResponse::setContentType('application/json');
		} else if ($this->format === 'atom') {
			HttpResponse::setContentType('application/atom+xml');
		} else { // Also set charset for text subtypes. -- cwells
			HttpResponse::setContentType('text/html; charset=utf-8');
		}

		if (!is_null($this->canonicalURL)) {
			HttpResponse::setHeader('Link', "<$this->canonicalURL>; rel=\"canonical\"");
		}

		// Security related headers. -- cwells
		HttpResponse::setHeader('Content-Security-Policy', \CWA\MVC\VIEWS\HEADERS\CONTENT_SECURITY_POLICY);
		HttpResponse::setHeader('X-Content-Type-Options', \CWA\MVC\VIEWS\HEADERS\X_CONTENT_TYPE_OPTIONS);
		HttpResponse::setHeader('X-Frame-Options', \CWA\MVC\VIEWS\HEADERS\X_FRAME_OPTIONS);
		HttpResponse::setHeader('X-XSS-Protection', \CWA\MVC\VIEWS\HEADERS\X_XSS_PROTECTION);
	}


	/* Public methods for managing properties: */

	public function getCanonicalURL() {
		return $this->sanitize($this->canonicalURL);
	}

	public function getData($name = null) {
		if (is_null($name)) {
			return $this->data;
		} else if (isset($this->data[$name])) {
			return $this->data[$name];
		} else {
			return null;
		}
	}

	public function getDescription() {
		return substr($this->sanitize($this->description), 0, 160); // Max of 160 characters.
	}

	public function getFormat() {
		return $this->format;
	}

	public function getName() {
		return $this->name;
	}

	public function getStatusCode() {
		return $this->statusCode;
	}

	public function getStatusMessage() {
		return $this->statusMessage;
	}

	public function getTitle() {
		return $this->sanitize($this->title);
	}

	public function isPartial() {
		return $this->isPartial;
	}

	public function setCanonicalURL($canonicalURL) {
		$this->canonicalURL = is_null($canonicalURL) ? $canonicalURL : strtolower($canonicalURL);
	}

	public function setData($data, $value = null) {
		if (is_array($data)) {
			$this->data = array_merge($this->data, $data);
		} else {
			$this->data[$data] = $value;
		}
	}

	public function setDescription($description) {
		$this->description = $description;
	}

	public function setStatus($message = '', $code = 200) {
		$this->statusCode = $code;
		$this->statusMessage = $message;
	}

	public function setTitle($title) {
		$this->title = $title;
	}

	public function show() {
		// Make all items in the data array available to the templates as variables. -- cwells
		$variables = $this->sanitize($this->data);
		if (is_array($variables)) {
			extract($variables);
		}

		$this->setHeaders();

		ob_start();
		if ($this->isPartial) {
			require_once $this->pathToPartial;
		} else {
			require_once $this->pathToLayout;
		}
		$this->performReplacements();
		if (ob_get_length() !== false) {
			ob_end_flush();
		}
	}

}

?>
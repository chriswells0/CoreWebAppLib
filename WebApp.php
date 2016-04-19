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

namespace CWA;

use \CWA\DB\DatabaseException;
use \CWA\MVC\Controllers\BadMethodCallException;
use \CWA\MVC\Controllers\ErrorController;
use \CWA\MVC\Controllers\InvalidArgumentException;
use \CWA\MVC\Models\User;
use \CWA\Net\HTTP\FileNotFoundException;
use \CWA\Net\HTTP\HttpResponse;

// ENT_HTML5 and ENT_SUBSTITUTE are not defined until PHP 5.4. -- cwells
defined('ENT_HTML5') || define('ENT_HTML5', (16|32));
defined('ENT_SUBSTITUTE') || define('ENT_SUBSTITUTE', 8);

if (!defined('CWA\APP_ROOT')) {
	define('CWA\APP_ROOT', '/');
}
if (!defined('CWA\LIB_PATH')) {
	if (defined('LIB_PATH')) {
		define('CWA\LIB_PATH', \LIB_PATH);
	} else {
		define('CWA\LIB_PATH', '../lib/');
	}
}
// Each controller that needs storage should create a subdirectory in this directory. -- cwells
if (!defined('CWA\STORAGE_PATH')) {
	define('CWA\STORAGE_PATH', '../storage');
}

require_once \CWA\LIB_PATH . 'cwa/mvc/controllers/BadMethodCallException.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/models/User.php';
require_once \CWA\LIB_PATH . 'cwa/net/http/FileNotFoundException.php';
require_once \CWA\LIB_PATH . 'cwa/net/http/HttpResponse.php';
require_once \CWA\LIB_PATH . 'cwa/util/Logger.php';

abstract class WebApp
{
	/* Protected variables: */
	protected $controller;
	protected $controllerAttributes;
	protected $controllerName;
	protected $controllers = array();
	protected $db;
	protected $logger;
	protected $loginURL = '/account/login';
	protected $method;


	/* Constructor: */
	public function __construct() {
		register_shutdown_function(array($this, 'checkForErrors'));
		$this->logger = $this->getLogger(\CWA\Util\LOG_NAME);
		session_start();
	}

	/* Destructor: */
	public function __destruct() {
		$this->controller = null;
	}


	/* Protected methods: */

	protected function callMethod() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->logger->trace('Using POST data as method parameters.');
			$this->controller->{$this->method}($_POST);
		} else if (!empty($_GET['parameter'])) {
			$this->logger->trace("parameter: {$_GET['parameter']}");
			$this->controller->{$this->method}($_GET['parameter']);
		} else {
			$this->logger->trace('Not using method parameters.');
			$this->controller->{$this->method}();
		}
	}

	protected function getBrowserFingerprint() {
		return md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
	}

	protected function loadController() {
		if (isset($_GET['controller']) && isset($this->controllers[$_GET['controller']])) {
			$this->controllerName = $_GET['controller'];
		} else {
			/*
			$this->controllerName = 'default';
			if (isset($_GET['controller'])) $_GET['method'] = $_GET['controller'];
			*/
			throw new FileNotFoundException('Page Not Found', 404);
		}
		$this->controllerAttributes = $this->controllers[$this->controllerName];
		$controllerClass = $this->controllerAttributes['class'];
		$this->logger->trace("controllerClass: $controllerClass");
		if (file_exists("controllers/{$controllerClass}.php")) {
			require_once "controllers/{$controllerClass}.php";
		} else {
			require_once \CWA\LIB_PATH . "cwa/mvc/controllers/{$controllerClass}.php";
			$controllerClass = "\\cwa\\mvc\\controllers\\$controllerClass";
		}
		$this->controller = new $controllerClass();
	}

	protected function validateMethod() {
		if (isset($_GET['method'])) {
			$this->method = $_GET['method'];
		} else {
			$this->method = 'index';
		}
		$this->logger->trace("method: $this->method");

		if (!is_callable(array($this->controller, $this->method))) {
			$this->logger->fatal($this->controllerAttributes['class'] . "::$this->method is not callable.");
			throw new BadMethodCallException('Page Not Found', 404);
		} else if (strpos($this->method, '__') === 0) { // Methods beginning with __ are disallowed. -- cwells
			$this->logger->fatal($this->controllerAttributes['class'] . "::$this->method is not directly accessible.");
			throw new BadMethodCallException('Page Not Found', 404);
		}

		if (!$this->userIsAuthorized()) {
			$this->logger->warn('User does not have access to ' . $this->controllerAttributes['class'] . "::$this->method.");
			$this->redirectToLogin();
		}
	}

	protected function validateSession() {
		// The browser fingerprint is used to prevent session hijacking. Even if an adversary
		// steals the session cookie from a valid user, this can be used to validate that the
		// session is only accessed from the correct location by the correct browser. -- cwells
		if (isset($_SESSION['CWA_browserFingerprint']) && $_SESSION['CWA_browserFingerprint'] !== $this->getBrowserFingerprint()) {
			$this->logger->warn('Browser fingerprint mismatch.');
			$this->recreateSession(); // No longer logged in. -- cwells
		}

		// The synchronizer token is used to prevent cross-site request forgery (CSRF). The
		// server should generate a unique token name and value for every session and include
		// it as a hidden field in every form. An adversary on another site cannot "guess" the
		// token to send with a forged request, which secures the forms against attack. -- cwells
		if (isset($_SESSION['CWA_syncToken']) && count($_POST) !== 0) {
			$token = $_SESSION['CWA_syncToken'];
			if (!isset($_POST[$token['name']]) || $_POST[$token['name']] !== $token['value']) {
				$this->logger->warn('Invalid CSRF synchronizer token.');
				$this->recreateSession();
				$this->redirect($this->loginURL); // Prevent a POST URL from being set as the returnURL. -- cwells
			} else {
				unset($_POST[$token['name']]);
			}
		}
	}


	/* Public methods: */

	public function checkForErrors() {
		$error = error_get_last();
		if (!is_null($error) && $error['type'] === E_ERROR) {
			$this->logger->fatal('WebApp::checkForErrors() - exiting with error.');
			$this->logger->phpError($error['type'], $error['message'], $error['file'], $error['line']);
			$this->redirect(\CWA\APP_ROOT . 'error/view/500');
		}
	}

	public function getAuthorizedRoles($controller = null, $method = null) {
		if (is_null($controller)) {
			$controller = $this->controllerName;
		}
		if (is_null($method)) {
			$method = $this->method;
		}
		$authorizedRoles = null;
		if (isset($this->controllers['__GLOBAL__'])
			&& !empty($this->controllers['__GLOBAL__']['authorizedRoles'])
			&& isset($this->controllers['__GLOBAL__']['authorizedRoles'][$method])) {
				if (is_null($authorizedRoles)) $authorizedRoles = array();
				$authorizedRoles = array_merge($authorizedRoles, $this->controllers['__GLOBAL__']['authorizedRoles'][$method]);
		}
		if (!empty($this->controllers[$controller]['authorizedRoles'])) {
			if (isset($this->controllers[$controller]['authorizedRoles']['__ALL__'])) {
				if (is_null($authorizedRoles)) $authorizedRoles = array();
				$authorizedRoles = array_merge($authorizedRoles, $this->controllers[$controller]['authorizedRoles']['__ALL__']);
			}
			if (isset($this->controllers[$controller]['authorizedRoles'][$method])) {
				if (is_null($authorizedRoles)) $authorizedRoles = array();
				$authorizedRoles = array_merge($authorizedRoles, $this->controllers[$controller]['authorizedRoles'][$method]);
			}
		}
		if (is_null($authorizedRoles)) {
			return null;
		} else {
			return array_unique($authorizedRoles);
		}
	}

	public function getControllerName() {
		return $this->controllerName;
	}

	public function getControllers() {
		return $this->controllers;
	}

	public function getCurrentUser() {
		if (!isset($_SESSION['CWA_currentUser'])) {
			$_SESSION['CWA_currentUser'] = new User();
		}
		return $_SESSION['CWA_currentUser'];
	}

	public function getDatabase() {
		if (!isset($this->db)) {
			require_once \CWA\LIB_PATH . 'cwa/db/Database.php';
			$this->db = new \CWA\DB\Database(\CWA\DB\DSN, \CWA\DB\USERNAME, \CWA\DB\PASSWORD);
			if (is_null($this->db) || $this->db->getErrorCode()) {
				throw new DatabaseException('Failed to connect to DB.', 500);
			}
		}
		return $this->db;
	}

	public function getLogger($name) {
		return \CWA\Util\Logger::getLogger($name);
	}

	public function getSyncToken() {
		if (isset($_SESSION['CWA_syncToken'])) {
			return $_SESSION['CWA_syncToken'];
		} else {
			return null;
		}
	}

	public function main() {
		try {
			$this->logger->debug("In main()");
			$this->validateSession(); // Do this before doing anything else! -- cwells
			$this->loadController();
			$this->validateMethod();
			$this->callMethod();
			$this->controller->__showView();
		} catch (FileNotFoundException $e) {
			$this->logger->error('Caught FileNotFoundException in main()', $e);
			$this->showError($e->getMessage(), $e->getCode());
		} catch (BadMethodCallException $e) {
			$this->logger->error('Caught BadMethodCallException in main()', $e);
			$this->showError($e->getMessage(), $e->getCode());
		} catch (InvalidArgumentException $e) {
			$this->logger->error('Caught InvalidArgumentException in main()', $e);
			$this->showError($e->getMessage(), $e->getCode());
		} catch (DatabaseException $e) {
			$this->logger->error('Caught DatabaseException in main()', $e);
			$this->showError($e->getMessage(), $e->getCode());
		} catch (\Exception $e) {
			$this->logger->error('Caught unknown exception type in main()', $e);
			$this->showError($e->getMessage(), 500);
		}
	}

	public function recreateSession() {
		$_SESSION = array();
		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		}
		session_destroy();
		session_start();
		session_regenerate_id(true);
	}

//	public function redirect($url, array $params, $session = false, $status = 0) {
	public function redirect($url, array $params = null) {
		HttpResponse::redirect($url, $params);
	}

	public function redirectToHome() {
		HttpResponse::redirect(\CWA\APP_ROOT);
	}

	public function redirectToLogin() {
		// Cannot send a 401 here because it's a redirect (302). -- cwells
		HttpResponse::redirect($this->loginURL, array('returnURL' => $_SERVER['REQUEST_URI']));
	}

	public function setControllers(array $controllers) {
		$this->controllers = $controllers;
	}

	public function setCurrentUser(User $user) {
		$this->recreateSession(); // Prevent session fixation by always starting a new session. -- cwells
		$_SESSION['CWA_currentUser'] = $user;
		// See validateSession() for how these values are leveraged. -- cwells
		$_SESSION['CWA_browserFingerprint'] = $this->getBrowserFingerprint(); // Prevent session hijacking. -- cwells
		$_SESSION['CWA_syncToken'] = array('name' => 'SyncToken_' . mt_rand(),
											'value' => base64_encode(openssl_random_pseudo_bytes(64)));
	}

	public function showError($statusMessage, $statusCode) {
		$controllers = $this->getControllers();
		$controllerClass = $controllers['error']['class'];
		if (file_exists("controllers/{$controllerClass}.php")) {
			require_once "controllers/{$controllerClass}.php";
		} else {
			require_once \CWA\LIB_PATH . "cwa/mvc/controllers/{$controllerClass}.php";
			$controllerClass = "\\cwa\\mvc\\controllers\\$controllerClass";
		}
		$controller = new $controllerClass();
		$controller->view($statusCode, $statusMessage);
		$controller->__showView();
		exit();
	}

	public function userIsAuthorized($controller = null, $method = null, User $user = null) {
		$roles = $this->getAuthorizedRoles($controller, $method);
		if (is_null($roles)) {
			return true; // A null role array means no authentication required. -- cwells
		}

		if (is_null($user)) { // When no user is specified, test against the current user. -- cwells
			$user = $this->getCurrentUser();
		}

		if (empty($roles)) { // Empty role array means must be logged in, but no specific role needed. -- cwells
			return $user->isLoggedIn();
		} else {
			return $user->hasRole($roles);
		}
	}

}

?>
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

namespace CWA\Util;

if (!defined('CWA\Util\LOG_FORMAT')) {
	define('CWA\Util\LOG_FORMAT', '[%TIMESTAMP] [%LEVEL] [client %{REMOTE_ADDR}] %MESSAGE');
}
if (!defined('CWA\Util\LOG_LEVEL')) {
	define('CWA\Util\LOG_LEVEL', 'WARN');
}
if (!defined('CWA\Util\LOG_NAME')) {
	define('CWA\Util\LOG_NAME', 'CWA.Util.Logger');
}
if (!defined('CWA\Util\LOG_PATH')) {
	define('CWA\Util\LOG_PATH', '../log/%s.log');
}
if (!defined('CWA\Util\LOG_TIMESTAMP')) {
	define('CWA\Util\LOG_TIMESTAMP', 'D M j G:i:s T Y');
}

class Logger {

	/* Static variables: */
	private static $LEVEL = array(
		'OFF'	=> 0,
		'FATAL'	=> 1,
		'ERROR'	=> 2,
		'WARN'	=> 3,
		'INFO'	=> 4,
		'DEBUG'	=> 5,
		'TRACE'	=> 6,
		'ALL'	=> 7
	);
	protected static $loggers = array();

	/* Private variables: */
	private $logFile;
	private $logFormat;
	private $logLevel;


	/* Constructor: */
	public function __construct($name) {
		set_error_handler(array($this, 'phpError'));
		set_exception_handler(array($this, 'exception'));

		$this->logLevel = self::$LEVEL[\CWA\Util\LOG_LEVEL];
		if (!$this->logFile = @fopen(sprintf(\CWA\Util\LOG_PATH, $name), 'a')) {
			$message = 'Failed to open log file: ' . sprintf(\CWA\Util\LOG_PATH, $name);
			error_log($message);
			die($message);
		}

		// This allows the variables in \CWA\Util\LOG_FORMAT to be specified in any order or omitted. -- cwells
		$this->logFormat = str_replace(array('%TIMESTAMP', '%LEVEL', '%MESSAGE'),
										array('%1$s', '%2$s', '%3$s'),
										\CWA\Util\LOG_FORMAT) . "\n";

		// Replace all instances of ${VARNAME} with the value of $_SERVER['VARNAME'] or '-' if it doesn't exist. -- cwells
		$matches = array();
		$matchResult = preg_match_all('/%{([^}]+)}/', $this->logFormat, $matches);
		if ($matchResult !== false && count($matches) === 2) {
			foreach ($matches[0] as $index => $match) {
				$this->logFormat = str_replace($match, (isset($_SERVER[$matches[1][$index]]) ? $_SERVER[$matches[1][$index]] : '-'), $this->logFormat);
			}
		}
	}

	/* Destructor: */
	public function __destruct() {
		if (is_resource($this->logFile)) {
			fclose($this->logFile);
		}
	}


	/* Static methods: */

	public static function getLogger($name) {
		if (!isset(self::$loggers[$name])) {
			self::$loggers[$name] = new Logger($name);
		}
		return self::$loggers[$name];
	}


	/* Public methods: */

	public function debug($message, \Throwable $throwable = null) {
		if ($this->logLevel >= self::$LEVEL['DEBUG']) {
			$this->log('DEBUG', $message, $throwable);
		}
	}

	public function error($message, \Throwable $throwable = null) {
		if ($this->logLevel >= self::$LEVEL['ERROR']) {
			$this->log('ERROR', $message, $throwable);
		}
	}

	public function exception(\Throwable $throwable) {
		$this->error('In exception handler:', $throwable);
	}

	public function fatal($message, \Throwable $throwable = null) {
		if ($this->logLevel >= self::$LEVEL['FATAL']) {
			$this->log('FATAL', $message, $throwable);
		}
	}

	public function info($message, \Throwable $throwable = null) {
		if ($this->logLevel >= self::$LEVEL['INFO']) {
			$this->log('INFO', $message, $throwable);
		}
	}

	public function log($level = '', $message = '', \Throwable $throwable = null) {
		if (is_resource($this->logFile)) {
			$entryTime = date(\CWA\Util\LOG_TIMESTAMP); // Timestamps will match for the message and exception entries. -- cwells
			fwrite($this->logFile, sprintf($this->logFormat, $entryTime, $level, $message));
			if (!is_null($throwable)) {
				fwrite($this->logFile, sprintf($this->logFormat, $entryTime, $level, $throwable->__toString()));
			}
		}
	}

	public function phpError($errno, $errstr, $errfile, $errline, $errcontext = null) {
		if (!(error_reporting() & $errno)) { // Reporting is disabled for this error level. -- cwells
			return;
		}
		$this->log('PHP', "Error $errno with message '$errstr' in $errfile:$errline");
		return true; // Don't execute the internal PHP error handler. -- cwells
	}

	public function trace($message, \Throwable $throwable = null) {
		if ($this->logLevel >= self::$LEVEL['TRACE']) {
			$this->log('TRACE', $message, $throwable);
		}
	}

	public function warn($message, \Throwable $throwable = null) {
		if ($this->logLevel >= self::$LEVEL['WARN']) {
			$this->log('WARN', $message, $throwable);
		}
	}

}

?>
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

/*
 * This class is intended to provide a small subset of the features provided
 * by the PECL HttpResponse class in environments where PECL is not installed.
 * See: http://www.php.net/manual/en/class.httpresponse.php
 */

namespace CWA\Net\HTTP;

class HttpResponse
{
	/* Static methods: */

	public static function getContentDisposition() {
		return self::getHeader('Content-Disposition');
	}

	public static function getContentType() {
		return self::getHeader('Content-type');
	}

	public static function getHeader($name) {
		$headersList = headers_list();
		if (empty($name)) return $headersList;
		$name .= ': ';
		foreach ($headersList as $i => $header) {
			if (strpos($header, $name) === 0) {
				$headerValue = explode(': ', $header, 2);
				return $headerValue[1];
			}
		}
		return false;
	}

	public static function getRequestHeaders() {
		return headers_list();
	}

	public static function redirect($url, array $params = null, $session = false, $status = 0) {
		if (!empty($params)) $url .= '?' . http_build_query($params);
		header("Location: $url", true, $status);
		exit;
	}

	public static function setContentDisposition($filename, $inline = false) {
		if (empty($filename)) return false;
		header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '\"', $filename) . '"');
		return true;
	}

	public static function setContentType($contentType) {
		$slashPos = strpos($contentType, '/');
		if ($slashPos === false || $slashPos === 0 || $slashPos === (strlen($contentType) - 1)) {
			return false; // Slash was not found or was the first/last char.
		}

		header("Content-type: $contentType");
		return true;
	}

	public static function setHeader($name = '', $value = '', $replace = true) {
		if (!empty($name)) {
			$header = "$name: $value";
		} else {
			$header = $value;
		}

		if (empty($header)) {
			return false;
		}

		header($header, $replace);
		return true;
	}


	/* Public methods: */

	/*
	public function setCookie($name, $value, $expire = 0, $path, $domain, $secure = false, $httpOnly = false) {
		return setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
	}

	public function setRawCookie($name, $value, $expire = 0, $path, $domain, $secure = false, $httpOnly = false) {
		return setrawcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
	}
	*/

}

?>
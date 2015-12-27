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

namespace CWA\MVC\Models;

abstract class Model /* implements JsonSerializable - PHP 5.4+ -- cwells */
{
	/* Private variables: */


	/* Protected variables: */
	protected $properties = array(); // Managed by __get() and __set() or by subclass getter/setter methods.


	/* Constructor: */
	public function __construct(array $properties = null) {
		if (isset($properties) && is_array($properties)) $this->properties = $properties;
	}


	/* Magic methods: */

	public function &__get($property) {
		if (!array_key_exists($property, $this->properties)) {
			$this->properties[$property] = null;
		}
		return $this->properties[$property];
	}

	public function __isset($property) {
		return isset($this->properties[$property]);
	}

	public function __set($property, $value) {
		$this->properties[$property] = $value;
	}

	public function __unset($property) {
		unset($this->properties[$property]);
	}


	/* Public methods: */

	public function jsonSerialize() {
		return $this->properties;
	}

	public function toArray($deep = false) {
		if (!$deep) {
			return $this->properties;
		} else {
			$properties = $this->properties;
			array_walk_recursive($properties, function (&$value, $key) {
				if (is_object($value) && method_exists($value, 'toArray')) {
					$value = $value->toArray(true);
				}
			});
			return $properties;
		}
	}

	public function toJSON() {
		return json_encode($this->toArray(true));
	}

}

?>
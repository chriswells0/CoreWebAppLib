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

use \CWA\DB\DatabaseMapping;

require_once \CWA\LIB_PATH . 'cwa/db/DatabaseMapping.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/models/Model.php';

abstract class DatabaseRecord extends Model
{
	/* Private variables: */
	private $className;


	/* Protected variables: */
	protected static $altKeyName = null;
	protected static $createdFieldName = 'Created';
	protected static $dbMappings = array();
	protected static $mappingLoader;
	protected static $primaryKeyName = 'ID';
	protected static $updatedFieldName = 'Updated';


	/* Public constants: */
	const MAPPINGS_NONE = 0;
	const MAPPINGS_NO_LAZY = 1;
	const MAPPINGS_ALL = 2;


	/* Constructor: */
	public function __construct(array $properties = null, $loadMappings = DatabaseRecord::MAPPINGS_NO_LAZY) {
		parent::__construct($properties);
		$this->className = get_class($this);
		if (!isset(self::$dbMappings[$this->className])) {
			self::$dbMappings[$this->className] = array();
		}
		if ($loadMappings !== self::MAPPINGS_NONE && is_callable(static::$mappingLoader)) {
			$noLazy = ($loadMappings === self::MAPPINGS_NO_LAZY);
			foreach (self::$dbMappings[$this->className] as $property => $mapping) {
				if ($noLazy && $mapping->Lazy) continue; // Skip lazy mappings at load time. -- cwells
				call_user_func(static::$mappingLoader, $this, $property, $mapping);
			}
		}
	}


	/* Magic methods: */

	public function &__get($property) {
		$value = parent::__get($property);
		// I removed the Lazy condition because selectAll() defaults to MAPPINGS_NONE. -- cwells
//		if (is_null($value) && isset(self::$dbMappings[$this->className][$property]) && self::$dbMappings[$this->className][$property]->Lazy && is_callable(static::$mappingLoader)) {
		if (is_null($value) && isset(self::$dbMappings[$this->className][$property]) && is_callable(static::$mappingLoader)) {
			// This property has a lazy-loaded DB mapping, so load it now. -- cwells
			if (call_user_func(static::$mappingLoader, $this, $property, self::$dbMappings[$this->className][$property])) {
				return $this->properties[$property];
			}
		}
		return $value;
	}


	/* Static methods: */

	public static function addDatabaseMapping($property, DatabaseMapping $mapping) {
		$subclass = get_called_class();
		if (!isset(self::$dbMappings[$subclass])) {
			self::$dbMappings[$subclass] = array();
		}
		self::$dbMappings[$subclass][$property] = $mapping;
	}

	public static function getAlternateKeyName() {
		return (isset(static::$altKeyName) ? static::$altKeyName : static::$primaryKeyName);
	}

	public static function getCreatedFieldName() {
		return static::$createdFieldName;
	}

	public static function getDatabaseMappings() {
		$subclass = get_called_class();
		if (!isset(self::$dbMappings[$subclass])) {
			self::$dbMappings[$subclass] = array();
		}
		return self::$dbMappings[$subclass];
	}

	public static function getPrimaryKeyName() {
		return static::$primaryKeyName;
	}

	public static function getUpdatedFieldName() {
		return static::$updatedFieldName;
	}

	// The Callable type hint doesn't work until PHP 5.4. -- cwells
	public static function setMappingLoader(/* Callable */ $mappingLoader) {
		static::$mappingLoader = $mappingLoader;
	}


	/* Public methods: */

	public function getID() {
		return $this->properties[static::$primaryKeyName];
	}

	/*
	public function getPrimaryKey() {
		return $this->properties[static::$primaryKeyName];
	}
	*/

	public function setID($value) {
		$this->properties[static::$primaryKeyName] = $value;
	}
}

?>
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

namespace CWA\DB;

use \PDO;
use \PDOException;
use \CWA\DB\DatabaseMapping;
use \CWA\MVC\Models\DatabaseRecord;
use \CWA\Util\Logger;

require_once \CWA\LIB_PATH . 'cwa/db/DatabaseMapping.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/models/DatabaseRecord.php';
require_once \CWA\LIB_PATH . 'cwa/util/Logger.php';

if (!defined('CWA\DB\DBTYPE')) {
	define('CWA\DB\DBTYPE', 'mysql');
}
if (!defined('CWA\DB\HOST')) {
	define('CWA\DB\HOST', 'localhost');
}
if (!defined('CWA\DB\BACKUP_COMMAND')) {
	define('CWA\DB\BACKUP_COMMAND', '/usr/local/bin/mysqldump --single-transaction --skip-extended-insert -u ' . \CWA\DB\USERNAME . ' --password=\'' . \CWA\DB\PASSWORD . '\' ' . \CWA\DB\DBNAME);
}
if (!defined('CWA\DB\DSN')) {
	define('CWA\DB\DSN', \CWA\DB\DBTYPE . ':host=' . \CWA\DB\HOST . ';dbname=' . \CWA\DB\DBNAME);
}
if (!defined('CWA\DB\DATETIME_DB_TO_PHP')) {
	define('CWA\DB\DATETIME_DB_TO_PHP', 'l, F jS, Y @ g:i A T');
}
if (!defined('CWA\DB\DATETIME_PHP_TO_DB')) {
	define('CWA\DB\DATETIME_PHP_TO_DB', 'Y-m-d H:i:s');
}

class Database
{
	/* Private variables: */
	private $dsn;
	private $errorCode;
	private $errorInfo;
	private $handle;
	private $logger;
	private $options;
	private $password;
	private $statement;
	private $username;


	/* Constructor: */
	public function __construct($dsn, $username = null, $password = null, array $options = null) {
		register_shutdown_function(array($this, '__destruct')); // Ensure the DB is always cleaned up. -- cwells

		if (!isset($this->logger)) { // Override by setting in a subclass. --cwells
			$this->logger = Logger::getLogger(\CWA\Util\LOG_NAME);
		}
		$this->dsn = $dsn;
		$this->username = $username;
		$this->password = $password;
		$this->options = $options;

		try {
			$this->handle = new PDO($this->dsn, $this->username, $this->password, $this->options);
			$this->handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
	}


	/* Destructor: */
	public function __destruct() {
		if ($this->handle) {
			if ($this->inTransaction()) {
				$this->rollbackTransaction();
			}
			$this->handle = null;
		}
	}


	/* Private methods: */

	private function setLastError(\Exception $e) {
		$this->errorCode = $e->getCode();
		$this->errorInfo = $e->errorInfo;
		$this->logger->error('Caught database exception:', $e);
	}


	/* Public methods: */

	public function beginTransaction() {
		return $this->handle->beginTransaction();
	}

	public function commitTransaction() {
		$this->logger->debug('Committing database transaction.');
		$retVal = $this->handle->commit();
		if ($retVal === false) {
			$this->logger->error('Failed to commit database transaction.');
		}
		return $retVal;
	}

	public function delete($class, $id) {
		if (!is_subclass_of($class, '\CWA\MVC\Models\DatabaseRecord') || empty($id)) return false;
		$primaryKey = $class::getAlternateKeyName();
		$sql = "DELETE FROM `$class` WHERE $primaryKey = :$primaryKey";

		$retVal = false;
		try {
			$this->statement = $this->handle->prepare($sql);
			$retVal = $this->statement->execute(array($primaryKey => $id));
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function execute($sql, array $parameters = null) {
		$retVal = false;
		try {
			$this->statement = $this->handle->prepare($sql);
			$retVal = $this->statement->execute($parameters);
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function fetchAll($sql, array $parameters = null, $fetchMode = PDO::FETCH_ASSOC) {
		$retVal = false;
		try {
			$this->statement = null;
			$statement = $this->handle->prepare($sql);
			if ($statement->execute($parameters)) {
				$statement->setFetchMode($fetchMode);
				$retVal = $statement->fetchAll();
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function getBackupCommand() {
		return \CWA\DB\BACKUP_COMMAND;
	}

	public function getErrorCode() {
		return $this->errorCode;
	}

	public function getErrorInfo() {
		return $this->errorInfo;
	}

	public function getHandle() {
		return $this->handle;
	}

	public function getRowCount() {
		if (is_null($this->statement)) {
			return $this->handle->query('SELECT FOUND_ROWS()')->fetchColumn();
		} else {
			return $this->statement->rowCount();
		}
	}

	public function insert($class, array &$properties) {
		if (!is_subclass_of($class, '\CWA\MVC\Models\DatabaseRecord') || !is_array($properties)) return false;

		$primaryKey = $class::getPrimaryKeyName();
		if (!empty($properties[$primaryKey])) return false;

		$now = date(\CWA\DB\DATETIME_PHP_TO_DB);
		$createdField = $class::getCreatedFieldName();
		$updatedField = $class::getUpdatedFieldName();
		if (isset($createdField)) $properties[$createdField] = $now;
		if (isset($updatedField)) $properties[$updatedField] = $now;

		$mappingsToUpdate = array();
		$mappingValues = array();
		$mappings = $class::getDatabaseMappings();
		foreach ($mappings as $property => $mapping) {
			if (!isset($properties[$property])) continue;
			// OneToOne and ManyToOne records will be updated via the foreign key field.
			// No support for updating OneToMany records due to orphans. -- cwells
			if ($mapping->Relationship === DatabaseMapping::ManyToMany) {
				// Store this mapping instance in one array and its value (array of IDs) in another. -- cwells
				$mappingsToUpdate[$property] = $mapping;
				$mappingValues[$property] = array_filter($properties[$property]); // Remove empty values since they can't reference a DB record. -- cwells
			}
//			unset($properties[$property]);
		}

		$fields = array_keys($properties);
		foreach ($fields as $field) {
			if ($field === $primaryKey || is_array($properties[$field])) {
				unset($properties[$field]);
			}
		}
		$fields = array_keys($properties);
		$sql = 'INSERT INTO `' . $class . '` ('
				. implode(', ', $fields)
				. ') VALUE (:'
				. implode(', :', $fields) . ')';
		$this->logger->debug("SQL: $sql");
		$this->logger->debug('Values: ' . implode(',', $properties));

		$retVal = false;
		try {
			$useTransaction = (count($mappingsToUpdate) > 0);
			if ($useTransaction && $this->beginTransaction() === false) {
				$this->logger->error('Failed to begin database transaction.');
				return false;
			}

			$this->statement = $this->handle->prepare($sql);
			$retVal = $this->statement->execute($properties);
			if ($retVal) {
				$properties[$primaryKey] = $this->handle->lastInsertId();

				foreach ($mappingsToUpdate as $key => $mapping) {
					if (count($mappingValues[$key]) === 0) {
						continue; // No items for this mapping. -- cwells
					}
					// Subtract 1 from the count for the extra '(?, ?)' being added. -- cwells
					$sql = 'INSERT IGNORE INTO `' . $mapping->Table . '` (' . $mapping->ToField
							. ', ' . $mapping->Submappings[0]->FromField . ') VALUES '
							. str_repeat('(?, ?), ', count($mappingValues[$key]) - 1) . '(?, ?)';
					$subvalues = array();
					foreach ($mappingValues[$key] as $item) {
						// Save the value of the main object's ID and the mapped object's ID. -- cwells
						$subvalues[] = $properties[$mapping->FromField];
						$subvalues[] = (is_subclass_of($item, '\CWA\MVC\Models\DatabaseRecord') ? $item->getID() : $item);
					}
					$this->logger->debug("SQL: $sql");
					$this->logger->debug('Values: ' . implode(',', $subvalues));

					try {
						$this->statement = $this->handle->prepare($sql);
						$retVal = $this->statement->execute($subvalues);
					} catch (PDOException $e) {
						$retVal = false;
						$this->setLastError($e);
					}
					if ($retVal === false) {
						break;
					}
				}
			}

			if ($useTransaction) {
				if ($retVal) {
					$retVal = $this->commitTransaction();
				} else {
					$this->rollbackTransaction();
				}
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function inTransaction() {
		$retVal = false;
		try {
			$retVal = $this->handle->inTransaction();
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function loadMapping(DatabaseRecord $instance, $property, DatabaseMapping $mapping) {
		$retVal = false;
		try {
//			$this->logger->trace("FromField starts as $mapping->FromField");
//			$mapping->FromField = $instance->{$mapping->FromField}; // Update FromField to use the value in the instance's property. -- cwells
			$sql = "SELECT `$mapping->ObjectType`.* FROM `$mapping->Table`";
			$where = " WHERE :FromField = $mapping->ToField";
			$submappings = $mapping->Submappings;
			if (isset($submappings)) {
				foreach ($submappings as $submapping) {
					$sql .= ", `$submapping->Table`";
					$where .= " AND $submapping->FromField = $submapping->ToField";
				}
			}
			$statement = $this->handle->prepare($sql . $where);
			$properties = array('FromField' => $instance->{$mapping->FromField});
			$this->logger->debug("SQL: $sql$where");
			$this->logger->debug('Values: ' . implode(',', $properties));
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			if ($statement->execute($properties)) {
				if ($mapping->Relationship === DatabaseMapping::OneToOne || $mapping->Relationship === DatabaseMapping::ManyToOne) {
					$record = $statement->fetch();
					if ($record !== false) {
						$instance->{$property} = new $mapping->ObjectType($record);
					}
				} else {
					$results = array();
					while ($record = $statement->fetch()) {
						$results[] = new $mapping->ObjectType($record);
					}
					$instance->{$property} = $results;
				}
				$retVal = true;
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function rollbackTransaction() {
		$this->logger->debug('Rolling back database transaction.');
		$retVal = false;
		try {
			$retVal = $this->handle->rollBack();
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		if ($retVal === false) {
			$this->logger->error('Failed to roll back database transaction.');
		}
		return $retVal;
	}

	public function save($class, array &$properties) {
		if (empty($properties[$class::getPrimaryKeyName()])) {
			return $this->insert($class, $properties);
		} else {
			return $this->update($class, $properties);
		}
	}

	public function select($class, $id, $loadMappings = DatabaseRecord::MAPPINGS_NO_LAZY) {
		if (!is_subclass_of($class, '\CWA\MVC\Models\DatabaseRecord') || empty($id)) return null;
		$primaryKey = $class::getAlternateKeyName();
		$sql = "SELECT `$class`.* FROM `$class` WHERE $primaryKey = :$primaryKey";
		$this->logger->debug("SQL: $sql");

		$retVal = null;
		try {
			$this->statement = null;
			$statement = $this->handle->prepare($sql);
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			if ($statement->execute(array($primaryKey => $id))) {
				$record = $statement->fetch();
				if ($record !== false) {
					$class::setMappingLoader(array($this, 'loadMapping'));
					$retVal = new $class($record, $loadMappings);
				}
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function selectAll($class, $clauses, $parameters = null, $loadMappings = DatabaseRecord::MAPPINGS_NONE) {
		$retVal = null;
		try {
			$sql = "SELECT `$class`.* FROM `$class` $clauses";
			$this->logger->debug("SQL: $sql");
			$records = $this->fetchAll($sql, $parameters);
			if ($records !== false) {
				$objects = array();
				$class::setMappingLoader(array($this, 'loadMapping'));
				foreach ($records as $record) {
					$objects[] = new $class($record, $loadMappings);
				}
				$retVal = $objects;
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function selectRandom($class, $count = 1, $loadMappings = DatabaseRecord::MAPPINGS_NO_LAZY) {
		if (!is_subclass_of($class, '\CWA\MVC\Models\DatabaseRecord')) return null;
		$primaryKey = $class::getPrimaryKeyName();
		$sql = "SELECT `$class`.* FROM `$class` WHERE $primaryKey >= (SELECT FLOOR(MAX($primaryKey) * RAND()) FROM `$class`) ORDER BY $primaryKey LIMIT 1";
		$this->logger->debug("SQL: $sql");

		$retVal = null;
		try {
			$this->statement = null;
			$statement = $this->handle->prepare($sql);
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			if ($statement->execute()) {
				$record = $statement->fetch();
				if ($record !== false) {
					$class::setMappingLoader(array($this, 'loadMapping'));
					$retVal = new $class($record, $loadMappings);
				}
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

	public function update($class, array &$properties) {
		if (!is_subclass_of($class, '\CWA\MVC\Models\DatabaseRecord') || !is_array($properties)) return false;

		$primaryKey = $class::getPrimaryKeyName();
		if (empty($properties[$primaryKey])) return false;

		$now = date(\CWA\DB\DATETIME_PHP_TO_DB);
		$updatedField = $class::getUpdatedFieldName();
		if (isset($updatedField)) $properties[$updatedField] = $now;

		$mappingsToUpdate = array();
		$mappingValues = array();
		$mappings = $class::getDatabaseMappings();
		foreach ($mappings as $property => $mapping) {
			if (!isset($properties[$property])) continue;
			// OneToOne and ManyToOne records will be updated via the foreign key field.
			// No support for updating OneToMany records due to orphans. -- cwells
			if ($mapping->Relationship === DatabaseMapping::ManyToMany) {
				// Store this mapping instance in one array and its value (array of IDs) in another. -- cwells
				$mappingsToUpdate[$property] = $mapping;
				$mappingValues[$property] = array_filter($properties[$property]); // Remove empty values since they can't reference a DB record. -- cwells
			}
			unset($properties[$property]);
		}

		$sql = 'UPDATE `' . $class . '` SET';
		$fields = array_keys($properties);
		foreach ($fields as $field) {
			if ($field === $primaryKey) {
				continue;
			} else if (is_array($properties[$field])) {
				unset($properties[$field]);
				continue;
			}
			$sql .= " $field = :$field,";
		}
		$sql = substr($sql, 0, -1); // Trim the trailing comma.
		$sql .= " WHERE $primaryKey = :$primaryKey";
		$this->logger->debug("SQL: $sql");
		$this->logger->debug('Values: ' . implode(',', $properties));

		$retVal = false;
		try {
			$useTransaction = (count($mappingsToUpdate) > 0);
			if ($useTransaction && $this->beginTransaction() === false) {
				$this->logger->error('Failed to begin database transaction.');
				return false;
			}

			$this->statement = $this->handle->prepare($sql);
			$retVal = $this->statement->execute($properties);

			if ($retVal) {
				foreach ($mappingsToUpdate as $key => $mapping) {
					$numberOfRecords = count($mappingValues[$key]);
					$subvalues = array($properties[$mapping->FromField]); // Start the array with the object's ID. -- cwells
					foreach ($mappingValues[$key] as $item) { // Append each ID into the array. -- cwells
						$subvalues[] = (is_subclass_of($item, '\CWA\MVC\Models\DatabaseRecord') ? $item->getID() : $item);
					}

					$sql = 'DELETE FROM `' . $mapping->Table . '` WHERE ' . $mapping->ToField . ' = ?';
					if ($numberOfRecords !== 0) { // Do not delete records that should remain associated. -- cwells
						// Subtract 1 from the count for the extra '?' being added. -- cwells
						$sql .= ' AND ' . $mapping->Submappings[0]->FromField . ' NOT IN ('
								. str_repeat('?, ', $numberOfRecords - 1) . '?)';
					}
					$this->logger->debug("SQL: $sql");
					$this->logger->debug('Values: ' . implode(',', $subvalues));

					try {
						$this->statement = $this->handle->prepare($sql);
						$retVal = $this->statement->execute($subvalues);
					} catch (PDOException $e) {
						$retVal = false;
						$this->setLastError($e);
					}

					if ($retVal && $numberOfRecords !== 0) {
						// Subtract 1 from the count for the extra '(?, ?)' being added. -- cwells
						$sql = 'INSERT IGNORE INTO `' . $mapping->Table . '` (' . $mapping->ToField
								. ', ' . $mapping->Submappings[0]->FromField . ') VALUES '
								. str_repeat('(?, ?), ', $numberOfRecords - 1) . '(?, ?)';
						$subvalues = array();
						foreach ($mappingValues[$key] as $item) {
							// Save the value of the main object's ID and the mapped object's ID. -- cwells
							$subvalues[] = $properties[$mapping->FromField];
							$subvalues[] = (is_subclass_of($item, '\CWA\MVC\Models\DatabaseRecord') ? $item->getID() : $item);
						}
						$this->logger->debug("SQL: $sql");
						$this->logger->debug('Values: ' . implode(',', $subvalues));

						try {
							$this->statement = $this->handle->prepare($sql);
							$retVal = $this->statement->execute($subvalues);
						} catch (PDOException $e) {
							$retVal = false;
							$this->setLastError($e);
						}

						if ($retVal === false) {
							break;
						}
					}
				}
			}

			if ($useTransaction) {
				if ($retVal) {
					$retVal = $this->commitTransaction();
				} else {
					$this->rollbackTransaction();
				}
			}
		} catch (PDOException $e) {
			$this->setLastError($e);
		}
		return $retVal;
	}

}

?>
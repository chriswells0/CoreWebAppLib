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

use \CWA\MVC\Models\Model;

require_once \CWA\LIB_PATH . 'cwa/mvc/models/Model.php';

class DatabaseMapping extends Model
{
	/* Constants: */
	const OneToOne = 1;
	const OneToMany = 2;
	// From the perspective of a given object, ManyToOne and ManyToMany
	// are really just one (self) to whatever. -- cwells
	const ManyToOne = 1;
	const ManyToMany = 2;

	/* Constructor: */
	public function __construct($relationship, $fromField, $toField, array $submappings = null, $lazy = null) {
		if (is_null($lazy)) { // By default, lazy load "Many" items while loading "One" items immediately. -- cwells
			$lazy = ($relationship === self::OneToMany || $relationship === self::ManyToMany);
		}
		$table = explode('.', $toField);
		$table = $table[0];
		if (is_null($submappings)) {
			$objectType = $table;
		} else { // The main object type is the same as that of the last submapping. -- cwells
			$lastMapping = end($submappings);
			$objectType = $lastMapping->ObjectType;
		}
		if (is_null($fromField)) { // Could default to null, but makes the code awkward for complex mappings. -- cwells
			if ($relationship === self::OneToOne || $relationship === self::ManyToOne) {
				$fromField = $objectType . 'ID';
			} else {
				$fromField = 'ID';
			}
		}

		$properties = array(
			'Relationship' => $relationship,
			'FromField' => $fromField,
			'ToField' => $toField,
			'Table' => $table,
			'ObjectType' => $objectType,
			'Submappings' => $submappings,
			'Lazy' => $lazy
		);
		parent::__construct($properties);
	}
}

?>
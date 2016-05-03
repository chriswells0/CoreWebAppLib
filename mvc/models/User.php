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

require_once 'DatabaseRecord.php';

class User extends DatabaseRecord
{
	// The bcrypt cost (work factor). Always using 15 for now. The password
	// library defaults to 10 and requires between 4 and 31 inclusive.
	// The recommended minimum cost for bcrypt is 11 as of 2015. -- cwells
	const HASH_COST = 15;


	/* Protected variables: */

	protected $roleTypes = array();
	protected $loggedIn = false;


	/* Constructor: */
	public function __construct(array $properties = null, $loadMappings = DatabaseRecord::MAPPINGS_NO_LAZY) {
		parent::__construct($properties, $loadMappings);
		foreach ($this->Roles as $role) {
			$this->roleTypes[] = $role->Type;
		}
	}


	/* Public methods: */

	public function hasRole($desiredRoles) {
		if (!$this->loggedIn || empty($desiredRoles)) {
			return false;
		}

		if (!is_array($desiredRoles)) {
			return in_array($desiredRoles, $this->roleTypes);
		}

		foreach ($desiredRoles as $desiredRole) {
			if (in_array($desiredRole, $this->roleTypes)) {
				return true;
			}
		}
		return false;
	}

	public function isLoggedIn() {
		return $this->loggedIn;
	}

	public function setPassword($password) {
		if (!empty($password)) {
			if (!defined('PASSWORD_DEFAULT')) {
				require_once LIB_PATH . 'password_compat/password.php';
			}

			$hash = password_hash($password, PASSWORD_DEFAULT, array('cost' => self::HASH_COST));
			if ($hash !== false) {
				$this->PasswordHash = $hash;
				return true;
			}
		}
		return false;
	}

	public function verifyPassword($password) {
		if (!defined('PASSWORD_DEFAULT')) {
			require_once LIB_PATH . 'password_compat/password.php';
		}

		$this->loggedIn = (password_verify($password, $this->PasswordHash) === true);
		if ($this->loggedIn) { // Verify password hash algorithm and strength requirements. -- cwells
			$passwordInfo = password_get_info($this->PasswordHash);
			if (password_needs_rehash($this->PasswordHash, PASSWORD_DEFAULT, $passwordInfo['options']) === true
					|| $passwordInfo['options']['cost'] !== self::HASH_COST) {
				// No need to verify success here since we'll try again on the next login. -- cwells
				$this->setPassword($password);
			}
		}
		return $this->loggedIn;
	}
}
?>
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

use \CWA\DB\DatabaseException;
use \CWA\MVC\Controllers\InvalidArgumentException;

require_once \CWA\LIB_PATH . 'cwa/db/DatabaseException.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/controllers/Controller.php';
require_once \CWA\LIB_PATH . 'cwa/mvc/controllers/InvalidArgumentException.php';

abstract class DatabaseRecordController extends Controller
{
	/* Private variables: */


	/* Protected variables: */
	protected $adminLimit;
	protected $adminSort;
	protected $db;
	protected $indexClauses;
	protected $indexLimit;
	protected $indexSort;
	protected $pageLimit;


	/* Constructor: */
	public function __construct() {
		parent::__construct();

		$this->viewInfo['add']['template'] = 'form';
		$this->viewInfo['add']['title'] = "Add $this->modelType";
		$this->viewInfo['admin']['title'] = "{$this->modelType} Admin";
		$this->viewInfo['admin']['description'] = "{$this->modelType} Admin on " . DOMAIN . '.';
		$this->viewInfo['edit']['template'] = 'form';
		$this->viewInfo['edit']['title'] = "Edit $this->modelType";
		$this->viewInfo['index']['title'] = "{$this->modelType}s";
		$this->viewInfo['index']['description'] = "{$this->modelType}s on " . DOMAIN . '.';
		$this->viewInfo['page']['template'] = 'index';
		$this->viewInfo['page']['title'] = "{$this->modelType}s - Page {PageNumber}";
		$this->viewInfo['save']['title'] = "Save $this->modelType";
		$this->viewInfo['view']['title'] = $this->modelType;
		$this->viewInfo['view']['description'] = "$this->modelType on " . DOMAIN . '.';
		$this->viewInfo['view']['canonicalURL'] = PROTOCOL_HOST_PORT . "$this->pathInURL/view/{ID}";

		if (!is_subclass_of($this->modelType, '\CWA\MVC\Models\DatabaseRecord')) {
			throw new InvalidArgumentException("$this->modelType does not inherit from DatabaseRecord.", 500);
		}

		$this->db = $this->app->getDatabase();
		if (!isset($this->indexSort)) {
			$class = $this->modelType;
			$this->indexSort = $class::getUpdatedFieldName();
			if (is_null($this->indexSort)) $this->indexSort = $class::getCreatedFieldName();
			if (is_null($this->indexSort)) $this->indexSort = $class::getAlternateKeyName();
			$this->indexSort .= ' DESC';
		}
		if (!isset($this->indexLimit)) {
			$this->indexLimit = 10;
		}
		if (!isset($this->indexClauses)) {
			$this->indexClauses = "ORDER BY $this->indexSort";
		}
		if (!isset($this->pageLimit)) { // pageLimit defaults to the indexLimit. -- cwells
			$this->pageLimit = $this->indexLimit;
		}
		if (!isset($this->adminSort)) {
			$class = $this->modelType;
			$this->adminSort = $class::getAlternateKeyName();
			if (is_null($this->adminSort)) $this->adminSort = $class::getPrimaryKeyName();
		}
		if (!isset($this->adminLimit)) {
			$this->adminLimit = 25;
		}
	}


	/* Protected methods: */

	// Make public in a subclass to enable this method. -- cwells
	protected function index() {
		$limit = $this->indexLimit + 1; // Grab 1 extra so we know if there are more. -- cwells
		$items = $this->db->selectAll($this->modelType, "$this->indexClauses LIMIT $limit");
		if (is_null($items)) {
			throw new DatabaseException("Failed to retrieve $this->modelType objects.", 500);
		}
		parent::index();
		if (count($items) > $this->indexLimit) {
			$morePages = true;
			unset($items[$this->indexLimit]);
		}
		$this->view->setData(array($this->modelType . 'List' => $items,
									'PreviousPage' => '',
									'NextPage' => (empty($morePages) ? '' : "$this->pathInURL/page/2")));
	}

	protected function loadObject($itemID = null) {
		if (empty($itemID) || is_array($itemID)) {
			$item = new $this->modelType($itemID);
		} else {
			$item = $this->db->select($this->modelType, $itemID);
			if (is_null($item)) {
				throw new InvalidArgumentException("Failed to retrieve $this->modelType with key = $itemID.", 404);
			}
		}
		return $item;
	}


	/* Public methods: */

	public function add($properties = null) {
		$item = new $this->modelType($properties);
		$this->loadView('add');
		$this->view->setData($this->modelType, $item);
	}

	public function admin($pageNumber = null) {
		if (!isset($pageNumber)) {
			$pageNumber = 1;
		} else if (!is_numeric($pageNumber)) {
			throw new InvalidArgumentException('An invalid page number was specified.', 404);
		} else {
			$pageNumber = intval($pageNumber);
			if ($pageNumber < 2) {
				$this->app->redirect("$this->pathInURL/admin");
			}
		}

		// Begin at the number of already displayed items, which is really the next item since it's 0-based.
		// Subtract 1 to exclude the current page, which has not been displayed. -- cwells
		$begin = ($pageNumber - 1) * $this->adminLimit;
		$limit = $this->adminLimit + 1; // Grab 1 extra to determine if there are more. -- cwells
		$items = $this->db->selectAll($this->modelType, "ORDER BY $this->adminSort LIMIT $begin, $limit");
		if (is_null($items)) {
			throw new DatabaseException("Failed to retrieve $this->modelType objects.", 500);
		} else if (count($items) === 0 && $pageNumber !== 1) {
			throw new InvalidArgumentException('No items were found for the specified page number.', 404);
		}

		$this->loadView('admin');
		if ($pageNumber === 1) {
			$previousPage = '';
		} else if ($pageNumber === 2) {
			$previousPage = "$this->pathInURL/admin";
		} else {
			$previousPage = "$this->pathInURL/admin/" . ($pageNumber - 1);
		}
		if (count($items) > $this->adminLimit) {
			$morePages = true;
			unset($items[$this->adminLimit]); // Do not send the extra item to the view. -- cwells
		}
		$this->view->setData(array($this->modelType . 'List' => $items,
									'PageNumber' => $pageNumber,
									'PreviousPage' => $previousPage,
									'NextPage' => (empty($morePages) ? '' : "$this->pathInURL/admin/" . ($pageNumber + 1))));
	}

	public function delete($data) {
		if (empty($data)) {
			throw new InvalidArgumentException('You must specify the item to delete.', 400);
		} else if (!is_array($data)) {
			$this->view($data);
			$this->view->setStatus('You must enable JavaScript to delete.', 400);
		} else if (empty($data['itemID'])) {
			throw new InvalidArgumentException('You must specify the item to delete.', 400);
		} else {
			$itemID = $data['itemID'];
			if ($this->db->delete($this->modelType, $itemID)) {
				if (empty($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], "$this->pathInURL/view") !== false) {
					// No referrer or the referrer was the item being deleted, so the next URL is a list of similar items. -- cwells
					if ($this->app->userIsAuthorized(null, 'admin')) {
						$nextURL = "$this->pathInURL/admin";
					} else {
						$nextURL = $this->pathInURL;
					}
				} else { // This is typically a page that had this item as a sub-item. -- cwells
					$nextURL = $_SERVER['HTTP_REFERER'];
				}

				$this->loadView('add'); // Get a simple view with no item loaded. -- cwells
				$this->view->setStatus('Successfully deleted the specified item.');
				$this->view->setData('NextURL', $nextURL);
			} else {
				$this->view($itemID);
				$errorInfo = $this->db->getErrorInfo();
				if (count($errorInfo) > 2) {
					$this->view->setStatus($errorInfo[2], 500);
				} else {
					$this->view->setStatus('Error deleting the specified item.', 500);
				}
			}
		}
	}

	public function edit($itemID) {
		if (is_null($itemID)) {
			throw new InvalidArgumentException('You must specify the item to edit.', 400);
		}
		$item = $this->loadObject($itemID);
		$this->loadView('edit');
		$this->view->setData($this->modelType, $item);
	}

	public function page($pageNumber) {
		if (!isset($pageNumber)) {
			$this->app->redirect($this->pathInURL);
		} else if (!is_numeric($pageNumber)) {
			throw new InvalidArgumentException('An invalid page number was specified.', 404);
		} else {
			$pageNumber = intval($pageNumber);
			if ($pageNumber < 2) {
				$this->app->redirect($this->pathInURL);
			}
		}

		// Begin at the number of already displayed items, which is really the next item since it's 0-based.
		// Why minus 2?  We're separately excluding the index's limit and the current page. -- cwells
		$begin = $this->indexLimit + (($pageNumber - 2) * $this->pageLimit);
		$limit = $this->pageLimit + 1; // Grab 1 extra to determine if there are more. -- cwells
		$items = $this->db->selectAll($this->modelType, "$this->indexClauses LIMIT $begin, $limit");
		if (is_null($items)) {
			throw new DatabaseException("Failed to retrieve $this->modelType objects.", 500);
		} else if (count($items) === 0) {
			throw new InvalidArgumentException('No items were found for the specified page number.', 404);
		}

		$this->loadView('page');
		if (count($items) > $this->pageLimit) {
			$morePages = true;
			unset($items[$this->pageLimit]); // Do not send the extra item to the view. -- cwells
		}
		$this->view->setData(array($this->modelType . 'List' => $items,
									'PageNumber' => $pageNumber,
									'PreviousPage' => ($pageNumber === 2 ? $this->pathInURL : "$this->pathInURL/page/" . ($pageNumber - 1)),
									'NextPage' => (empty($morePages) ? '' : "$this->pathInURL/page/" . ($pageNumber + 1))));
	}

	public function save(array &$properties) {
		if (empty($properties)) {
			throw new InvalidArgumentException('You must provide the values to update.', 400);
		} else {
			$class = $this->modelType;
			if ($this->db->save($class, $properties)) {
				$this->edit($properties);
				$this->view->setStatus('Successfully saved the specified item.');
				$this->view->setData('NextURL', "$this->pathInURL/view/" . $properties[$class::getAlternateKeyName()]);
			} else {
				if (empty($properties[$class::getPrimaryKeyName()])) {
					$this->add($properties);
				} else {
					$this->edit($properties);
				}
				$errorInfo = $this->db->getErrorInfo();
				if (count($errorInfo) > 2) {
					$this->view->setStatus($errorInfo[2], 500);
				} else {
					$this->view->setStatus('Error saving the item.', 500);
				}
			}
		}
	}

	public function view($itemID) {
		if (is_null($itemID)) {
			throw new InvalidArgumentException('You must specify the item to view.', 400);
		}
		$item = $this->loadObject($itemID);
		$this->loadView('view');
		$this->view->setData($this->modelType, $item);
	}

}

?>
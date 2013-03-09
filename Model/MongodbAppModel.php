<?php
/**
 * Mongodb App Model
 *
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2013, DizyArt http://github.com/dizyart/
 * @link          http://cakephp.org
 * @package       Mongodb
 * @subpackage    Mongodb.Model
 * @since         Mongodb 2.0b
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 **/

App::uses('AppModel', 'Model');

/**
 * Application model for Mongodb.
 *
 * Adds some necessary adaptations for saving and retreiving data.
 *
 * @package       Mongodb.Model
 */
class MongodbAppModel extends AppModel {

/**
 * Adapter for Model::_saveMulti
 * 
 * Method is overriden because saving HABTM data structure cannot be properly recognized by
 * Model class. Specifically, the MongoId (or a string representation) is not recognized 
 * by cake as a valid ID type, hence skipping it in the ID check:
 * 
 *      if ((is_string($row) && (strlen($row) == 36 || strlen($row) == 16)) || is_numeric($row))
 *
 * @param array $joined Data to save
 * @param integer|string $id ID of record in this model
 * @param DataSource $db
 * @return void
 */
	protected function _saveMulti($joined, $id, $db) {
		foreach ($joined as $assoc => $data) {

			if (isset($this->hasAndBelongsToMany[$assoc])) {
				list($join) = $this->joinModel($this->hasAndBelongsToMany[$assoc]['with']);

				if ($with = $this->hasAndBelongsToMany[$assoc]['with']) {
					$withModel = is_array($with) ? key($with) : $with;
					list(, $withModel) = pluginSplit($withModel);
					$dbMulti = $this->{$withModel}->getDataSource();
				} else {
					$dbMulti = $db;
				}

				$isUUID = !empty($this->{$join}->primaryKey) && $this->{$join}->_isUUIDField($this->{$join}->primaryKey);

				$newData = $newValues = $newJoins = array();
				$primaryAdded = false;

				$fields = array(
					$dbMulti->name($this->hasAndBelongsToMany[$assoc]['foreignKey']),
					$dbMulti->name($this->hasAndBelongsToMany[$assoc]['associationForeignKey'])
				);

				$idField = $db->name($this->{$join}->primaryKey);
				if ($isUUID && !in_array($idField, $fields)) {
					$fields[] = $idField;
					$primaryAdded = true;
				}

				foreach ((array)$data as $row) {
					if ((is_string($row) && (strlen($row) == 36 || strlen($row) == 16)) || is_numeric($row)) {
						$newJoins[] = $row;
						$values = array($id, $row);
						if ($isUUID && $primaryAdded) {
							$values[] = String::uuid();
						}
						$newValues[$row] = $values;
						unset($values);
					} elseif (isset($row[$this->hasAndBelongsToMany[$assoc]['associationForeignKey']])) {
						if (!empty($row[$this->{$join}->primaryKey])) {
							$newJoins[] = $row[$this->hasAndBelongsToMany[$assoc]['associationForeignKey']];
						}
						$newData[] = $row;
					} elseif (isset($row[$join]) && isset($row[$join][$this->hasAndBelongsToMany[$assoc]['associationForeignKey']])) {
						if (!empty($row[$join][$this->{$join}->primaryKey])) {
							$newJoins[] = $row[$join][$this->hasAndBelongsToMany[$assoc]['associationForeignKey']];
						}
						$newData[] = $row[$join];
					}
				}

				$keepExisting = $this->hasAndBelongsToMany[$assoc]['unique'] === 'keepExisting';
				if ($this->hasAndBelongsToMany[$assoc]['unique']) {
					$conditions = array(
						$join . '.' . $this->hasAndBelongsToMany[$assoc]['foreignKey'] => $id
					);
					if (!empty($this->hasAndBelongsToMany[$assoc]['conditions'])) {
						$conditions = array_merge($conditions, (array)$this->hasAndBelongsToMany[$assoc]['conditions']);
					}
					$associationForeignKey = $this->{$join}->alias . '.' . $this->hasAndBelongsToMany[$assoc]['associationForeignKey'];
					$links = $this->{$join}->find('all', array(
						'conditions' => $conditions,
						'recursive' => empty($this->hasAndBelongsToMany[$assoc]['conditions']) ? -1 : 0,
						'fields' => $associationForeignKey,
					));

					$oldLinks = Hash::extract($links, "{n}.{$associationForeignKey}");
					if (!empty($oldLinks)) {
						if ($keepExisting && !empty($newJoins)) {
							$conditions[$associationForeignKey] = array_diff($oldLinks, $newJoins);
						} else {
							$conditions[$associationForeignKey] = $oldLinks;
						}
						$dbMulti->delete($this->{$join}, $conditions);
					}
				}

				if (!empty($newData)) {
					foreach ($newData as $data) {
						$data[$this->hasAndBelongsToMany[$assoc]['foreignKey']] = $id;
						if (empty($data[$this->{$join}->primaryKey])) {
							$this->{$join}->create();
						}
						$this->{$join}->save($data);
					}
				}

				if (!empty($newValues)) {
					if ($keepExisting && !empty($links)) {
						foreach ($links as $link) {
							$oldJoin = $link[$join][$this->hasAndBelongsToMany[$assoc]['associationForeignKey']];
							if (!in_array($oldJoin, $newJoins)) {
								$conditions[$associationForeignKey] = $oldJoin;
								$db->delete($this->{$join}, $conditions);
							} else {
								unset($newValues[$oldJoin]);
							}
						}
						$newValues = array_values($newValues);
					}
					if (!empty($newValues)) {
						$dbMulti->insertMulti($this->{$join}, $fields, $newValues);
					}
				}
			}
		}
	}
}

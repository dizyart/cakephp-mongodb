<?php
/**
 * A CakePHP datasource for the mongoDB (http://www.mongodb.org/) document-oriented database.
 *
 * This datasource uses Pecl Mongo (http://php.net/mongo)
 * and is thus dependent on PHP 5.0 and greater.
 *
 * Original implementation by ichikaway(Yasushi Ichikawa) http://github.com/ichikaway/
 *
 * Reference:
 *	Nate Abele's lithium mongoDB datasource (http://li3.rad-dev.org/)
 *	Joél Perras' divan(http://github.com/jperras/divan/)
 *
 * Copyright 2010, Yasushi Ichikawa http://github.com/ichikaway/
 *
 * Contributors: Predominant, Jrbasso, tkyk, AD7six, DizyArt
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2010, Yasushi Ichikawa http://github.com/ichikaway/
 * @package       mongodb
 * @subpackage    mongodb.models.datasources
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 * 
 * @todo Apply MongodbDource::conditions() method to all conditions instead of manual '$in' keying
 * @todo Query Sanitizing - all fields from associated models should be removed from the query before execution. There are no joins, so only overhead is created.
 * @todo Unify Schemaless behavior with field selection
 * @todo Recheck 'id' to '_id' translation
 * 
 */

App::uses('DboSource', 'Model/Datasource');
App::uses('SchemalessBehavior', 'Mongodb.Model/Behavior');
$path = App::path('Lib', 'Mongodb');
require_once($path[0] . DS . 'Error' . DS . 'exceptions.php');

/**
 * MongoDB Source
 *
 * @package       mongodb
 * @subpackage    mongodb.models.datasources
 */
class MongodbSource extends DboSource {

/**
 * Are we connected to the DataSource?
 *
 * true - yes
 * null - haven't tried yet
 * false - nope, and we can't connect
 *
 * @var boolean
 * @access public
 */
	public $connected = null;

/**
 * Database Instance
 *
 * @var resource
 * @access protected
 */
	protected $_db = null;

/**
 * Mongo Driver Version
 *
 * @var string
 * @access protected
 */
	protected $_driverVersion = Mongo::VERSION;

/**
 * startTime property
 *
 * If debugging is enabled, stores the (micro)time the current query started
 *
 * @var mixed null
 * @access protected
 */
	protected $_startTime = null;

/**
 * Direct connection with database, isn't the
 * same of DboSource::_connection
 *
 * @var mixed null | Mongo
 * @access private
 */
	public $connection = null;

/**
 * Base Config
 *
 * set_string_id:
 *    true: In read() method, convert MongoId object to string and set it to array 'id'.
 *    false: not convert and set.
 *
 * @var array
 * @access public
 *
 */
	public $_baseConfig = array(
		'set_string_id' => true,
		'persistent' => true,
		'host'       => 'localhost',
		'database'   => '',
		'port'       => '27017',
		'login'		=> '',
		'password'	=> '',
		'replicaset'	=> '',
	);

/**
 * column definition
 *
 * @var array
 */
	public $columns = array(
		'boolean' => array('name' => 'boolean'),
		'string' => array('name'  => 'varchar'),
		'text' => array('name' => 'text'),
		'integer' => array('name' => 'integer', 'format' => null, 'formatter' => 'intval'),
		'float' => array('name' => 'float', 'format' => null, 'formatter' => 'floatval'),
		'datetime' => array('name' => 'datetime', 'format' => null, 'formatter' => array('MongodbSource','dateFormatter')),
		'timestamp' => array('name' => 'timestamp', 'format' => null, 'formatter' => array('MongodbSource','dateFormatter')),
		'time' => array('name' => 'time', 'format' => null, 'formatter' => array('MongodbSource','dateFormatter')),
		'date' => array('name' => 'date', 'format' => null, 'formatter' => array('MongodbSource','dateFormatter'))
	);

/**
 * Default schema for the mongo models
 *
 * @var array
 * @access protected
 */
	protected $_defaultSchema = array(
		'_id' => array('type' => 'string', 'length' => 24, 'key' => 'primary'),
		'created' => array('type' => 'datetime', 'default' => null),
		'modified' => array('type' => 'datetime', 'default' => null)
	);

/**
 * construct method
 *
 * By default don't try to connect until you need to
 *
 * @param array $config Configuration array
 * @param bool $autoConnect false
 * @return void
 * @access public
 */
	function __construct($config = array(), $autoConnect = false) {
		return parent::__construct($config, $autoConnect);
	}

/**
 * Destruct
 *
 * @access public
 */
	public function __destruct() {
		if ($this->connected) {
			$this->disconnect();
		}
	}

/**
 * commit method
 *
 * MongoDB doesn't support transactions
 *
 * @return void
 * @access public
 */
	public function commit() {
		return false;
	}

/**
 * Connect to the database
 *
 * If using 1.0.2 or above use the mongodb:// format to connect
 * The connect syntax changed in version 1.0.2 - so check for that too
 *
 * If authentication information in present then authenticate the connection
 *
 * @return boolean Connected
 * @access public
 * @todo Wrap the connection arguments around a parser function.
 * @todo Enable all connection options to be passed at config.
 */
	public function connect() {
		$this->connected = false;

		try{

			$host = $this->createConnectionName($this->config, $this->_driverVersion);
            
            if (isset($this->config['replicaset']) && count($this->config['replicaset']) === 2) {
				$this->connection = new Mongo($this->config['replicaset']['host'], $this->config['replicaset']['options']);
            } else if ($this->_driverVersion >= '1.3.0') {
                $this->connection = new MongoClient($host); // presist option was removed, issues a notice in tests on 5.4
			} else if ($this->_driverVersion >= '1.2.0') {
                $this->connection = new Mongo($host, array("persist" => $this->config['persistent']));
			} else {
				$this->connection = new Mongo($host, true, $this->config['persistent']);
			}

			if (isset($this->config['slaveok'])) {
				$this->connection->setSlaveOkay($this->config['slaveok']);
			}
            $this->_db = $this->connection->selectDB($this->config['database']);
			if ($this->_db) {
				if (!empty($this->config['login']) && $this->_driverVersion < '1.2.0') {
					$return = $this->_db->authenticate($this->config['login'], $this->config['password']);
					if (!$return || !$return['ok']) {
						trigger_error('MongodbSource::connect ' . $return['errmsg']);
						return false;
					}
				}
				$this->connected = true;
			}

		} catch(MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		return $this->connected;
	}

/**
 * create connection name.
 *
 * @param array $config
 * @param string $version  version of MongoDriver
 */
    public function createConnectionName($config, $version) {
        $host = null;

        if ($version >= '1.0.2') {
            $host = "mongodb://";
        } 
        else {
            $host = '';
        }
        $hostname = $config['host'] . ':' . $config['port'];

        if(!empty($config['login'])){
            $host .= $config['login'] .':'. $config['password'] . '@' . $hostname . '/'. $config['database'];
        } 
        else {
            $host .= $hostname;
        }

        return $host;
    }


/**
 * Inserts multiple values into a table
 *
 * @param string $table
 * @param string $fields
 * @param array $values
 * @access public
 */
	public function insertMulti($table, $fields, $values) {
		$table = $this->fullTableName($table);
        
        if (!is_array($fields) || !is_array($values)) {
			return false;
		}

		$inUse = array_search('id', $fields);
		$default = array_search('_id', $fields);

		if($inUse !== false && $default === false) {
			$fields[$inUse] = '_id';
		}

		$data = array();
		foreach($values as $row) {
			if (is_string($row)) {
				$row = explode(', ', substr($row, 1, -1));
			}
			$data[] = array_combine($fields, $row);
		}
		$this->_prepareLogQuery($table); // just sets a timer
        
        try{
            if ($this->_driverVersion >= '1.3.0') {
				$return = $this->_db
				->selectCollection($table)
				->batchInsert($data, array('safe' => true));
			} else {
                $return = $this->_db
				->selectCollection($table)
				->batchInsert($data, true);
			}
            
			
		} catch (Exception $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		if ($this->fullDebug) {
			$this->logQuery("db.{$table}.insertMulti( :data , array('safe' => true))", compact('data'));
		}
	}

/**
 * check connection to the database
 *
 * @return boolean Connected
 * @access public
 */
	public function isConnected() {
		if ($this->connected === false) {
			return false;
		}
		return $this->connect();
	}

/**
 * get MongoDB Object
 *
 * @return mixed MongoDB Object
 * @access public
 */
	public function getMongoDb($autoconnect = false) {
        if ($this->connected === null && $autoconnect){
            $this->connect();
        }
        return $this->_db;
	}
    
/**
 * Get the underlying connection object. 
 *
 * @param boolean $autoconnect Try establishing a connection if possible.
 * @return PDO
 */
    public function getConnection($autoconnect = false) {
        if ($this->connected === false) {
            return false;
        }
        elseif ($this->connected === null && $autoconnect){
            $this->connect();
        }
        return $this->connection;
    }
/**
 * get MongoDB Collection Object
 *
 * @return mixed MongoDB Collection Object
 * @access public
 */
	public function getMongoCollection(Model $Model) {
		if ($this->connected === false) {
			return false;
		}

		$collection = $this->_db
			->selectCollection($Model->table);
		return $collection;
	}

/**
 * isInterfaceSupported method
 *
 * listSources is infact supported, however: cake expects it to return a complete list of all
 * possible sources in the selected db - the possible list of collections is infinte, so it's
 * faster and simpler to tell cake that the interface is /not/ supported so it assumes that
 * <insert name of your table here> exists
 * 
 * This function was removed from DataSource class in Cake 2.2.0
 *
 * @param mixed $interface
 * @return void
 * @access public
 * 
 */
	public function isInterfaceSupported($interface) {
		if ($interface === 'listSources') {
			return false;
		}
		return parent::isInterfaceSupported($interface);
	}

/**
 * Close database connection
 *
 * @return boolean Connected
 * @access public
 */
	public function close() {
		return $this->disconnect();
	}

/**
 * Disconnect from the database
 *
 * @return boolean Connected
 * @access public
 */
	public function disconnect() {
		if ($this->connected) {
			$this->connected = !$this->connection->close();
			unset($this->_db, $this->connection);
			return !$this->connected;
		}
		return true;
	}

/**
 * Get list of available Collections
 *
 * @param array $data
 * @return array Collections
 * @access public
 */
    public function listSources($data = null) {
		if (!$this->isConnected()) {
			return false;
		}
		return true;
	}

/**
 * Describe
 *
 * Automatically bind the schemaless behavior if there is no explicit mongo schema.
 * When called, if there is model data it will be used to derive a schema. a row is plucked
 * out of the db and the data obtained used to derive the schema.
 *
 * @param Model $Model
 * @return array if model instance has mongoSchema, return it.
 * @access public
 */
	public function describe($Model) {
		if(empty($Model->primaryKey)) {
			$Model->primaryKey = '_id';
		}

		$schema = array();
		if (!empty($Model->mongoSchema) && is_array($Model->mongoSchema)) {
			$schema = $Model->mongoSchema;
			return $schema + array($Model->primaryKey => $this->_defaultSchema['_id']);
		} 
        elseif ($this->isConnected() && is_a($Model, 'Model') && !empty($Model->Behaviors)) {
			$Model->Behaviors->attach('Mongodb.Schemaless');
			if (!$Model->data) {
				if ($this->_db->selectCollection($Model->table)->count()) {
					return $this->deriveSchemaFromData($Model, $this->_db->selectCollection($Model->table)->findOne());
				}
			}
		}
		$return = $this->deriveSchemaFromData($Model);
        return $return;
	}

/**
 * begin method
 *
 * Mongo doesn't support transactions
 *
 * @return void
 * @access public
 */
	public function begin() {
		return false;
	}

/**
 * Calculate
 *
 * @param Model $Model
 * @return array
 * @access public
 */
	public function calculate(Model $Model, $func, $params = array()) {
		return array('count' => true);
	}

/**
 * Quotes identifiers.
 *
 * MongoDb does not need identifiers quoted, so this method simply returns the identifier.
 *
 * @param string $name The identifier to quote.
 * @return string The quoted identifier.
 */
	public function name($name) {
		return $name;
	}

/**
 * Create Data
 *
 * @param Model $Model Model Instance
 * @param array $fields Field data
 * @param array $values Save data
 * @return boolean Insert result
 * @access public
 */
	public function create(Model $Model, $fields = null, $values = null) {
		if (!$this->isConnected()) {
			return false;
		}

		if ($fields !== null && $values !== null) {
			$data = array_combine($fields, $values);
		} else {
			$data = $Model->data;
		}

		if($Model->primaryKey !== '_id' && isset($data[$Model->primaryKey]) && !empty($data[$Model->primaryKey])) {
			$data['_id'] = $data[$Model->primaryKey];
			unset($data[$Model->primaryKey]);
		}

		if (!empty($data['_id'])) {
			$this->_convertId($data['_id']);
		}

		$this->_prepareLogQuery($Model); // just sets a timer
		try{
			if ($this->_driverVersion >= '1.3.0') {
				$return = $this->_db
					->selectCollection($Model->table)
					->insert($data, array('safe' => true));
			} else {
				$return = $this->_db
					->selectCollection($Model->table)
					->insert($data, true);
			}
		} catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		if ($this->fullDebug) {
			$this->logQuery("db.{$Model->useTable}.insert( :data , true)", compact('data'));
		}

		if (!empty($return) && $return['ok']) {

			$id = $data['_id'];
			if($this->config['set_string_id'] && is_object($data['_id'])) {
				$id = $data['_id']->__toString();
			}
			$Model->setInsertID($id);
			$Model->id = $id;
			return true;
		}
		return false;
	}

/**
 * createSchema method
 *
 * Mongo no care for creating schema. Mongo work with no schema.
 *
 * @param mixed $schema
 * @param mixed $tableName null
 * @return void
 * @access public
 */
	public function createSchema($schema, $tableName = null) {
		return true;
	}

/**
 * dropSchema method
 *
 * Return a command to drop each table
 *
 * @param mixed $schema
 * @param mixed $tableName null
 * @return void
 * @access public
 */
	public function dropSchema(CakeSchema $schema, $tableName = null) {
		if (!$this->isConnected()) {
			return false;
		}

		if (!is_a($schema, 'CakeSchema')) {
			trigger_error(__('Invalid schema object', true), E_USER_WARNING);
			return null;
		}
		if ($tableName) {
			return "db.{$tableName}.drop();";
		}

		$toDrop = array();
		foreach ($schema->tables as $curTable => $columns) {
			if ($tableName === $curTable) {
				$toDrop[] = $curTable;
			}
		}

		if (count($toDrop) === 1) {
			return "db.{$toDrop[0]}.drop();";
		}

		$return = "toDrop = :tables;\nfor( i = 0; i < toDrop.length; i++ ) {\n\tdb[toDrop[i]].drop();\n}";
		$tables = '["' . implode($toDrop, '", "') . '"]';

		return String::insert($return, compact('tables'));
	}

/**
 * distinct method
 *
 * @param mixed $Model
 * @param array $keys array()
 * @param array $params array()
 * @return void
 * @access public
 */
	public function distinct(Model $Model, $keys = array(), $params = array()) {
		if (!$this->isConnected()) {
			return false;
		}

		$this->_prepareLogQuery($Model); // just sets a timer

		if (array_key_exists('conditions', $params)) {
			$params = $params['conditions'];
		}
		try{
			$return = $this->_db
				->selectCollection($Model->table)
				->distinct($keys, $params);
		} 
        catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		if ($this->fullDebug) {
			$this->logQuery("db.{$Model->table}.distinct( :keys, :params )", compact('keys', 'params'));
		}

		return $return;
	}


/**
 * group method
 *
 * @param array $params array()
 *   Set params  same as MongoCollection::group()
 *    key,initial, reduce, options(conditions, finalize)
 *
 *   Ex. $params = array(
 *           'key' => array('field' => true),
 *           'initial' => array('csum' => 0),
 *           'reduce' => 'function(obj, prev){prev.csum += 1;}',
 *           'options' => array(
 *                'condition' => array('age' => array('$gt' => 20)),
 *                'finalize' => array(),
 *           ),
 *       );
 * @param mixed $Model
 * @return void
 * @access public
 */
	public function group($params, $Model = null) {

		if (!$this->isConnected() || count($params) === 0 || $Model === null) {
			return false;
		}
        if (is_object($params) && is_array($Model)) {
            //Backwards compatibility
            list($params, $Model) = array($Model, $params);
        }

		$this->_prepareLogQuery($Model); // just sets a timer

		$key = (empty($params['key'])) ? array() : $params['key'];
		$initial = (empty($params['initial'])) ? array() : $params['initial'];
		$reduce = (empty($params['reduce'])) ? array() : $params['reduce'];
		$options = (empty($params['options'])) ? array() : $params['options'];

		try{
			$return = $this->_db
				->selectCollection($Model->table)
				->group($key, $initial, $reduce, $options);
		} catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		if ($this->fullDebug) {
			$this->logQuery("db.{$Model->useTable}.group( :key, :initial, :reduce, :options )", $params);
		}

		return $return;
	}


/**
 * ensureIndex method
 *
 * @param mixed $Model
 * @param array $keys array()
 * @param array $params array()
 * @return void
 * @access public
 */
	public function ensureIndex(Model $Model, $keys = array(), $params = array()) {
		if (!$this->isConnected()) {
			return false;
		}

		$this->_prepareLogQuery($Model); // just sets a timer

		try{
			$return = $this->_db
				->selectCollection($Model->table)
				->ensureIndex($keys, $params);
		} catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		if ($this->fullDebug) {
			$this->logQuery("db.{$Model->useTable}.ensureIndex( :keys, :params )", compact('keys', 'params'));
		}

		return $return;
	}

/**
 * Update Data
 *
 * This method uses $set operator automatically with MongoCollection::update().
 * If you don't want to use $set operator, you can chose any one as follw.
 *  1. Set TRUE in Model::mongoNoSetOperator property.
 *  2. Set a mongodb operator in a key of save data as follow.
 *      Model->save(array('_id' => $id, '$inc' => array('count' => 1)));
 *      Don't use Model::mongoSchema property,
 *       CakePHP delete '$inc' data in Model::Save().
 *  3. Set a Mongo operator in Model::mongoNoSetOperator property.
 *      Model->mongoNoSetOperator = '$inc';
 *      Model->save(array('_id' => $id, array('count' => 1)));
 *
 * @param Model $Model Model Instance
 * @param array $fields Field data
 * @param array $values Save data
 * @return boolean Update result
 * @access public
 */
	public function update(Model $Model, $fields = null, $values = null, $conditions = null) {
		if (!$this->isConnected()) {
			return false;
		}

		if ($fields !== null && $values !== null) {
			$data = array_combine($fields, $values);
		} elseif($fields !== null && $conditions !== null) {
			return $this->updateAll($Model, $fields, $conditions);
		} else{
			$data = $Model->data;
		}

		if($Model->primaryKey !== '_id' && isset($data[$Model->primaryKey]) && !empty($data[$Model->primaryKey])) {
			$data['_id'] = $data[$Model->primaryKey];
			unset($data[$Model->primaryKey]);
		}

		if (empty($data['_id'])) {
			$data['_id'] = $Model->id;
		}

		$this->_convertId($data['_id']);

		try{
			$mongoCollectionObj = $this->_db
				->selectCollection($Model->table);
		} catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
			return false;
		}

		$this->_prepareLogQuery($Model); // just sets a timer
		if (!empty($data['_id'])) {
			$this->_convertId($data['_id']);
			$cond = array('_id' => $data['_id']);
			unset($data['_id']);

			$data = $this->setMongoUpdateOperator($Model, $data);

			try{
				if ($this->_driverVersion >= '1.3.0') {
					$return = $mongoCollectionObj->update($cond, $data, array("multiple" => false, 'safe' => true));
				} else {
					$return = $mongoCollectionObj->update($cond, $data, array("multiple" => false));
				}
			} catch (MongoException $e) {
				$this->error = $e->getMessage();
				trigger_error($this->error);
			}
			if ($this->fullDebug) {
				$this->logQuery("db.{$Model->useTable}.update( :conditions, :data, :params )",
					array('conditions' => $cond, 'data' => $data, 'params' => array("multiple" => false))
				);
			}
		} else {
			try{
				if ($this->_driverVersion >= '1.3.0') {
					$return = $mongoCollectionObj->save($data, array('safe' => true));
				} else {
					$return = $mongoCollectionObj->save($data);
				}
			} catch (MongoException $e) {
				$this->error = $e->getMessage();
				trigger_error($this->error);
			}
			if ($this->fullDebug) {
				$this->logQuery("db.{$Model->useTable}.save( :data )", compact('data'));
			}
		}
		return $return;
	}


/**
 * setMongoUpdateOperator
 *
 * Set Mongo update operator following saving data.
 * This method is for update() and updateAll.
 *
 * @param Model $Model Model Instance
 * @param array $values Save data
 * @return array $data
 * @access public
 */
	public function setMongoUpdateOperator(Model $Model, $data) {
		if(isset($data['updated'])) {
			$updateField = 'updated';
		} else {
			$updateField = 'modified';
		}

		//setting Mongo operator
		if(empty($Model->mongoNoSetOperator)) {
			if(!preg_grep('/^\$/', array_keys($data))) {
				$data = array('$set' => $data);
			} 
            else {
				if(!empty($data[$updateField])) {
					$modified = $data[$updateField];
					unset($data[$updateField]);
					$data['$set'] = array($updateField => $modified);
				}
			}
		} 
        elseif(substr($Model->mongoNoSetOperator,0,1) === '$') {
			if(!empty($data[$updateField])) {
				$modified = $data[$updateField];
				unset($data[$updateField]);
				$data = array($Model->mongoNoSetOperator => $data, '$set' => array($updateField => $modified));
			} 
            else {
				$data = array($Model->mongoNoSetOperator => $data);
			}
		}

		return $data;
	}

/**
 * Update multiple Record
 *
 * @param Model $Model Model Instance
 * @param array $fields Field data
 * @param array $conditions
 * @return boolean Update result
 * @access public
 */
	public function updateAll(Model $Model, $fields = null,  $conditions = null) {
		if (!$this->isConnected()) {
			return false;
		}

		$this->_stripAlias($conditions, $Model->alias);
		$this->_stripAlias($fields, $Model->alias, false, 'value');

		$fields = $this->setMongoUpdateOperator($Model, $fields);

		$this->_prepareLogQuery($Model); // just sets a timer
		try{
			if ($this->_driverVersion >= '1.3.0') {
				// not use 'upsert'
				$return = $this->_db
					->selectCollection($Model->table)
					->update($conditions, $fields, array("multiple" => true, 'safe' => true));
				if (isset($return['updatedExisting'])) {
					$return = $return['updatedExisting'];
				}
			} else {
				$return = $this->_db
					->selectCollection($Model->table)
					->update($conditions, $fields, array("multiple" => true));
			}
		} catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}

		if ($this->fullDebug) {
			$this->logQuery("db.{$Model->useTable}.update( :conditions, :fields, :params )",
				array('conditions' => $conditions, 'fields' => $fields, 'params' => array("multiple" => true))
			);
		}
		return $return;
	}

/**
 * deriveSchemaFromData method
 *
 * @param mixed $Model
 * @param array $data array()
 * @return void
 * @access public
 */
	public function deriveSchemaFromData($Model, $data = array()) {
		if (!$data) {
			$data = $Model->data;
			if ($data && array_key_exists($Model->alias, $data)) {
				$data = $data[$Model->alias];
			}
		}

		$return = $this->_defaultSchema;

		if ($data) {
			$fields = array_keys($data);
			foreach($fields as $field) {
				if (in_array($field, array('created', 'modified', 'updated'))) {
					$return[$field] = array('type' => 'datetime', 'null' => true);
				} else {
					$return[$field] = array('type' => 'string', 'length' => 2000);
				}
			}
		}

		return $return;
	}

/**
 * Delete Data
 *
 * For deleteAll(true, false) calls - conditions will arrive here as true - account for that and
 * convert to an empty array
 * For deleteAll(array('some conditions')) calls - conditions will arrive here as:
 *  array(
 *  	Alias._id => array(1, 2, 3, ...)
 *  )
 *
 * This format won't be understood by mongodb, it'll find 0 rows. convert to:
 *
 *  array(
 *  	Alias._id => array('$in' => array(1, 2, 3, ...))
 *  )
 * @todo Move all $in => array() transformations to MongodbSource::conditions() method
 * @todo bench remove() v drop. if it's faster to drop - just drop the collection taking into
 *  	account existing indexes (recreate just the indexes)
 * @param Model $Model Model Instance
 * @param array $conditions
 * @return boolean Update result
 * @access public
 */
	public function delete(Model $Model, $conditions = null) {
		if (!$this->isConnected()) {
			return false;
		}

		$id = null;

		$this->_stripAlias($conditions, $Model->alias);

		if ($conditions === true) {
			$conditions = array();
		} elseif (empty($conditions)) {
			$id = $Model->id;
		} elseif (!empty($conditions) && !is_array($conditions)) {
			$id = $conditions;
			$conditions = array();
		} elseif (!empty($conditions['id'])) { //for cakephp2.0
			$id = $conditions['id'];
			unset($conditions['id']);
		}
        
        $conditions = $this->conditions($conditions);

		$mongoCollectionObj = $this->_db
			->selectCollection($Model->table);

		$this->_stripAlias($conditions, $Model->alias);
		if (!empty($id)) {
			$conditions['_id'] = $id;
		}
		if (!empty($conditions['_id'])) {
			$this->_convertId($conditions['_id'], true);
		}

		$return = false;
		$r = false;
		try{
			$this->_prepareLogQuery($Model); // just sets a timer
			$return = $mongoCollectionObj->remove($conditions);
			if ($this->fullDebug) {
				$this->logQuery("db.{$Model->useTable}.remove( :conditions )",
					compact('conditions')
				);
			}
			$return = true;
		} catch (MongoException $e) {
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		return $return;
	}

/**
 * Read Data
 *
 * For deleteAll(true) calls - the conditions will arrive here as true - account for that and switch to an empty array
 *
 * @param Model $model Model Instance
 * @param array $queryData Query data
 * @param mixed  $recursive
 * @return array Results
 * @access public
 * @todo refactor this snake
 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		if (!$this->isConnected()) {
			return false;
		}

		$this->_setEmptyValues($queryData);
		extract($queryData);
        if (!empty($fields) && is_string($fields)) { 
            $fields = array($fields); 
        }

		if (!empty($order[0])) {
			$order = array_shift($order);
		}
		$this->_stripAlias($conditions, $model->alias);
		$this->_stripAlias($fields, $model->alias, false, 'value');
		$this->_stripAlias($order, $model->alias, false, 'both');

		if(!empty($conditions[$model->primaryKey]) && empty($conditions['_id'])) {
			$conditions['_id'] = $conditions[$model->primaryKey];
			unset($conditions[$model->primaryKey]);
		}

		if (!empty($conditions['_id'])) {
			$this->_convertId($conditions['_id']);
		}

		$fields = (is_array($fields)) ? $fields : array($fields => 1);
		if ($conditions === true) {
			$conditions = array();
		} elseif (!is_array($conditions)) {
			$conditions = array($conditions);
		}
		$order = (is_array($order)) ? $order : array($order);

		if (is_array($order)) {
			foreach($order as $field => &$dir) {
				if (is_numeric($field) || is_null($dir)) {
					unset ($order[$field]);
					continue;
				}
				if ($dir && strtoupper($dir) === 'ASC') {
					$dir = 1;
					continue;
				} elseif (!$dir || strtoupper($dir) === 'DESC') {
					$dir = -1;
					continue;
				}
				$dir = (int)$dir;
			}
		}

		if (empty($offset) && $page && $limit) {
			$offset = ($page - 1) * $limit;
		}

		$resultSet = array();

		$this->_prepareLogQuery($model); // just sets a timer
		if (empty($modify)) {
			if ($model->findQueryType === 'count' && $fields == array('count' => true)) {
                /** @refactored in 2.0b */
                return $this->_queryCount($model, $conditions);
				
			}
            if (empty($model->table)) {
                return null;
                throw new MongodbException(sprintf("Model::\$table cannot be empty (%s)", get_class($model)));
            }
            try {
			$resultSet = $this->_db
				->selectCollection($model->table)
				->find($conditions, $fields)
				->sort($order)
				->limit($limit)
				->skip($offset);
            } catch (MongoException $e) {
                $this->error = $e->getMessage();
                trigger_error($this->error);
            }
            
			if ($this->fullDebug) {
				$count = $resultSet->count(true);
                /** @todo change $Model->useTable to $Model->table? */
				$this->logQuery("db.{$model->useTable}.find( :conditions, :fields ).sort( :order ).limit( :limit ).skip( :offset )",
					compact('conditions', 'fields', 'order', 'limit', 'offset', 'count')
				);
			}
		} 
        else {
			$options = array_filter(array(
				'findandmodify' => $model->table,
				'query' => $conditions,
				'sort' => $order,
				'remove' => !empty($remove),
				'update' => $this->setMongoUpdateOperator($model, $modify),
				'new' => !empty($new),
				'fields' => $fields,
				'upsert' => !empty($upsert)
			));
			$resultSet = $this->_db
				->command($options);
			if ($this->fullDebug) {
				if ($resultSet['ok']) {
					$count = 1;
					if ($this->config['set_string_id'] && !empty($resultSet['value']['_id']) && $resultSet['value']['_id'] instanceof MongoId) {
						$resultSet['value']['_id'] = (string) $resultSet['value']['_id'];
					}
					$resultSet = array( array($model->alias => $resultSet['value']) );
				} else {
					$count = 0;
				}
				$this->logQuery("db.runCommand( :options )",
					array('options' => array_filter($options), 'count' => $count)
				);
			}
		}

		if (is_object($resultSet)) {
            
            /** @var array Temporary result set */
            $_resultSet = array();
			foreach ($resultSet as $idstring => $resultRecord) {
                if (!empty($resultRecord['_id'])){
                    if ($this->config['set_string_id'] && $resultRecord['_id'] instanceof MongoId) {
                        $resultRecord['_id'] = (string) $resultRecord['_id']; //typecast instead of __toString();
                    }
                    if ($model->primaryKey !== '_id') {
                        $resultRecord[$model->primaryKey] = $resultRecord['_id'];
                        unset($resultRecord['_id']);
                    }
                }
				$_resultSet[][$model->alias] = $resultRecord;
			}
            unset($resultSet); //clear the Cursor from memory
			$resultSet = $_resultSet;
		}


		/**
         * Association retrieval
         * 
         * @since 2.0b (taken from @ichikaway/relation branch)
         */
		if ($recursive === null && isset($queryData['recursive'])) {
			$recursive = $queryData['recursive'];
		}

		if (!is_null($recursive)) {
			if(is_array($recursive) && count($recursive) === 0) {
				$recursive = 0;
			}
			$_recursive = $model->recursive;
			$model->recursive = $recursive;
		}
        
        $_associations = $model->getAssociated();

		if ($model->recursive == -1) {
			$_associations = array();
		} 
        else if ($model->recursive == 0) {
			unset($_associations[2], $_associations[3]);
		}

		if ($model->recursive > -1) {
            $linkedModels = array();
			foreach ($_associations as $type) {
				foreach ($model->{$type} as $association => $assocData) {
					$linkModel = $model->{$association};
                    $external = isset($assocData['external']);

					//get id list for conditions
					$idList = array();
					$keyName = $model->primaryKey;
					if($type === 'belongsTo') {
						$keyName = $assocData['foreignKey'];
					}
					foreach($resultSet as $modelData) {
						if(!empty($modelData[$model->alias]) && !empty($modelData[$model->alias][$keyName])) {
							$idList[] = $modelData[$model->alias][$keyName];
						}
					}
                    
                    if (!isset($linkedModels[$type . '/' . $association])) {
						if ($model->useDbConfig === $linkModel->useDbConfig) {
							$db = $this;
						} 
                        else {
							$db = ConnectionManager::getDataSource($linkModel->useDbConfig);
						}
					} 
                    elseif ($model->recursive > 1 && ($type === 'belongsTo' || $type === 'hasOne')) {
						$db = $this;
					}
                    
                    if (isset($db) && method_exists($db, 'queryAssociation')) {
                        $stack = array($association);
						/** @todo $stack['_joined'] = $joined; **/
                        
                        $db->queryAssociation($model, $linkModel, $type, $association, $assocData, $queryData, $external, $resultSet, $model->recursive-1, $stack);
                        unset($db);
                    }
				}
			}
		}


		if (!is_null($recursive)) {
			$model->recursive = $_recursive;
		}

		return $resultSet;
	}
    
/**
 * rollback method
 *
 * MongoDB doesn't support transactions
 *
 * @return void
 * @access public
 */
	public function rollback() {
		return false;
	}

/**
 * Deletes all the records in a table
 *
 * @param mixed $table A string or model class representing the table to be truncated
 * @return boolean
 * @access public
 */
	public function truncate($table) {
		if (!$this->isConnected()) {
			return false;
		}

		return $this->execute('db.' . $this->fullTableName($table) . '.remove();');
	}

/**
 * query method
 *  If getMongoDb() is called from model, this method returns getMongoDb().
 *
 * @param mixed $query
 * @param array $params array()
 * @param Model $Model
 * @return void
 * @access public
 */
	public function query() {
        $args = func_get_args();
		if (!$this->isConnected() || count($args) == 0) {
			return false;
		}
        
        $query = $args[0];
        $params = (count($args) > 1) ? $args[1] : array();
        $Model = (count($args) > 2) ? $args[2] : null;

		if($query === 'getMongoDb') {
			return $this->getMongoDb();
		}

		if (count($args) > 1 && (strpos($args[0], 'findBy') === 0 || strpos($args[0], 'findAllBy') === 0)) {
			$params = $args[1];

			if (substr($args[0], 0, 6) === 'findBy') {
				$field = Inflector::underscore(substr($args[0], 6));
				return $args[2]->find('first', array('conditions' => array($field => $args[1][0])));
			} else{
				$field = Inflector::underscore(substr($args[0], 9));
				return $args[2]->find('all', array('conditions' => array($field => $args[1][0])));
			}
		}

		if(isset($args[2]) && is_a($args[2], 'Model')) {
			$this->_prepareLogQuery($args[2]);
		}
        try {
            if ($this->fullDebug) {
                $this->logQuery("db.runCommand( :query )", 	compact('query'));
            }
            $return = $this->_db
                ->command($query);
            
        } catch(Exception $e) {
            $this->error = $e->getMessage();
            throw new MongodbException($this->error);
        }

		return $return;
	}

/**
 * mapReduce
 *
 * @param mixed $query
 * @param integer $timeout (milli second)
 * @return mixed false or array
 * @access public
 */
	public function mapReduce($query, $timeout = null) {

		//above MongoDB1.8, query must object.
		if(isset($query['query']) && !is_object($query['query'])) {
			$query['query'] = (object)$query['query'];
		}

		$result = $this->query($query);

		if($result['ok']) {
			if (isset($query['out']['inline']) && $query['out']['inline'] === 1) {
				if (is_array($result['results'])) {
					$data = $result['results'];
				}
                else{
					$data = false;
				}
			}
            else {
				$data = $this->_db->selectCollection($result['result'])->find();
				if(!empty($timeout)) {
					$data->timeout($timeout);
				}
			}
			return $data;
		}
		return false;
	}



/**
 * Prepares a value, or an array of values for database queries by quoting and escaping them.
 *
 * @param mixed $data A value or an array of values to prepare.
 * @param string $column The column into which this data will be inserted
 * @return mixed Prepared value or array of values.
 * @access public
 */
	public function value($data, $column = null) {
		if (is_array($data) && !empty($data)) {
			return array_map(
				array(&$this, 'value'),
				$data, array_fill(0, count($data), $column)
			);
		} elseif (is_object($data) && isset($data->type, $data->value)) {
			if ($data->type == 'identifier') {
				return $this->name($data->value);
			} elseif ($data->type == 'expression') {
				return $data->value;
			}
		} elseif (in_array($data, array('{$__cakeID__$}', '{$__cakeForeignKey__$}'), true)) {
			return $data;
		}

		if ($data === null || (is_array($data) && empty($data))) {
			return 'NULL';
		}

		if (empty($column)) {
			$column = $this->introspectType($data);
		}

		switch ($column) {
			case 'binary':
			case 'string':
			case 'text':
				return $data;
			case 'boolean':
				return !empty($data);
			default:
				if ($data === '') {
					return 'NULL';
				}
				if (is_float($data)) {
					return str_replace(',', '.', strval($data));
				}

				return $data;
		}
	}

/**
 * execute method
 *
 * If there is no query or the query is true, execute has probably been called as part of a
 * db-agnostic process which does not have a mongo equivalent, don't do anything.
 *
 * @param mixed $query
 * @param array $options
 * @param array $params array()
 * @return void
 * @access public
 */
	public function execute($query, $params = array(), $unused=array()) {
		if (!$this->isConnected()) {
			return false;
		}

		if (!$query || $query === true) {
			return;
		}
		$this->_prepareLogQuery(); // just sets a timer
		$return = $this->_db
			->execute($query, $params);
		if ($this->fullDebug) {
			if ($params) {
				$this->logQuery(":query, :params",
					compact('query', 'params')
				);
			} else {
				$this->logQuery($query);
			}
		}
		if ($return['ok']) {
			return $return['retval'];
		}
		return $return;
	}
    
/**
 * Creates a MongoDB conditions array from passed conditions data.
 * 
 * @param array $conditions Array of database-agnostic conditions
 * @param type $quoteValues unused, inherited from DBOsource
 * @param type $where unused
 * @param type $model Model upon which the conditions are made
 * @return array Array of Mongo compatible conditions
 * 
 * @todo Expand the conditions to allow AND/OR/XOR?(might be complex)/NOT/LT(e)/GT(e)/LIKE(regex)
 */
    public function conditions($conditions, $quoteValues = true, $where = true, $model = null) {
        $return = array();
        foreach ($conditions as $key => $value){
            if (is_array($value)){
                $numeric = (class_exists('Hash')) ? Hash::numeric(array_keys($value)) : Set::numeric(array_keys($value));
                if ($numeric) {
                    $return[$key] = array('$in' => $value);
                }
                else {
                    $return[$key] = $value;
                }
            }
            else { // non-array conditions
                $return[$key] = $value;
            }
        }
        return $return;
    }
    
/**
 * Set empty values, arrays or integers, for the variables Mongo uses
 *
 * @param mixed $data
 * @param array $integers array('limit', 'offset')
 * @return void
 * @access protected
 */
	protected function _setEmptyValues(&$data, $integers = array('limit', 'offset')) {
		if (!is_array($data)) {
			return;
		}
		foreach($data as $key => $value) {
			if (empty($value)) {
				if (in_array($key, $integers)) {
					$data[$key] = 0;
				} else {
					$data[$key] = array();
				}
			}
		}
	}

/**
 * prepareLogQuery method
 *
 * Any prep work to log a query
 *
 * @param mixed $Model
 * @return void
 * @access protected
 */
	protected function _prepareLogQuery() {
		if (!$this->fullDebug) {
			return false;
		}
		$this->_startTime = microtime(true);
		$this->took = null;
		$this->affected = null;
		$this->error = null;
		$this->numRows = null;
		return true;
	}

/**
 * setTimeout Method
 *
 * Sets the MongoCursor timeout so long queries (like map / reduce) can run at will.
 * Expressed in milliseconds, for an infinite timeout, set to -1
 *
 * @param int $ms
 * @return boolean
 * @access public
 */
	public function setTimeout($ms){
		MongoCursor::$timeout = $ms;

		return true;
	}

/**
 * logQuery method
 *
 * Set timers, errors and refer to the parent
 * If there are arguments passed - inject them into the query
 * Show MongoIds in a copy-and-paste-into-mongo format
 *
 *
 * @param mixed $query
 * @param array $args array()
 * @return void
 * @access public
 */
	public function logQuery($query, $args = array()) {
		if ($args) {
			$this->_stringify($args);
			$query = String::insert($query, $args);
		}
		$this->took = round((microtime(true) - $this->_startTime) * 1000, 0);
		$this->affected = null;
		if (empty($this->error['err'])) {
			$this->error = $this->_db->lastError();
			if (!is_scalar($this->error)) {
				$this->error = json_encode($this->error);
			}
		}
		$this->numRows = !empty($args['count'])?$args['count']:null;

		$query = preg_replace('@"ObjectId\((.*?)\)"@', 'ObjectId ("\1")', $query);
		return parent::logQuery($query);
	}

/**
 * convertId method
 *
 * $conditions is used to determine if it should try to auto correct _id => array() queries
 * it only appies to conditions, hence the param name
 *
 * @param mixed $mixed
 * @param bool $conditions false
 * @return void
 * @access protected
 */
	protected function _convertId(&$mixed, $conditions = false) {
        /** @todo fallback if Object must be instance of or throw an InvalidID exception */
		if (is_numeric($mixed)) {
			return;
		}
		if (is_string($mixed)) {
			if (strlen($mixed) !== 24) {
				return;
			}
			$mixed = new MongoId($mixed);
		}
		if (is_array($mixed)) {
			foreach($mixed as &$row) {
				$this->_convertId($row, false);
			}
			if (!empty($mixed[0]) && $conditions) {
				$mixed = array('$in' => $mixed);
			}
		}
	}

/**
 * stringify method
 *
 * Takes an array of args as an input and returns an array of json-encoded strings. Takes care of
 * any objects the arrays might be holding (MongoID);
 *
 * @param array $args array()
 * @param int $level 0 internal recursion counter
 * @return array
 * @access protected
 */
	protected function _stringify(&$args = array(), $level = 0) {
		foreach($args as &$arg) {
			if (is_array($arg)) {
				$this->_stringify($arg, $level + 1);
			} elseif (is_object($arg) && is_callable(array($arg, '__toString'))) {
				$class = get_class($arg);
				if ($class === 'MongoId') {
					$arg = 'ObjectId(' . $arg->__toString() . ')';
				} elseif ($class === 'MongoRegex') {
					$arg = '_regexstart_' . $arg->__toString() . '_regexend_';
				} else {
					$arg = $class . '(' . $arg->__toString() . ')';
				}
			}
			if ($level === 0) {
				$arg = json_encode($arg);
				if (strpos($arg, '_regexstart_')) {
					preg_match_all('@"_regexstart_(.*?)_regexend_"@', $arg, $matches);
					foreach($matches[0] as $i => $whole) {
						$replace = stripslashes($matches[1][$i]);
						$arg = str_replace($whole, $replace, $arg);
					}
				}
			}
		}
	}

/**
 * Convert automatically array('Model.field' => 'foo') to array('field' => 'foo')
 *
 * This introduces the limitation that you can't have a (nested) field with the same name as the model
 * But it's a small price to pay to be able to use other behaviors/functionality with mongoDB
 *
 * @param array $args array()
 * @param string $alias 'Model'
 * @param bool $recurse true
 * @param string $check 'key', 'value' or 'both'
 * @return void
 * @access protected
 */
	protected function _stripAlias(&$args = array(), $alias = 'Model', $recurse = true, $check = 'key') {
		if (!is_array($args)) {
			return;
		}
		$checkKey = ($check === 'key' || $check === 'both');
		$checkValue = ($check === 'value' || $check === 'both');

		foreach($args as $key => &$val) {
			if ($checkKey) {
				if (strpos($key, $alias . '.') === 0) {
					unset($args[$key]);
					$key = substr($key, strlen($alias) + 1);
					$args[$key] = $val;
				}
			}
			if ($checkValue) {
				if (is_string($val) && strpos($val, $alias . '.') === 0) {
					$val = substr($val, strlen($alias) + 1);
				}
			}
			if ($recurse && is_array($val)) {
				$this->_stripAlias($val, $alias, true, $check);
			}
		}
	}
    
    /**
 * Queries associations. Used to fetch results on recursive models.
 *
 * @param Model $model Primary Model object
 * @param Model $linkModel Linked model that
 * @param string $type Association type, one of the model association types ie. hasMany
 * @param string $association
 * @param array $assocData
 * @param array $queryData
 * @param boolean $external Whether or not the association query is on an external datasource.
 * @param array $resultSet Existing results
 * @param integer $recursive Number of levels of association
 * @param array $stack
 * @return mixed
 * @throws CakeException when results cannot be created.
 */
	public function queryAssociation(Model $model, &$linkModel, $type, $association, $assocData, &$queryData, $external, &$resultSet, $recursive, $stack) {
            $idList = array();
            $keyName = ($type === 'belongsTo') ? $assocData['foreignKey'] : $model->primaryKey;
            foreach($resultSet as $modelData) {
                if(!empty($modelData[$model->alias]) && isset($modelData[$model->alias][$keyName])) {
                    $idList[] = $modelData[$model->alias][$keyName];
                }
            }
            
            if (count($idList) == 0) {
                /**
                 * @todo Check if other conditions might still invoke a query for associations
                 */
                return false;
            }
            
        switch($type):
            case 'hasMany':
                $_conditions = array($assocData['foreignKey'] => array('$in' => $idList));
                if(is_array($assocData['conditions'])) {
                    $_conditions = array_merge($_conditions, $assocData['conditions']);
                }
                $params = array(
                        'conditions' => $_conditions,
                        'recursive' => $recursive - 1,
                        );
                $hasManyResult = $linkModel->find('all', $params);
                

                if(!empty($hasManyResult)) {
                    //aggregate retrieving data using array key(foreigin key _id)
                    $hasManyResultSet = array();
                    foreach($hasManyResult as $key => $val) {
                        $foreignKey = $val[$linkModel->alias][$assocData['foreignKey']];
                        if(!empty($foreignKey)) {
                            $hasManyResultSet[$foreignKey] = $val[$linkModel->alias];
                        }
                    }

                    $this->_mergeHasMany($resultSet, $hasManyResult, $association, $model, $linkModel);
                }
                break;

            case 'hasOne':
                $_conditions = array($assocData['foreignKey'] => array('$in' => $idList));
                if(is_array($assocData['conditions'])) {
                    $_conditions = array_merge($_conditions, $assocData['conditions']);
                }
                $params = array(
                    'conditions' => $_conditions,
                    'recursive' => $recursive - 1,
                );
                $hasOneResult = $linkModel->find('all', $params);

                if(!empty($hasOneResult)) {
                    //aggregate retrieving data using array key(foreigin key _id)
                    $hasOneResultSet = array();
                    foreach($hasOneResult as $key => $val) {
                        $foreignKey = $val[$linkModel->alias][$assocData['foreignKey']];
                        if(!empty($foreignKey)) {
                            $hasOneResultSet[$foreignKey] = $val[$linkModel->alias];
                        }
                    }
                    //set relational retrieving data to return data
                    foreach($resultSet as $key => $val) {
                        if(!empty($hasOneResultSet[ $val[$model->alias][$model->primaryKey] ])) {
                            $resultSet[$key][$model->alias][$linkModel->alias] = $hasOneResultSet[ $val[$model->alias][$model->primaryKey] ];
                        } 
                        else {
                            $resultSet[$key][$model->alias][$linkModel->alias] = array();
                        }
                    }
                }
                break;

            case 'belongsTo':
                $_conditions = array($linkModel->primaryKey => array('$in' => $idList));
                if(is_array($assocData['conditions'])) {
                    $_conditions = array_merge($_conditions, $assocData['conditions']);
                }
                $params = array(
                        'conditions' => $_conditions,
                        'recursive' => $recursive - 1,
                        );
                $belongsToResult = $linkModel->find('all', $params);

                if(!empty($belongsToResult)) {
                    //aggregate retrieving data using array key(foreigin key _id)
                    $belongsToResultSet = array();
                    foreach($belongsToResult as $key => $val) {
                        $belongsToResultSet[ $val[$linkModel->alias][$linkModel->primaryKey] ] = $val[$linkModel->alias];
                    }
                    //set relational retrieving data to return data
                    foreach($resultSet as $key => $val) {
                        if(!empty($belongsToResultSet[ $val[$model->alias][$assocData['foreignKey']] ])) {
                            $resultSet[$key][$linkModel->alias] = $belongsToResultSet[ $val[$model->alias][$assocData['foreignKey']] ];
                        }
                        else {
                            $resultSet[$key][$linkModel->alias] = array();
                        }
                    }
                }
                break;
            
            case 'hasAndBelongsToMany':
                $_conditions = array($assocData['foreignKey'] => array('$in' => $idList));
                if(is_array($assocData['conditions'])) {
                    $_conditions = array_merge($_conditions, $assocData['conditions']);
                }
                
                $params = array(
                        'conditions' => $_conditions,
                        'recursive' => $recursive - 1,
                        );
                
                if (isset($assocData['with']) && !empty($assocData['with'])) { 
                    /** 
                     * @todo replace 'with' with $model->joinModel($this->hasAndBelongsToMany[$assoc]['with']); 
                     * @todo cleanup unecessary field checks (for schemaless models)
                     */
					$joinKeys = array($assocData['foreignKey'], $assocData['associationForeignKey']);
					list($with, $joinFields) = $model->joinModel($assocData['with'], $joinKeys);
                    if (array_intersect($joinFields, $joinKeys) !== $joinKeys) {
                        //ADD the join keys for schemaless join models
                        /** @todo Account for schemaless join more conistently, this is basically guesswork */
                        $joinFields = array_merge($joinFields, $joinKeys);
                    }

					$joinTbl = $model->{$with};
					$joinAlias = $joinTbl;

					if (is_array($joinFields) && !empty($joinFields)) {
						$joinAssoc = $joinAlias = $model->{$with}->alias;
						$joinFields = $this->fields($model->{$with}, $joinAlias, $joinFields);
					} else {
						$joinFields = array();
					}
				} else {
					$joinTbl = $assocData['joinTable'];
					$joinAlias = $this->fullTableName($assocData['joinTable']);
				}
                
                
                $params['fields'] = (!empty($assocData['fields'])) ? array_merge($this->fields($linkModel, $association, $assocData['fields']), $joinFields) : '';
                $params['limit'] = $assocData['limit'];
                $params['order'] = $assocData['order'];
                /** $query = array(
					'conditions' => $assocData['conditions'],
					'limit' => $assocData['limit'],
					'table' => $this->fullTableName($linkModel),
					'alias' => $association,
					'fields' => array_merge($this->fields($linkModel, $association, $assocData['fields']), $joinFields),
					'order' => $assocData['order'],
					'group' => null,
					'joins' => array(array(
						'table' => $joinTbl,
						'alias' => $joinAssoc,
						'conditions' => $this->getConstraint('hasAndBelongsToMany', $model, $linkModel, $joinAlias, $assocData, $association)
					))
				); */
                
                
                
                $linkResults = $joinTbl->find('all', $params);
                if (class_exists('Hash')){
                    $associationIds = Hash::extract($linkResults, "{n}.$joinAlias.{$assocData['associationForeignKey']}");
                } else {
                    $associationIds = Set::extract($linkResults, "{n}.$joinAlias.{$assocData['associationForeignKey']}");
                }
                if (empty($associationIds)) {
                    /**
                     * @todo Should an Exception or warning be thrown if no associations are found?
                     */
                    return false;
                }
                $_conditions = array($linkModel->primaryKey => array('$in' => $associationIds));
                if(is_array($assocData['conditions'])) {
                    $_conditions = array_merge($_conditions, $assocData['conditions']);
                }
                $params['conditions'] = $_conditions;
                
                $habtmResults = $linkModel->find('all', $params);
                
                if (!empty($habtmResults)){
                    foreach($resultSet as $index => $record) {
                        /** @todo mangle data here if necessary */
                    }
                    $this->_mergeHabtm($resultSet, $linkResults, $habtmResults, $association, $model, $linkModel);
                }
                

                //// TAKEN FROM DBO SOURCE
                $joinFields = array();
				$joinAssoc = null;

				if (isset($assocData['with']) && !empty($assocData['with'])) {
					$joinKeys = array($assocData['foreignKey'], $assocData['associationForeignKey']);
					list($with, $joinFields) = $model->joinModel($assocData['with'], $joinKeys);

					$joinTbl = $model->{$with};
					$joinAlias = $joinTbl;

					if (is_array($joinFields) && !empty($joinFields)) {
						$joinAssoc = $joinAlias = $model->{$with}->alias;
						$joinFields = $this->fields($model->{$with}, $joinAlias, $joinFields);
					} else {
						$joinFields = array();
					}
				} else {
					$joinTbl = $assocData['joinTable'];
					$joinAlias = $this->fullTableName($assocData['joinTable']);
				}
				$query = array(
					'conditions' => $assocData['conditions'],
					'limit' => $assocData['limit'],
					'table' => $this->fullTableName($linkModel),
					'alias' => $association,
					'fields' => array_merge($this->fields($linkModel, $association, $assocData['fields']), $joinFields),
					'order' => $assocData['order'],
					'group' => null,
					'joins' => array(array(
						'table' => $joinTbl,
						'alias' => $joinAssoc,
						'conditions' => $this->getConstraint('hasAndBelongsToMany', $model, $linkModel, $joinAlias, $assocData, $association)
					))
				);
                
                
                //// END TAKEN FROM DBO
                
                break;
        endswitch; //$type
    }
    
/**
 * mergeHabtm - Merge the results of habtm relations.
 *
 *
 * @param array $resultSet Data to merge into
 * @param array $joinResults The data from the join table
 * @param array $merge Data to merge
 * @param string $association Name of Model being Merged
 * @param Model $model Model being merged onto
 * @param Model $linkModel Model being merged $model->habtm[$association] => foreignKey, associationFK
 * @return void
 * $resultSet, $linkResults, $habtmResults, $association, $model, $linkModel
 * 
 * $model	Vehicle		
	hasAndBelongsToMany	array[1]		
		[Owner]	array[15]		
			[className]	string	"Owner"	
			[joinTable]	string	"owners_vehicles"	
			[with]	string	"OwnersVehicle"	
			[foreignKey]	string	"vehicle_id"	
			[associationForeignKey]	string	"owner_id"	

$linkResults	array[2]		
	[0]	array[1]		
		[OwnersVehicle]	array[3]		
			[owner_id]	string	"513a943db58a90840400009c"	
			[vehicle_id]	string	"513a943db58a90840400009b"	
			[id]	string	"513a943db58a90840400009d"	
				
				
				
$resultSet	array[1]		
	[0]	array[1]		
		[Vehicle]	array[4]		
			[name]	string	"Corvette V12"	
			[id]	string	"513a943db58a90840400009b"	
	
	
	
$merge	array[2]		
	[0]			
		[Owner]		
			[name]	string	"John Longbottom"	
			[modified]	MongoDate		
			[created]	MongoDate		
			[id]	string	"513a943db58a90840400009c"	
 * 
 */
	protected function _mergeHabtm(&$resultSet, $joinResults, $merge, $association, $model, $linkModel) {
        $associationData = $model->hasAndBelongsToMany[$association];
        $joinPK = $model->hasAndBelongsToMany[$association]['foreignKey'];
        $joinFK = $model->hasAndBelongsToMany[$association]['associationForeignKey'];
		$modelAlias = $model->alias;
		$modelPK = $model->primaryKey;
		
		foreach ($resultSet as &$result) {
			if (!isset($result[$modelAlias])) {
				continue;
			}
			$merged = array();
			foreach ($joinResults as $joinData) {
                
                if ($joinData[$associationData['with']][$joinPK] == $result[$modelAlias][$modelPK]){
                    $mergeId = $joinData[$associationData['with']][$joinFK];
                    foreach($merge as $mergeData){
                        if ($mergeId == $mergeData[$association][$linkModel->primaryKey]){
                            $result[$association][] = $mergeData[$association];
                        }
                    }
                }
				if ( false && $result[$modelAlias][$modelPK] === $data[$association][$modelFK]) {
					if (count($data) > 1) {
						$data = array_merge($data[$association], $data);
						unset($data[$association]);
						foreach ($data as $key => $name) {
							if (is_numeric($key)) {
								$data[$association][] = $name;
								unset($data[$key]);
							}
						}
						$merged[] = $data;
					} else {
						$merged[] = $data[$association];
					}
				}
			}
			//$result = Hash::mergeDiff($result, array($association => $merged));
		}
	}
    
    /**
     * Count function used in read, refactored
     * 
     * @param Model $Model
     * @param array $conditions
     * @return array Result
     * @since 2.0b
     */
    private function _queryCount(Model $Model, $conditions = array()){
        $count = $this->_db
					->selectCollection($Model->table)
					->count($conditions);
        if ($this->fullDebug) {
            $this->logQuery("db.{$Model->useTable}.count( :conditions )",
                compact('conditions', 'count')
            );
        }
        return array(array($Model->alias => array('count' => $count)));
    }
    
    /**
     * Date formatter for date/time column types
     * 
     * 
     * @since 2.0b
     * @param type $date
     * @return MongoDate
     */
    public static function dateFormatter($date = null) {
        if ($date) {
            return new MongoDate($date);
        }
        return new MongoDate();
    }
    
}



/**
 * MongoDbDateFormatter function
 *
 * Alias for MongodbSource::dateFormatter()
 *
 * @param mixed $date null
 * @return MongoDate
 * @access public
 * @deprecated since version 2.0b
 */

function MongoDbDateFormatter($date=null){
    if (Configure::read('debug') > 0){
        $error_type = (defined('E_USER_DEPRECATED')) ? E_USER_DEPRECATED : E_USER_NOTICE;
        trigger_error("Deprecated, please use MongodbSource::dateFormatter() instead", $error_type);
    }
    return MongodbSource::dateFormatter($date);
}
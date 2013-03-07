<?php
/**
 * Test cases for the Cakephp mongoDB datasource.
 *
 * This datasource uses Pecl Mongo (http://php.net/mongo)
 * and is thus dependent on PHP 5.0 and greater.
 *
 * Copyright 2010, Yasushi Ichikawa http://github.com/ichikaway/
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2010, Yasushi Ichikawa http://github.com/ichikaway/
 * @package       mongodb
 * @subpackage    mongodb.tests.cases.datasources
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * Import relevant classes for testing
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('MongodbSource', 'Mongodb.Model/Datasource');

class Vehicle extends AppModel {
    public $useDbConfig = 'test_mongo';
    public $hasMany = array(
        'VehiclePart' => array()
    );
    
    public $hasAndBelongsToMany = array(
        'Color'
    );
}
class VehiclePart extends AppModel {
    public $useDbConfig = 'test_mongo';
    //public $useTable = 'vehicle_parts';
    public $belongsTo = array(
        'Vehicle' => array()
    );
}

class Color extends AppModel {
    public $useDbConfig = 'test_mongo';
    public $hasAndBelongsToMany = array(
        'Vehicle' => array()
    );
}


class Car extends Vehicle {
    
}
class Engine extends VehiclePart {
    
}


/**
 * MongoDB Associactions test class
 *
 * @package       app
 * @subpackage    app.model
 * @property Model $Vehicle
 * @property Model $VehiclePart
 * @property Model $Car
 * @property Model $Engine
 */
class MongodbSourceTest extends CakeTestCase {
    
    public $debug = true;

/**
 * Database Instance
 *
 * @var resource
 * @access public
 */
	public $mongodb;

/**
 * Base Config
 *
 * @var array
 * @access public
 *
 */
	protected $_config = array(
		'datasource' => 'Mongodb.MongodbSource',
		'host' => 'localhost',
		'login' => '',
		'password' => '',
		'database' => 'test_mongo',
		'port' => 27017,
		'prefix' => '',
		//'persistent' => true,
	);
    
    
    
    
    ###### TEST CASES #####
    
    public function testSave(){
        $data = array(
            'name' => 'Beetle',
            'year' => 1950
        );
        $this->Vehicle->save($data);
        $result = $this->Vehicle->read();
        $this->assertNotEmpty($result);
    }
    
    public function testSaveAndFetchMany(){
        $vehicle = array(
            'name' => 'Corvette',
            'year' => 2008
        );
        $this->Vehicle->save($vehicle);
        $parts = array(
            array(
                'type' => 'exhaust',
                'name' => 'RCD double',
                'engine_type' => 'V6',
                'vehicle_id' => $this->Vehicle->getInsertId()
                ),
            array(
                'type' => 'wheel',
                'name' => 'Moonshine 17',
                'size' => array(
                    'core' => 12,
                    'rim' => 15,
                    'width' => 225
                ),
                'vehicle_id' => $this->Vehicle->getInsertId()
                ),
        );
        $this->VehiclePart->saveMany($parts);
        $conditions = array('id' => $this->Vehicle->getInsertId());
        $result = $this->Vehicle->find('first', compact('conditions'));
        $this->assertArraySubsetMatches($result, array('Vehicle' => $vehicle, 'VehiclePart' => $parts));
    }
    
    function testSaveAssociatedMany(){
        $Vehicle = array(
            'name' => 'Corvette',
            'year' => 2008
        );
        $VehiclePart = array(
            array(
                'type' => 'exhaust',
                'name' => 'RCD double',
                'engine_type' => 'V6'
                ),
            array(
                'type' => 'wheel',
                'name' => 'Moonshine 17',
                'size' => array(
                    'core' => 12,
                    'rim' => 15,
                    'width' => 225
                )
                ),
        );
        $this->Vehicle->saveAssociated(compact('Vehicle', 'VehiclePart'));
        $this->Vehicle->recursive = 1;
        $result = $this->Vehicle->read(null, $this->Vehicle->getInsertId());
        //debug($result); die();
        $this->assertArraySubsetMatches($result, compact('Vehicle', 'VehiclePart'));
    }
    function testSaveAssociatedBelongsTo(){
        $Vehicle = array(
            'name' => 'Corvette',
            'year' => 2008
        );
        $VehiclePart = array(
            
                'type' => 'wheel',
                'name' => 'Moonshine 17',
                'size' => array(
                    'core' => 12,
                    'rim' => 15,
                    'width' => 225
                )
            
        );
        $this->VehiclePart->saveAssociated(compact('Vehicle', 'VehiclePart'));
        $this->Vehicle->recursive = 1;
        $result = $this->Vehicle->read(null, $this->Vehicle->getInsertId());
        $this->assertArraySubsetMatches($result, array('Vehicle' => $Vehicle, 'VehiclePart' => array($VehiclePart)));
    }
    
    function test
    
    function testCheckInheritance(){
        $expected = array(
            'VehiclePart' => 'VehiclePart',
            'AppModel' => 'AppModel',
            'Model' => 'Model',
            'Object' => 'Object'
        );
        $actual = class_parents($this->Engine);
        $this->assertEquals($expected, $actual);
    }
    
    function testInherited(){
        $Engine = array(
            'type' => 'V8',
            'manufacturer' => 'VolksWagen'
        );
        $this->Engine->save($Engine);
    }
    
    
    function startTest($method) {
        $this->dropData();
    }

/**
 * Sets up the environment for each test method
 *
 * @return void
 * @access public
 */
	public function setUp() {
        
		$connections = ConnectionManager::enumConnectionObjects();

		if (!empty($connections['test']['classname']) && $connections['test']['classname'] === 'mongodbSource') {
			$config = new DATABASE_CONFIG();
			$this->_config = $config->test;
		} elseif (isset($connections['test_mongo'])) {
			$this->_config = $connections['test_mongo'];
		}

		if(!isset($connections['test_mongo'])) {
			ConnectionManager::create('test_mongo', $this->_config);
		}

		$this->Mongo = new MongodbSource($this->_config);

		$this->Vehicle = ClassRegistry::init(array('class' => 'Vehicle'), true);
		$this->VehiclePart = ClassRegistry::init(array('class' => 'VehiclePart'), true);
        $this->Engine = ClassRegistry::init(array('class' => 'Engine'), true);

		$this->mongodb = ConnectionManager::getDataSource($this->Vehicle->useDbConfig);
		$this->mongodb->connect();
        $this->dropData();
	}

/**
 * Destroys the environment after each test method is run
 *
 * @return void
 * @access public
 */
	public function tearDown() {
		//$this->dropData();
        unset($this->Vehicle);
        unset($this->VehiclePart);
		unset($this->Mongo);
		unset($this->mongodb);
		ClassRegistry::flush();
	}


/**
 * get Mongod server version
 *
 * @return numeric
 * @access public
 */
	public function getMongodVersion() {
		$mongo = $this->Post->getDataSource();
		return $mongo->execute('db.version()');
	}

/**
 * Drop database
 *
 * @return void
 * @access public
 */
	public function dropData() {
        
		try {
			$db = $this->mongodb
				->connection
				->selectDB($this->_config['database']);

			foreach($db->listCollections() as $collection) {
				$collection->drop();
			}
		} catch (MongoException $e) {
			trigger_error($e->getMessage());
		}
	}


    
    
    protected function assertArraySubsetMatches($result, $expected){
        if (!is_array($result) || !is_array($expected)) {
            $this->fail("assertArraySubsetMatches requires two arrays as arguments");
        }
        if (class_exists('Hash')) {
            $result = Hash::flatten($result);
            $expected = Hash::flatten($expected);
        } else {
            $result = Set::flatten($result);
            $expected = Set::flatten($expected);
        }
        foreach (array_keys($expected) as $key) {
            if (!key_exists($key, $result)) {
                if ($this->debug){
                    debug($result); debug($expected); xdebug_print_function_stack(); die();
                }
                $this->fail("Key '{$key}' missing in actual array.");
            }
        }
        foreach (array_keys($result) as $key) {
            if (!key_exists($key, $expected)) {
                unset($result[$key]);
            }
        }
        $this->assertEquals($expected, $result);
    }
    
    
    
    /**
     * Backwards compatible function for Hash::extract
     * 
     * @param array $set
     * @param string $path
     * @param string $compatPath
     * @return array Result of Hash::extract()
     */
    protected function _extract($set, $path, $compatPath = null){
        if (class_exists('Hash')) {
            return Hash::extract($set, $path);
        }
        else {
            return Set::extract($set, ($compatPath === null) ? $path : $compatPath);
        }
    }
}

if (!function_exists('class_parents')) {
  function class_parents($class=null, $autoload = null, $plist=array()) {
    $parent = get_parent_class($class);
    if($parent) {
      $plist[$parent] = $parent;
      $plist = class_parents($parent, null, $plist);
    }
    return $plist;
  }
}
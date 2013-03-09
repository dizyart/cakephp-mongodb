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
App::uses('MongodbAppModel', 'Mongodb.Model');
App::uses('MongodbSource', 'Mongodb.Model/Datasource');



class Vehicle extends MongodbAppModel {
    public $useDbConfig = 'test_mongo';
    public $hasMany = array(
        'VehiclePart' => array()
    );
    public $belongsTo = array(
        //'Manufacturer'
    );
    
    public $hasAndBelongsToMany = array(
        'Owner' => array(
            'className' => "Owner",
			'joinTable' => "owners_vehicles",
			'with' => "OwnersVehicle",
			'foreignKey' => "car_id",
			'associationForeignKey' => "owner_id"
        )
    );
}
class VehiclePart extends MongodbAppModel {
    public $useDbConfig = 'test_mongo';
    //public $useTable = 'vehicle_parts';
    public $belongsTo = array(
        'Vehicle' => array(),
        //'Manufacturer'
    );
}

class Manufacturer extends MongodbAppModel {
    public $useDbConfig = 'test_mongo';
    public $hasMany = array('VehiclePart', 'Vehicle');
}

class Owner extends MongodbAppModel {
    public $useDbConfig = 'test_mongo';
    public $hasAndBelongsToMany = array(
        'Vehicle' => array(
            'associationForeignKey' => 'car_id'
        )
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
class MongodbAssociationsTest extends CakeTestCase {
    
    public $debug = true;

/**
 * Database Instance
 *
 * @var resource
 * @access public
 */
	public $mongodb;
    
    /**
     *
     * @var boolean If true, test method data persists until next test
     */
    public $keepDataForNext = false;
    
    /**
     *
     * @var boolean If true, no data is dropped after each test (but is erased on setup)
     */
    public $keepAllData = false;

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
    
    public function __construct($name = NULL, array $data = array(), $dataName = '') {
        parent::__construct($name, $data, $dataName);
        if (!empty($_GET['debug'])) {
            $this->keepAllData = true;  
        }
    }
    
    
    
    
    ###### TEST CASES #####
    
    public function testSave(){
        $this->dropData();
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
    
    
    function testCheckInheritance(){
        $expected = array(
            'VehiclePart' => 'VehiclePart',
            'MongodbAppModel' => 'MongodbAppModel',
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
    
    function testSaveAssociatedDeep() {
        $this->Vehicle->bindModel(array('belongsTo' => array('Manufacturer')), false);
        $this->Vehicle->VehiclePart->bindModel(array('belongsTo' => array('Manufacturer')), false);
        $manufacturer = array(
            'name' => 'Ford',
            'established' => 'June 16, 1903'
        );
        $this->Manufacturer->save($manufacturer);
        $manufacturer_id = $this->Manufacturer->getInsertId();
        $vehicle_id = (string) new MongoId();
        $manufacturer2_id = (string) new MongoId();
        $data = array(
            'Vehicle' => array('name' => 'Model T', 'manufacturer_id' => $manufacturer_id, 'id' => $vehicle_id),
            'VehiclePart' => array(
                array('class' => 'Transmission', 'type' => 'planetary gear', 'manufacturer_id' => $manufacturer_id),
                array('class' => 'Chassis', 'type' => 'Left replacement door', 'Manufacturer' => array('id' => $manufacturer2_id, 'name' => 'The Model T Ford Club International', 'established' => 'December 1952')),
            )
        );
        $this->Vehicle->saveAssociated($data, array('deep' => true));
        $this->Vehicle->recursive = 3;
        $result = $this->Vehicle->find('first', array('conditions' => array('id' => $vehicle_id)));
        $expect = array(
            'Vehicle' => array(
                'id' => (string) $vehicle_id,
                'name' => 'Model T',
                'manufacturer_id' => (string) $manufacturer_id
            ),
            'Manufacturer' => array(
                'name' => 'Ford',
                'established' => 'June 16, 1903',
                'id' => (string) $manufacturer_id
            ),
            'VehiclePart' => array(
                (int) 0 => array(
                    'vehicle_id' => (string) $vehicle_id,
                    'manufacturer_id' => (string) $manufacturer_id,
                    'class' => 'Transmission',
                    'type' => 'planetary gear',
                    'Vehicle' => array(
                        'id' => (string) $vehicle_id,
                        'name' => 'Model T',
                        'manufacturer_id' => (string) $manufacturer_id,
                    ),
                    'Manufacturer' => array(
                        'id' => (string) $manufacturer_id,
                        'name' => 'Ford',
                        'established' => 'June 16, 1903',
                    )
                ),
                (int) 1 => array(
                    'manufacturer_id' => (string) $manufacturer2_id,
                    'vehicle_id' => (string) $vehicle_id,
                    'class' => 'Chassis',
                    'type' => 'Left replacement door',
                    'Vehicle' => array(
                        'id' => (string) $vehicle_id,
                        'name' => 'Model T',
                        'manufacturer_id' => (string) $manufacturer_id,
                    ),
                    'Manufacturer' => array(
                        'id' => (string) $manufacturer2_id,
                        'name' => 'The Model T Ford Club International',
                        'established' => 'December 1952',
                    )
                )
            )
        );
        $this->assertArraySubsetMatches($result, $expect);
    }
    
    
    function testSaveHabtm(){
        $this->keepDataForNext = true;
        $vehicle_id = new MongoId();
        $vehicle_data = array('id' => $vehicle_id, 'name' => 'Corvette V12');
        $this->Vehicle->save($vehicle_data);
        $data = array(
            array(
            'Vehicle' => array('id' => (string)$vehicle_id),
            'Owner' => array('name' => 'John Longbottom', 'current' => true)
            ),
            array(
            'Vehicle' => array('id' => (string)$vehicle_id),
            'Owner' => array('name' => 'Jim Lipton', 'since' => '1993', 'until' => '2000')
            ),
        );
        $this->Owner->saveAll($data);
        $this->Vehicle->recursive = 1;
        $result = $this->Vehicle->read(null, $vehicle_id);
        
        $expected = array(
            'Vehicle' => array(
                'name' => 'Corvette V12',
                'id' => (string)$vehicle_id
            ),
            'Owner' => array(
                array('name' => 'John Longbottom', 'current' => true),
                array('name' => 'Jim Lipton', 'since' => '1993', 'until' => '2000')
            )
        );
        $this->assertArraySubsetMatches($result, $expected);
        
        $result = $this->Owner->find('first', array('conditions' => array('current' => true)));
        $expected = array(
            'Owner' => array(
                'name' => 'John Longbottom',
                'current' => true
            ),
            'Vehicle' => array(
                array(
                    'name' => 'Corvette V12',
                )
            )
        );
        $this->assertArraySubsetMatches($result, $expected);
    }
    
    
    
    public function testReplaceHabtm(){
        $data = $this->Vehicle->find('first', array('conditions'  => array('name' => 'Corvette V12')));
        unset($data['Owner'][0]);
        $ownerIds = array();
        foreach($data['Owner'] as $owner) {
            $ownerIds[] = $owner['id'];
        }
        
        $data = array(
            'id' => $data['Vehicle']['id'],
            'Owner' => $ownerIds
        );
        $this->Vehicle->save($data);
        $result = $this->Vehicle->read();
        
        $expected = array(
            'Vehicle' => array(
                'name' => 'Corvette V12'
            ),
            'Owner' => array(
                array(
                    'name' => 'Jim Lipton',
                    'since' => '1993',
                    'until' => '2000',
                )
            )
        );
        
        $this->assertArraySubsetMatches($result, $expected);
        $this->assertCount(count($ownerIds), $result['Owner']);
        
    }
    
    
    
    
    
    
    
    
    
    function startTest($method){
        
    }
    function endTest($method) {
        if (!$this->keepDataForNext && !$this->keepAllData){
            $this->dropData();
        }
        $this->keepDataForNext = false;
    }

/**
 * Sets up the environment for each TEST METHOD
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
        $this->Manufacturer = ClassRegistry::init(array('class' => 'Manufacturer'), true);
        $this->Owner = ClassRegistry::init(array('class' => 'Owner'), true);

		$this->mongodb = ConnectionManager::getDataSource($this->Vehicle->useDbConfig);
		$this->mongodb->connect();
        
	}

/**
 * Destroys the environment after each test method is run
 *
 * @return void
 * @access public
 */
	public function tearDown() {
		if ($this->keepAllData == false && $this->keepDataForNext == false) {
        //    $this->dropData();
        }
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
                if (!empty($_GET['debug'])){
                    debug($result); debug($expected); xdebug_print_function_stack(); ob_flush();
                }
                $this->fail("Key '{$key}' missing in resulting array. Enable debug output to inspect the arrays.");
            }
        }
        foreach (array_keys($result) as $key) {
            if (!key_exists($key, $expected)) {
                unset($result[$key]);
            }
        }
        $this->assertEquals($expected, $result);
    }
    
    public function debug($what){
        if (!empty($_GET['debug'])){
            debug($what);
            ob_flush();
        }
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
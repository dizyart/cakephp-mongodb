<?php
/**
 * Base TestCase class for all mongoDB datasource tests.
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
 * @subpackage    mongodb.tests.cases.model
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

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
class MongoTestCase extends CakeTestCase {
    
    public $debug = true;

/**
 * Database Instance
 *
 * @var MongodbSource
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
    
    
    
    ##### UTILITY METHODS #####
    
    
    
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
        parent::setUp();
		//$config = ConnectionManager::enumConnectionObjects();
        $this->mongodb = ConnectionManager::getDataSource('test');
        $this->skipIf(!$this->mongodb instanceof MongodbSource, 'These tests must run on a MongoDB test database confirguration. Please set your "$test" database config to MongoDB.');
        $dbCon = new DATABASE_CONFIG();
        $this->_config = $dbCon->test;
	}

/**
 * Destroys the environment after each test method is run
 *
 * @return void
 * @access public
 */
	public function tearDown() {
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
        //$this->mongodb->connect();
        //debug($this->mongodb->MongoDB()); die();
        try {
			$db = $this->mongodb->getMongoDB(true);

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

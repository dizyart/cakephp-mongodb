<?php
/**
 * Tests subset validations
 *
 * PHP version 5
 *
 * Copyright (c) 2012, Radig Soluções em TI (http://radig.com.br)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2012, Radig Soluções em Ti (http://radig.com.br)
 * @link          http://github.com/radig/
 * @package       Mongodb
 * @subpackage    Mongodb.Test.Case.Behavior
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('MongodbSource', 'Mongodb.Model/Datasource');
App::uses('MongodbAppModel', 'Mongodb.Model');


/**
 * MyCompany class
 *
 * @uses          Post
 * @package       Mongodb
 * @subpackage    Mongodb.Test.Case.Behavior
 */
class MyCompany extends MongodbAppModel {

/**
 * useDbConfig property
 * DataSource automatically prepend 'test_' to this name
 *
 * @var string 'mongo'
 * @access public
 */
    public $useDbConfig = 'test_mongo';

/**
 * mongoSchema property
 * MongoDb Schema for this model
 *
 * @var array
 * @access public
 */
    public $mongoSchema = array(
        'name'  => array('type' => 'string'),
        'address' => array(
            'street'  => array('type' => 'string'),
            'number'  => array('type' => 'number'),
        ),
    );

/**
 * actsAs property
 *
 * @var array
 * @access public
 */
    public $actsAs = array(
        'Mongodb.SubCollectionValidator'
    );

/**
 * validate property
 *
 * @var array
 * @access public
 */
    public $validate = array(
        'name' => 'notempty'
    );

/**
 * collection validate property
 *
 * @var array
 * @access public
 */
    public $collectionValidate = array(
        'address' => array(
            'street' => array(
                'rule' => array('notempty'),
                'message' => 'only letters and numbers'
            ),
            'number' => array(
                'rule' => 'numeric'
            )
        )
    );
}

/**
 * SubCollectionValidatorBehaviorTest class
 *
 * @uses          CakeTestCase
 * @package       Mongodb
 * @subpackage    Mongodb.Test.Case.Behavior
 */
class SubCollectionValidatorBehaviorTest extends CakeTestCase {
    
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

/**
 * Sets up the environment for each test method
 *
 * @return void
 * @access public
 */
	public function setUp() {
        $testModel = ClassRegistry::init('AppModel');
        $this->skipIf(!method_exists($testModel, 'validator'), "SubCollectionValidator not available for CakePHP v".Configure::version());
        
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

		$this->Company = ClassRegistry::init(array('class' => 'MyCompany', 'ds' => 'test_mongo'), true);
		
		$this->mongodb = ConnectionManager::getDataSource($this->Company->useDbConfig);
		$this->mongodb->connect();
	}


    public function startTest($method) {
        
        //clear Company attributes
        $this->Company->create();
    }

/**
 * Destroys the environment after each test method is run
 *
 * @return void
 * @access public
 */
    public function tearDown() {
        unset($this->Company);
    }

/**
 * testValidateFailure method
 *
 * @return void
 * @access public
 */
    public function testValidateFailure() {
        $expected = false;
        $result = $this->Company->save(array(
            'name' => 'Radig',
            'address' => array('street' => null, 'number' => 141)
        ));
        $this->assertEqual($expected, $result);

        $expected = array('street' => array('only letters and numbers'));
        $result = $this->Company->validationErrors;
        $this->assertEqual($expected, $result);
    }

    /**
 * testValidateSuccess method
 *
 * @return void
 * @access public
 */
    public function testValidateSuccess() {
        $data = array(
            'name' => 'Radig',
            'address' => array('street' => 'abc123', 'number' => 141)
        );

        $result = $this->Company->save($data);
        $this->assertNotEmpty($result);

        $expected = array();
        $result = $this->Company->validationErrors;
        $this->assertEqual($expected, $result);
    }
}
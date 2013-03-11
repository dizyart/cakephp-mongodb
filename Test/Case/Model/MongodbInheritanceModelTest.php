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
 * @subpackage    mongodb.tests.cases.model
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
$mongoTestCase = dirname(__FILE__) . DS . '..' . DS . 'MongoTestCase.php';
require_once($mongoTestCase);
require_once(dirname(__FILE__) . DS . 'MongodbAssociationsTest.models.php');
require_once(dirname(__FILE__) . DS . 'MongodbInheritanceModelTest.models.php');
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
class MongodbInheritanceModelTest extends MongoTestCase {
    
    public $debug = true;
    public $keepDataForNext = false;
    public $keepAllData = false;
    
    public $inheritanceModels = array(
        'BaseModel',
        'BaseModelExtended',
        'UsesTable',
        'UsesParentTable',
        'UsesGrandparentTable',
        'FourthLevelRebel',
        'CustomBaseModel',
        'CustomBaseChild',
        'IntermediateAppModel',
        'SecondLevelBaseModel'
    );
    
/**
 * Sets up the environment for each TEST METHOD
 *
 * @return void
 * @access public
 */
	public function setUp() {
        parent::setUp();
        $this->Vehicle = ClassRegistry::init(array('class' => 'Vehicle'), true);
		$this->VehiclePart = ClassRegistry::init(array('class' => 'VehiclePart'), true);
        $this->Engine = ClassRegistry::init(array('class' => 'Engine'), true);
        $this->Manufacturer = ClassRegistry::init(array('class' => 'Manufacturer'), true);
        $this->Owner = ClassRegistry::init(array('class' => 'Owner'), true);
	}
    
    function testCheckInheritance(){
        $expected = array(
            'VehiclePart',
            'MongodbAppModel',
            'AppModel',
            'Model',
            'Object'
        );
        $actual = array_values(class_parents('Engine'));
        $this->assertEquals($expected, $actual);
    }
    
    function testInheritanceMethods(){
        $this->assertTrue($this->Engine->hasParentModel());
        $this->assertFalse($this->VehiclePart->hasParentModel());
        $actualParents = $this->Engine->getModelParents();
        $expected = array('VehiclePart');
        $this->assertEquals($expected, $actualParents);
    }
    
    function testInherited(){
        $Engine = array(
            'type' => 'V8',
            'manufacturer' => 'VolksWagen'
        );
        $this->Engine->save($Engine);
    }
    
    
    function testTableInheritance() {

        $testModel = ClassRegistry::init('BaseModel');
        $this->assertEquals('base_models', $testModel->useTable);
        $this->assertEquals('base_models', $testModel->table);

        $testModel = ClassRegistry::init('BaseModelExtended');
        $this->assertEquals('base_models', $testModel->useTable);
        $this->assertEquals('base_models', $testModel->table);

        $testModel = ClassRegistry::init('UsesTable');
        $this->assertEquals('uses_second_level_tables', $testModel->useTable);
        $this->assertEquals('uses_second_level_tables', $testModel->table);

        $testModel = ClassRegistry::init('UsesParentTable');
        $this->assertEquals('uses_second_level_tables', $testModel->useTable);
        $this->assertEquals('uses_second_level_tables', $testModel->table);
        
        $testModel = ClassRegistry::init('UsesGrandparentTable');
        $this->assertEquals('uses_second_level_tables', $testModel->useTable);
        $this->assertEquals('uses_second_level_tables', $testModel->table);

        $testModel = ClassRegistry::init('FourthLevelRebel');
        $this->assertEquals('fourth_level_rebel', $testModel->useTable);
        $this->assertEquals('fourth_level_rebel', $testModel->table);
        
        $testModel = ClassRegistry::init('CustomBaseModel');
        $this->assertEquals('custom_bases', $testModel->useTable);
        $this->assertEquals('custom_bases', $testModel->table);
        
        $testModel = ClassRegistry::init('CustomBaseChild');
        $this->assertEquals('custom_bases', $testModel->useTable);
        $this->assertEquals('custom_bases', $testModel->table);

        // intermediate app models should not be instantiated, in fact, they should not even exist
        // but just to check against Cake default functionality
        $testModel = ClassRegistry::init('IntermediateAppModel'); 
        $testAppModel = ClassRegistry::init('AppModel');
        if ($testAppModel->useTable == 'app_models'){
            $this->assertEquals('intermediate_app_models', $testModel->useTable);
            $this->assertEquals('intermediate_app_models', $testModel->table);
        }
        else {
            // see-into-future compatibility check, will probably fail
            $this->assertEquals($testAppModel->useTable, $testModel->useTable); 
        }

        $testModel = ClassRegistry::init('SecondLevelBaseModel');
        $this->assertEquals('second_level_base_models', $testModel->useTable);
        $this->assertEquals('second_level_base_models', $testModel->table);
        
    }
    
    public function testSaveInherited(){
        $saveValue = 1;
        foreach ($this->inheritanceModels as $modelName){
            $saveValue++;
            $testModel = ClassRegistry::init($modelName);
            $data = array('value' => $saveValue, 'class' => $modelName);
            $testModel->save($data);
            $parents = $testModel->getModelParents();
            $baseModel = (!empty($parents)) ? ClassRegistry::init($testModel->getTableParent()) : $testModel;
            $result = $baseModel->find('first', array('conditions' => array('value' => $saveValue)));
            $result2 = $testModel->find('first', array('conditions' => array('value' => $saveValue)));
            $this->assertFalse(empty($result));
            $this->assertEqual($result[$baseModel->alias]['class'], $modelName);
            $this->assertEqual($result[$baseModel->alias], $result2[$testModel->alias]);
        }
    }
    
/**
 * Destroys the environment after each test method is run
 *
 * @return void
 * @access public
 */
	public function tearDown() {
        unset($this->Vehicle);
        unset($this->VehiclePart);
		unset($this->Mongo);
		unset($this->mongodb);
        parent::tearDown();
	}

}

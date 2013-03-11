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
class MongodbAssociationsTest extends MongoTestCase {
    
    public $debug = true;
    public $keepDataForNext = false;
    public $keepAllData = false;
    
/**
 * Sets up the environment for each TEST METHOD
 *
 * @return void
 * @access public
 */
	public function setUp() {
        parent::setUp();
        $this->Vehicle = ClassRegistry::init(array('class' => 'Vehicle', 'ds' => 'test'), true);
		$this->VehiclePart = ClassRegistry::init(array('class' => 'VehiclePart', 'ds' => 'test'), true);
        $this->Engine = ClassRegistry::init(array('class' => 'Engine', 'ds' => 'test'), true);
        $this->Manufacturer = ClassRegistry::init(array('class' => 'Manufacturer', 'ds' => 'test'), true);
        $this->Owner = ClassRegistry::init(array('class' => 'Owner', 'ds' => 'test'), true);
	}
    
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
        $this->keepDataForNext = true;
    }
    
    
    
    public function testReplaceHabtm() {
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
    
    public function testBelongsToUpdate(){
        
        $data = array(
            'VehiclePart' => array(
                'name' => 'A special door'
            ),
            'Vehicle' => array(
                'name' => 'Car with special doors'
            )
        );
        
        $this->VehiclePart->saveAssociated($data);
        $newRecord = $this->VehiclePart->read();
        //$this->debug($newRecord);
        
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

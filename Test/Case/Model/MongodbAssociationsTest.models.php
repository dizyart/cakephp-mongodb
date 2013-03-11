<?php
/**
 * Import relevant classes for testing
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('MongodbAppModel', 'Mongodb.Model');
App::uses('MongodbSource', 'Mongodb.Model/Datasource');



class Vehicle extends MongodbAppModel {
    //public $useDbConfig = 'test_mongo';
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
    //public $useDbConfig = 'test_mongo';
    //public $useTable = 'vehicle_parts';
    public $belongsTo = array(
        'Vehicle' => array(),
        //'Manufacturer'
    );
}

class Manufacturer extends MongodbAppModel {
    //public $useDbConfig = 'test_mongo';
    public $hasMany = array('VehiclePart', 'Vehicle');
}

class Owner extends MongodbAppModel {
    //public $useDbConfig = 'test_mongo';
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


?>
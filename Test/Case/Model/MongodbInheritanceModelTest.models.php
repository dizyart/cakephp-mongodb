<?php
/**
 * Import relevant classes for testing
 */
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('MongodbAppModel', 'Mongodb.Model');
App::uses('MongodbSource', 'Mongodb.Model/Datasource');


class BaseModel extends MongodbAppModel {
    
}

class BaseModelExtended extends BaseModel {
    //should use 'base_models' table
    
}

class UsesTable extends BaseModel {
    public $useTable = 'uses_second_level_tables';
}

class UsesParentTable extends UsesTable {
    // should use 'uses_second_level_tables' table
}

class UsesGrandparentTable extends UsesParentTable {
    // should use 'uses_second_level_tables' table
}

class FourthLevelRebel extends UsesGrandparentTable {
    public $useTable = 'fourth_level_rebel';
}

class CustomBaseModel extends MongodbAppModel {
    public $useTable = 'custom_bases';
}

class CustomBaseChild extends CustomBaseModel {
    // should use 'custom_bases' table
}

class IntermediateAppModel extends MongodbAppModel {
    
}

class SecondLevelBaseModel extends IntermediateAppModel {
    protected $_baseModelClass = 'IntermediateAppModel';
    // ignores IntermediateAppModel as a MODEL, should useTable 'second_level_models'
    
}

?>
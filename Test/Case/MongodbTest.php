<?php






class MongodbTest extends PHPUnit_Framework_TestSuite {
    
/**
 * suite method, defines tests for this suite.
 *
 * @return void
 */
	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite('MongoDB Plugin tests');
        $casePath = CakePlugin::path('Mongodb') . DS . 'Test' . DS . 'Case';
		$suite->addTestFile($casePath . DS . 'Behavior' . DS . 'SqlCompatibleTest.php');
		$suite->addTestFile($casePath . DS . 'Behavior' . DS . 'SubCollectionValidatorBehaviorTest.php');
		$suite->addTestFile($casePath . DS . 'Datasource' . DS . 'MongodbSourceTest.php');
		$suite->addTestFile($casePath . DS . 'Model' . DS . 'MongodbAssociationsTest.php');
		$suite->addTestFile($casePath . DS . 'Model' . DS . 'MongodbInheritanceModelTest.php');
		return $suite;
	}
}




?>
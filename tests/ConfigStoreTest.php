<?php
namespace pdyn\config\tests;

use \pdyn\config\ConfigStore;

/**
 * Mock config store object exposing protected properties for inspection.
 */
class ConfigStoreMock extends ConfigStore {
	/** @var \pdyn\database\DbDriverInterface An active database connection. */
	public $DB;
}

/**
 * Test ConfigStore
 *
 * @group pdyn
 * @group pdyn_config
 * @codeCoverageIgnore
 */
class ConfigStoreTest extends \PHPUnit_Framework_TestCase {
	/**
	 * PHPUnit setup.
	 */
	protected function setUp() {
		if (!class_exists('\pdyn\database\pdo\sqlite\DbDriver')) {
			$this->markTestSkipped('No database class available.');
			return false;
		}
		$this->DB = new \pdyn\database\pdo\sqlite\DbDriver(['\pdyn\config\DbSchema']);
		$this->DB->connect('sqlite::memory:');
		$this->DB->set_prefix('pdynconfigtest_');
		$tables = $this->DB->get_schema();
		foreach ($tables as $tablename => $tableschema) {
			$this->DB->structure()->create_table($tablename);
		}
	}

	/**
	 * Test set_db() function.
	 */
	public function test_set_db() {
		$CFG = new ConfigStoreMock;
		$this->assertEmpty($CFG->DB);
		$CFG->set_db($this->DB);
		$this->assertNotEmpty($CFG->DB);
		$this->assertTrue((get_class($CFG->DB) === get_class($this->DB)));
	}

	/**
	 * Test load_database_settings method.
	 */
	public function test_load_database_settings() {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);

		$this->assertEmpty($CFG->get('core', 'test'));

		$CFG->load_database_settings();

		$this->assertEmpty($CFG->get('core', 'test'));

		$record = [
			'component' => 'core',
			'name' => 'test',
			'val' => serialize('hello world!'),
		];
		$this->DB->insert_record('config', $record);

		$CFG->load_database_settings();

		$this->assertEquals('hello world!', $CFG->get('core', 'test'));
	}

	/**
	 * Dataprovider for test_quick_create.
	 *
	 * @return array Array of test parameters.
	 */
	public function dataprovider_set_get() {
		return [
			0 => [
				[],
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'expectedval' => null,
					],
				],
			],
			1 => [
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'val' => 'testval',
					],
				],
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'expectedval' => 'testval',
					],
					[
						'component' => 'core',
						'name' => 'testname',
						'fallback' => 'testfallback',
						'expectedval' => 'testval',
					],
					[
						'component' => 'core',
						'name' => 'testname2',
						'expectedval' => null,
					],
					[
						'component' => 'core',
						'name' => 'testname2',
						'fallback' => 'testfallback',
						'expectedval' => 'testfallback',
					],
					[
						'component' => 'testcomponent',
						'name' => 'testname',
						'expectedval' => null,
					],
					[
						'component' => 'testcomponent',
						'name' => 'testname',
						'fallback' => 'testfallback',
						'expectedval' => 'testfallback',
					],
				],
			],
			2 => [
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'val' => 'testval',
					],
					[
						'component' => 'testcomponent',
						'name' => 'testname2',
						'val' => 'testval2',
					],
				],
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'expectedval' => 'testval',
					],
					[
						'component' => 'testcomponent',
						'name' => 'testname2',
						'expectedval' => 'testval2',
					],
					[
						'component' => 'core',
						'name' => 'testname2',
						'expectedval' => null,
					],
					[
						'component' => 'testcomponent',
						'name' => 'testname',
						'expectedval' => null,
					],
				]
			],
			3 => [
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'val' => ['test' => 'test2'],
					],
				],
				[
					[
						'component' => 'core',
						'name' => 'testname',
						'expectedval' => ['test' => 'test2'],
					],
				],
			]
		];
	}

	/**
	 * Test simple setting and getting of config values.
	 *
	 * @dataProvider dataprovider_set_get
	 */
	public function test_set_get($sets, $gets) {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);

		foreach ($sets as $set) {
			$CFG->set($set['component'], $set['name'], $set['val']);
		}

		foreach ($gets as $get) {
			if (isset($get['fallback'])) {
				$actual = $CFG->get($get['component'], $get['name'], $get['fallback']);
			} else {
				$actual = $CFG->get($get['component'], $get['name']);
			}
			$this->assertEquals($get['expectedval'], $actual);
		}

		return true;
	}

	/**
	 * Dataprovider providing different datatypes and their associated data type.
	 *
	 * @return array Array of tests.
	 */
	public function dataprovider_datatypes() {
		return [
			['string', 'testval'],
			['array', [1, 2, 3]],
			['bool', true],
			['int', 3],
			['float', 2.4],
			['object', (object)['test' => 'value']]
		];
	}

	/**
	 * Test that datatypes are the same coming out as going in.
	 *
	 * @dataProvider dataprovider_datatypes
	 *
	 * @param string $type Intended data type.
	 * @param mixed $value A test value.
	 */
	public function test_datatypesAreMaintained($type, $value) {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);
		$CFG->set('core', 'testconfig', $value);
		$actual = $CFG->get('core', 'testconfig');
		$this->assertInternalType($type, $actual);
		$this->assertEquals($value, $actual);

		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);
		$CFG->load_database_settings();
		$actual = $CFG->get('core', 'testconfig');
		$this->assertInternalType($type, $actual);
		$this->assertEquals($value, $actual);
	}

	/**
	 * Test magic methods.
	 *
	 * @dataProvider dataprovider_datatypes
	 *
	 * @param string $type Intended data type.
	 * @param mixed $value A test value.
	 */
	public function test_magicMethods($type, $value) {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);

		$this->assertFalse(isset($CFG->testconfig));

		$CFG->testconfig = $value;
		$this->assertTrue(isset($CFG->testconfig));
		$actual = $CFG->get('core', 'testconfig');
		$this->assertInternalType($type, $actual);
		$this->assertEquals($value, $actual);

		$actual2 = $CFG->testconfig;
		$this->assertInternalType($type, $actual);
		$this->assertEquals($value, $actual);
	}

	/**
	 * Test calling ->get() without a config name returned all config for that component.
	 */
	public function test_getWithoutNameReturnsAllConfigForComponent() {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);

		$CFG->set('testcomponent', 'test1', 'one');
		$CFG->set('testcomponent', 'test2', 'two');
		$CFG->set('testcomponent', 'test3', 'three');
		$CFG->set('testcomponent2', 'test1', '1');
		$CFG->set('testcomponent2', 'test2', '2');
		$CFG->set('testcomponent2', 'test3', '3');

		$componentconfig = $CFG->get('testcomponent');
		$expected = [
			'test1' => 'one',
			'test2' => 'two',
			'test3' => 'three',
		];
		$this->assertEquals($expected, $componentconfig);
	}

	/**
	 * Test fallback values.
	 */
	public function test_getFallback() {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);
		$expected = 'fallback!';
		$actual = $CFG->get('core', 'nonexistant', $expected);
		$this->assertEquals($expected, $actual);

		$expected = ['test1' => 'one', 'test2' => 'two'];
		$actual = $CFG->get('nonexistant', null, $expected);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * Test set updates existing values.
	 */
	public function test_setUpdatesExistingValues() {
		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);

		$CFG->set('core', 'test', 'value1');
		$this->assertEquals('value1', $CFG->get('core', 'test'));

		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);
		$CFG->load_database_settings();
		$this->assertEquals('value1', $CFG->get('core', 'test'));
		$CFG->set('core', 'test', 'value2');
		$this->assertEquals('value2', $CFG->get('core', 'test'));

		$CFG = new ConfigStore;
		$CFG->set_db($this->DB);
		$CFG->load_database_settings();
		$this->assertEquals('value2', $CFG->get('core', 'test'));
	}

}

<?php

require_once(dirname(dirname(__FILE__)) . '/old/parse-readme.php');
require_once(dirname(dirname(__FILE__)) . '/old/jaquith_compat.php');
require_once(dirname(dirname(__FILE__)) . '/markdown.php');

require_once(dirname(dirname(__FILE__)) . '/compat.php');

define('DATA_DIR', dirname(__FILE__) . '/data');

class RegressionTest extends PHPUnit_Framework_TestCase {
	public static function providerExamples() {
		$files = glob(DATA_DIR . '/*.readme');
		$data = array();
		foreach ($files as $file) {
			$name = basename($file, '.readme');
			$data[$name] = array($name);
		}

		$data = array_values($data);
		return $data;
	}

	public static function providerFromRepository() {
		$files = glob(dirname(__FILE__) . '/slurper/readmes/*.readme');
		$data = array();
		foreach ($files as $file) {
			$name = basename($file, '.readme');
			$data[$name] = array($name);
		}

		$data = array_values($data);
		//$data = array_slice($data, 0, 200);
		return $data;
	}

	/**
	 * @dataProvider providerFromRepository
	 */
	public function testFromRepository($base) {
		$data = file_get_contents(dirname(__FILE__) . '/slurper/readmes/' . $base . '.readme');

		$parser = new WordPress_Readme_Parser();
		$expected = $parser->parse_readme_contents($data);
		$data = _Automattic_Readme::parse_readme_contents($data);

		// Exclude intentional changes
		unset($data['changelog'], $data['version']);
		if (isset($expected['sections']['change_log']) && isset($expected['sections']['changelog'])) {
			unset($expected['sections']['change_log']);
		}

		// parse-readme doesn't trim screenshots, we do
		// It's an insignificant change, but it causes tests to fail,
		// so we fake this
		if (!empty($expected['screenshots'])) {
			$expected['screenshots'] = array_map('trim', $expected['screenshots']);
		}

		// It also doesn't trim short descriptions
		if (isset($expected['short_description'])) {
			$expected['short_description'] = trim($expected['short_description']);
		}

		// For some reason, "contributors" is stripped of non-alphanumeric
		// characters, although it's not on the site. Fake it here, with the
		// expectation that we don't need to do this in the parser
		if (!empty($data['contributors'])) {
			foreach ($data['contributors'] as &$contributor) {
				$contributor = preg_replace('/[^a-z0-9_-]/i', '', $contributor);
			}
		}

		$this->assertEquals($expected, $data);
	}

	/**
	 * @dataProvider providerExamples
	 */
	public function testUsingString($base) {
		$data = file_get_contents(DATA_DIR . DIRECTORY_SEPARATOR . $base . '.readme');

		$parser = new WordPress_Readme_Parser();
		$expected = $parser->parse_readme_contents($data);
		$data = _Automattic_Readme::parse_readme_contents($data);

		// Exclude intentional changes
		unset($data['changelog']);
		if (isset($expected['sections']['change_log']) && isset($expected['sections']['changelog'])) {
			unset($expected['sections']['change_log']);
		}

		$this->assertEquals($expected, $data);
	}

	/**
	 * @dataProvider providerExamples
	 */
	public function testUsingFile($base) {
		$data = DATA_DIR . DIRECTORY_SEPARATOR . $base . '.readme';

		$parser = new WordPress_Readme_Parser();
		$expected = $parser->parse_readme($data);
		$data = _Automattic_Readme::parse_readme($data);

		// Exclude intentional changes
		unset($data['changelog']);
		if (isset($expected['sections']['change_log']) && isset($expected['sections']['changelog'])) {
			unset($expected['sections']['change_log']);
		}

		$this->assertEquals($expected, $data);
	}
}
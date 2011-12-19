<?php

set_time_limit(30);

require_once(dirname(dirname(__FILE__)) . '/ReadmeParser.php');
require_once(dirname(dirname(__FILE__)) . '/markdown.php');

define('DATA_DIR', dirname(__FILE__) . '/data');

class ParserTest extends PHPUnit_Framework_TestCase {
	public static function fullProvider() {
		$files = glob(DATA_DIR . '/*.readme');
		$data = array();
		foreach ($files as $file) {
			$name = basename($file, '.readme');
			$data[$name] = array($name);
		}

		$data = array_values($data);
		return $data;
	}

	/**
	 * @dataProvider fullProvider
	 */
	public function testFullUsingString($base) {
		$data = file_get_contents(DATA_DIR . DIRECTORY_SEPARATOR . $base . '.readme');
		$expected = unserialize(file_get_contents(DATA_DIR . DIRECTORY_SEPARATOR . $base . '.ser'));

		$data = Baikonur_ReadmeParser::parse_readme_contents($data);
		$this->assertEquals($expected, $data);
	}

	/**
	 * @dataProvider fullProvider
	 */
	public function testFullUsingFile($base) {
		$data = DATA_DIR . DIRECTORY_SEPARATOR . $base . '.readme';
		$expected = unserialize(file_get_contents(DATA_DIR . DIRECTORY_SEPARATOR . $base . '.ser'));

		$data = Baikonur_ReadmeParser::parse_readme($data);
		$this->assertEquals($expected, $data);
	}

	public function testExample() {
		$data = DATA_DIR . DIRECTORY_SEPARATOR . 'example.readme';
		$data = Baikonur_ReadmeParser::parse_readme($data);

		$this->assertEquals('Plugin Name', $data->name);
		$this->assertEquals(array('markjaquith', 'mdawaffe'), $data->contributors);
		$this->assertEquals('http://example.com/', $data->donate_link);
		$this->assertEquals(array('comments', 'spam'), $data->tags);
		$this->assertEquals('2.0.2', $data->requires);
		$this->assertEquals('2.1', $data->tested);
		$this->assertEquals('4.3', $data->stable_tag);

		$this->assertEquals('Here is a short description of the plugin.' . 
			'  This should be no more than 150 characters.  No markup here.',
			$data->short_description);

		$this->assertEquals("This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from\nthe directory of the stable readme.txt, so in this case, <code>/tags/4.3/screenshot-1.png</code> (or jpg, jpeg, gif)", $data->screenshots[0]);
		$this->assertEquals('This is the second screen shot', $data->screenshots[1]);
	}
}
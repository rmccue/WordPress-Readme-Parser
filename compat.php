<?php

if ( !defined('WORDPRESS_README_MARKDOWN') ) {
	if ( defined('AUTOMATTIC_README_MARKDOWN') )
		define( 'WORDPRESS_README_MARKDOWN', AUTOMATTIC_README_MARKDOWN );
	else
		define('WORDPRESS_README_MARKDOWN', dirname(__FILE__) . '/markdown.php');
}

if (!class_exists('Markdown')) {
	require_once(WORDPRESS_README_MARKDOWN);
}

class Automattic_Readme extends Baikonur_ReadmeParser {
	public static function parse_readme_contents($contents) {
		$result = parent::parse_readme_contents($contents);
		return (array) $result;
	}
}
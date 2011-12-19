<?php
/**
 * Custom readme parser
 *
 * Based on Automattic_Readme from http://code.google.com/p/wordpress-plugin-readme-parser/
 *
 * Relies on Markdown_Extra
 *
 * @todo Handle screenshots section properly
 * @todo Create validator for this based on http://code.google.com/p/wordpress-plugin-readme-parser/source/browse/trunk/validator.php
 */
class Baikonur_ReadmeParser {

	public static function parse_readme($file) {
		$contents = file($file);
		return self::parse_readme_contents($contents);
	}

	public static function parse_readme_contents($contents) {
		if (is_string($contents)) {
			$contents = explode("\n", $contents);
		}

		$contents = array_map('trim', $contents);

		$data = new stdClass;
		$data->is_excerpt = false;
		$data->is_truncated = false;

		$data->name = array_shift($contents);
		$data->name = trim($data->name, "#= ");

		// Parse headers
		$headers = array();

		while (($line = array_shift($contents)) !== false) {
			if (empty($line)) {
				break;
			}

			list($key, $value) = explode(':', $line, 2);
			$key = strtolower(str_replace(array(' ', "\t"), '_', trim($key)));
			$headers[$key] = trim($value);
		}

		if (!empty($headers['tags'])) {
			$data->tags = explode(',', $headers['tags']);
			$data->tags = array_map('trim', $data->tags);
		}
		if (!empty($headers['requires'])) {
			$data->requires = $headers['requires'];
		}
		if (!empty($headers['tested'])) {
			$data->tested = $headers['tested'];
		}
		if (!empty($headers['contributors'])) {
			$data->contributors = explode(',', $headers['contributors']);
			$data->contributors = array_map('trim', $data->contributors);
		}
		if (!empty($headers['stable_tag'])) {
			$data->stable_tag = $headers['stable_tag'];
		}
		if (!empty($headers['donate_link'])) {
			$data->donate_link = $headers['donate_link'];
		}

		// Parse the short description
		$data->short_description = '';

		while (($line = array_shift($contents)) !== null) {
			if (empty($line)) {
				$data->short_description .= "\n";
				continue;
			}
			if ($line[0] === '=' && $line[1] === '=') {
				array_unshift($contents, $line);
				break;
			}

			$data->short_description .= $line . "\n";
		}
		$data->short_description = trim($data->short_description);

		// Parse the rest of the body

		$data->sections = array();
		$current = '';
		$special = array('description', 'installation', 'faq', 'frequently_asked_questions', 'screenshots', 'changelog');

		while (($line = array_shift($contents)) !== null) {
			if (empty($line)) {
				$current .= "\n";
				continue;
			}

			if ($line[0] === '=' && $line[1] === '=') {
				if (!empty($current)) {
					$data->sections[$title] = trim($current);
				}

				$current = '';
				$real_title = strtolower(trim($line, "#= "));
				$title = str_replace(' ', '_', $real_title);
				if (!in_array($title, $special)) {
					$current .= '<h3>' . $real_title . "</h3>\n";
				}
				continue;
			}

			$current .= $line . "\n";
		}

		$data->sections[$title] = trim($current);
		$title = null;
		$current = null;

		if (empty($data->sections['description'])) {
			$data->sections['description'] = self::parse_markdown($data->short_description);
		}
		if (empty($data->sections['faq']) && !empty($data->sections['frequently_asked_questions'])) {
			$data->sections['faq'] = $data->sections['frequently_asked_questions'];
			unset($data->sections['frequently_asked_questions']);
		}

		// Parse changelog
		$data->changelog = array();

		if (!empty($data->sections['changelog'])) {
			$lines = explode("\n", $data->sections['changelog']);
			while (($line = array_shift($lines)) !== null) {
				if (empty($line)) {
					continue;
				}

				if ($line[0] === '=') {
					if (!empty($current)) {
						$data->changelog[$title] = trim($current);
					}

					$current = '';
					$title = trim($line, "#= ");
					continue;
				}

				$current .= $line . "\n";
			}

			$data->changelog[$title] = trim($current);
		}

		$data->screenshots = array();
		if (isset($data->sections['screenshots'])) {
			preg_match_all('#(?:\*|[0-9]+\.)(.*)#i', $data->sections['screenshots'], $screenshots, PREG_SET_ORDER);
			if ($screenshots) {
				foreach ((array) $screenshots as $ss) {
					$data->screenshots[] = trim($ss[1]);
				}
			}
		}

		// Markdownify!

		$data->sections = array_map(array(__CLASS__, 'parse_markdown'), $data->sections);
		$data->changelog = array_map(array(__CLASS__, 'parse_markdown'), $data->changelog);

		// Rearrange stuff

		$data->remaining_content = $data->sections;
		$data->sections = array();

		foreach ($special as $spec) {
			if (!empty($data->remaining_content[$spec])) {
				$data->sections[$spec] = $data->remaining_content[$spec];
				unset($data->remaining_content[$spec]);
			}
		}

		if (isset($data->remaining_content['upgrade_notice'])) {
			$data->upgrade_notice = $data->remaining_content['upgrade_notice'];
		}

		return $data;
	}

	protected static function parse_markdown($text) {
		$text = preg_replace('/^[\s]*=[\s]+(.+?)[\s]+=/m', "\n" . '<h4>$1</h4>' . "\n", $text);
		$text = Markdown(trim($text));
		return trim($text);
	}
}

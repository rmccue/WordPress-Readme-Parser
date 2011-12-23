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

		//$contents = array_map('rtrim', $contents);

		$data = new stdClass;

		// Defaults
		$data->is_excerpt = false;
		$data->is_truncated = false;
		$data->tags = array();
		$data->requires = '';
		$data->tested = '';
		$data->contributors = array();
		$data->stable_tag = '';
		$data->donate_link = '';
		$data->short_description = '';
		$data->sections = array();
		$data->changelog = array();
		$data->upgrade_notice = array();
		$data->screenshots = array();
		$data->remaining_content = array();

		$data->name = array_shift($contents);
		$data->name = trim($data->name, "#= ");

		// Parse headers
		$headers = array();

		while (($line = array_shift($contents)) !== null && ($line = trim($line)) && !empty($line)) {
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
		if (!empty($headers['requires_at_least'])) {
			$data->requires = $headers['requires_at_least'];
		}
		if (!empty($headers['tested'])) {
			$data->tested = $headers['tested'];
		}
		if (!empty($headers['tested_up_to'])) {
			$data->tested = $headers['tested_up_to'];
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
		while (($line = array_shift($contents)) !== null) {
			$trimmed = trim($line);
			if (empty($trimmed)) {
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
		$current = '';
		$special = array('description', 'installation', 'faq', 'frequently_asked_questions', 'screenshots', 'changelog', 'upgrade_notice');

		while (($line = array_shift($contents)) !== null) {
			$trimmed = trim($line);
			if (empty($trimmed)) {
				$current .= "\n";
				continue;
			}

			if ($line[0] === '=' && $line[1] === '=') {
				if (!empty($current)) {
					$data->sections[$title] = trim($current);
				}

				$current = '';
				$real_title = trim($line, "#= ");
				$title = strtolower(str_replace(' ', '_', $real_title));
				if (!in_array($title, $special)) {
					$current .= '<h3>' . $real_title . "</h3>\n";
				}
				continue;
			}

			$current .= $line . "\n";
		}

		if (!empty($title) && !empty($current)) {
			$data->sections[$title] = trim($current);
		}
		$title = null;
		$current = null;

		if (empty($data->sections['description'])) {
			$data->sections['description'] = self::parse_markdown($data->short_description);
		}
		if (empty($data->sections['frequently_asked_questions']) && !empty($data->sections['faq'])) {
			$data->sections['frequently_asked_questions'] = $data->sections['faq'];
			unset($data->sections['faq']);
		}

		// Parse changelog
		if (!empty($data->sections['changelog'])) {
			$lines = explode("\n", $data->sections['changelog']);
			while (($line = array_shift($lines)) !== null) {
				$trimmed = trim($line);
				if (empty($trimmed)) {
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
		$title = null;
		$current = null;

		if (!empty($data->sections['upgrade_notice'])) {
			$lines = explode("\n", $data->sections['upgrade_notice']);
			while (($line = array_shift($lines)) !== null) {
				$trimmed = trim($line);
				if (empty($trimmed)) {
					continue;
				}

				if ($line[0] === '=') {
					if (!empty($current)) {
						$data->upgrade_notice[$title] = trim($current);
					}

					$current = '';
					$title = trim($line, "#= ");
					continue;
				}

				$current .= $line . "\n";
			}

			$data->upgrade_notice[$title] = trim($current);
			unset($data->sections['upgrade_notice']);
		}

		// Markdownify!

		$data->sections = array_map(array(__CLASS__, 'parse_markdown'), $data->sections);
		$data->changelog = array_map(array(__CLASS__, 'parse_markdown'), $data->changelog);
		$data->upgrade_notice = array_map(array(__CLASS__, 'parse_markdown'), $data->upgrade_notice);

		if (isset($data->sections['screenshots'])) {
			preg_match_all('#<li>(.*?)</li>#is', $data->sections['screenshots'], $screenshots, PREG_SET_ORDER);
			if ($screenshots) {
				foreach ((array) $screenshots as $ss) {
					$data->screenshots[] = trim($ss[1]);
				}
			}
		}

		// Rearrange stuff

		$data->remaining_content = $data->sections;
		$data->sections = array();

		foreach ($special as $spec) {
			if (!empty($data->remaining_content[$spec])) {
				$data->sections[$spec] = $data->remaining_content[$spec];
				unset($data->remaining_content[$spec]);
			}
		}

		return $data;
	}

	protected static function parse_markdown($text) {
		$text = self::code_trick($text);
		$text = preg_replace('/^[\s]*=[\s]+(.+?)[\s]+=/m', "\n" . '<h4>$1</h4>' . "\n", $text);
		$text = Markdown(trim($text));
		return trim($text);
	}

	protected static function code_trick($text) {
		// If doing markdown, first take any user formatted code blocks and turn them into backticks so that
		// markdown will preserve things like underscores in code blocks
		$text = preg_replace_callback("!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s", array(__CLASS__, 'decodeit'), $text);

		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		// Markdown can do inline code, we convert bbPress style block level code to Markdown style
		$text = preg_replace_callback("!(^|\n)([ \t]*?)`(.*?)`!s", array(__CLASS__, 'indent'), $text);
		return $text;
	}

	protected static function indent($matches) {
		$text = $matches[3];
		$text = preg_replace('|^|m', $matches[2] . '    ', $text);
		return $matches[1] . $text;
	}

	protected static function decodeit($matches) {
		$text = $matches[2];
		$trans_table = array_flip(get_html_translation_table(HTML_ENTITIES));
		$text = strtr($text, $trans_table);
		$text = str_replace('<br />', '', $text);
		$text = str_replace('&#38;', '&', $text);
		$text = str_replace('&#39;', "'", $text);
		if ( '<pre><code>' == $matches[1] )
			$text = "\n$text\n";
		return "`$text`";
	}
}

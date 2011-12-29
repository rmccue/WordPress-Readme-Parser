<?php
/**
 * Handles the validation and output of the readme validator
 *
 * Based on work by Mark Jaquith
 * @link https://github.com/markjaquith/WordPress-Plugin-Readme-Parser/blob/master/validator.php
 *
 * @package   WordPress\Readme\Validator
 * @author    Gary Jones <gary@garyjones.co.uk>
 * @copyright Copyright (c) 2012, Gary Jones
 * @license   http://opensource.org/licenses/gpl-3.0.php GPL v3
 * @link      https://github.com/GaryJones/WordPress-Readme-Parser
 */

require 'markdown.php';
require 'ReadmeParser.php';

/**
 * Handles the validation and output of the readme validator.
 *
 * Relies on a parser (Baikonur_ReadmeParser or child class), which in turn is
 * likely to rely on Mardown Extra.
 *
 * @author Gary Jones <gary@garyjones.co.uk>
 */
class Readme_Validator {

	/**
	 * Parsed readme object.
	 *
	 * @var stdClass
	 */
	private $readme;

	/**
	 * Full readme contents.
	 *
	 * @var string
	 */
	private $readme_contents;

	/**
	 * Collection of fatal errors.
	 *
	 * @var array
	 */
	private $fatal_errors = array();

	/**
	 * Collection of warnings.
	 *
	 * @var array
	 */
	private $warnings = array();

	/**
	 * Collection of notes.
	 *
	 * @var array
	 */
	private $notes = array();

	/**
	 * The document title.
	 *
	 * @var string
	 */
	private $title = 'WordPress / bbPress Plugin readme.txt Validator';

	/**
	 * Gets the readme contents and parses it using hte supplied class and
	 * stores it in a property.
	 *
	 * The readme contents are grabbed either from the posted textarea, or from
	 * the URL that was posted.
	 *
	 * @param object $readme_parser Instance of a readme parser.
	 */
	public function __construct( $readme_parser ) {

		// URL was posted
		if ( $_POST['readme_url'] ) {
			$url = stripslashes( $_POST['readme_url'] );
			/** @todo More tidying up here */

			// Check the URl ended with the correct filename
			if ( strtolower( substr( $url, -10, 10 ) ) !== 'readme.txt' ) {
				$this->title = 'Validator Error!';
				$this->show_head();
				die( 'URL must end in <code>readme.txt</code>!' );
			}

			// Grab the contents from the URL
			if ( ! $readme_contents = file_get_contents( $url ) ) {
				$this->title = 'Validator Error!';
				$this->show_head();
				die( 'Invalid readme.txt URL' );
			}

			$this->readme_contents = $readme_contents;
		} elseif ( $_POST['readme_contents'] ) {
			// Grab the contents directly from the posted textarea
			$this->readme_contents = stripslashes( $_POST['readme_contents'] );
		}

		if ( $this->readme_contents ) {
			// We have readme contents, so parse it.
			$this->readme = $readme_parser->parse_readme_contents( $this->readme_contents );
			$this->title = 'Readme Validator Results';
		}

	}

	/**
	 * Checks the readme property object for missing values and flags, and sets
	 * items in issue arrays accordingly.
	 *
	 * @return null Returns early when there's no readme to parse.
	 */
	protected function validate() {

		if ( ! $this->readme )
			return;

		// Fatal errors
		if ( ! $this->readme->name )
			$fatal_errors[] = 'No plugin name detected.  Plugin names look like: <code>=== Plugin Name ===</code>';

		// Warnings
		if ( ! $this->readme->requires )
			$this->warnings[] = '<code>Requires at least</code> is missing';
		if ( ! $this->readme->tested )
			$this->warnings[] = '<code>Tested up to</code> is missing';
		if ( ! $this->readme->stable_tag )
			$this->warnings[] = '<code>Stable tag</code> is missing.  Hint: If you treat <code>/trunk/</code> as stable, put <code>Stable tag: trunk</code>';
		if ( ! count( $this->readme->contributors ) )
			$this->warnings[] = 'No <code>Contributors</code> listed';
		if ( ! count( $this->readme->tags ) )
			$this->warnings[] = 'No <code>Tags</code> specified';
		if ( $this->readme->is_excerpt )
			$this->warnings[] = 'No <code>== Description ==</code> section was found... your short description section will be used instead';
		if ( $this->readme->is_truncated )
			$this->warnings[] = 'Your short description exceeds the 150 character limit';

		// Notes
		if ( ! $this->readme->sections['installation'] )
			$this->notes[] = 'No <code>== Installation ==</code> section was found';
		if ( ! $this->readme->sections['frequently_asked_questions'] )
			$this->notes[] = 'No <code>== Frequently Asked Questions ==</code> section was found';
		if ( ! $this->readme->sections['changelog'] )
			$this->notes[] = 'No <code>== Changelog ==</code> section was found';
		if ( ! $this->readme->upgrade_notice )
			$this->notes[] = 'No <code>== Upgrade Notice ==</code> section was found';
		if ( $this->readme->changelog_unversioned_line )
			$this->notes[] = 'One or more changelog lines were not under a version subheading';
		if ( $this->readme->upgrade_notice_unversioned_line )
			$this->notes[] = 'One or more upgrade notice lines were not under a version subheading';
		if ( ! $this->readme->sections['screenshots'] )
			$this->notes[] = 'No <code>== Screenshots ==</code> section was found';
		if ( ! $this->readme->donate_link )
			$this->notes[] = 'No donate link was found';

	}

	/**
	 * Shows the notice (errors or success) at the top of the page.
	 *
	 * @return null Returns early when there's no readme to parse.
	 */
	protected function show_result() {

		if ( ! $this->readme )
			return;

		if ( $this->fatal_errors ) {
			echo '<div class="fatal notice"><p>Fatal Error:</p>' . "\n<ul>\n";
			foreach ( $this->fatal_errors as $e )
				echo "<li>$e</li>\n";
			echo "</ul>\n</div>";
			return; // no point staying
		}

		if ( $this->warnings ) {
			echo '<div class="warning notice"><p>Warnings:</p>' . "\n<ul>\n";
			foreach ( $this->warnings as $e )
				echo "<li>$e</li>\n";
			echo "</ul>\n</div>";
		}

		if ( $this->notes ) {
			echo '<div class="note notice"><p>Notes:</p>' . "\n<ul>\n";
			foreach ( $this->notes as $e )
				echo "<li>$e</li>\n";
			echo "</ul>\n</div>";
		}

		if ( ! $this->notes && ! $this->warnings && ! $this->fatal_errors )
			echo '<div class="success"><p>Your <code>readme.txt</code> rocks. Seriously. Flying colors.</p></div>' . "\n";
		else
			echo '<a href="#edit">Edit Your Readme File</a>' . "\n";

		echo '<hr />';

	}

	/**
	 * Echo the readme contents.
	 *
	 * @return null Returns early when there's no readme to parse
	 */
	protected function show_readme() {

		if ( ! $this->readme )
			return;

		?>
		<h1><?php echo $this->readme->name; ?></h1>

		<p><em><?php echo $this->readme->short_description; ?></em></p>

		<hr />

		<p>
			<strong>Contributors:</strong> <?php echo implode( ', ', $this->readme->contributors ); ?><br />
			<strong>Donate link:</strong> <?php echo $this->readme->donate_link; ?><br />
			<strong>Tags:</strong> <?php echo implode( ', ', $this->readme->tags ); ?><br />
			<strong>Requires at least:</strong> <?php echo $this->readme->requires; ?><br />
			<strong>Tested up to:</strong> <?php echo $this->readme->tested; ?><br />
			<strong>Stable tag:</strong> <?php echo $this->readme->stable_tag; ?>
		</p>

		<hr />

		<?php foreach ( $this->readme->sections as $title => $section ) : ?>
			<h3><?php echo ucwords( str_replace( '_', ' ', $title ) ); ?></h3>
			<?php echo function_exists( 'apply_filters' ) ? apply_filters( 'validator_section', $section ) : $section; ?>
			<hr />
		<?php endforeach; ?>

		<h3>Upgrade Notice</h3>
		<dl>
			<?php foreach ( $this->readme->upgrade_notice as $version => $notice ) : ?>
				<dt><?php echo $version; ?></dt>
				<dd><?php echo $notice; ?></dd>
			<?php endforeach; ?>
		</dl>

		<?php
		foreach ( $this->readme->remaining_content as $title => $section )
			echo "\n<hr />\n\n" . $section;
		echo "\n<hr />\n\n";
		echo '<h2 id="edit">Edit Your Readme File</h2>' . "\n";

	}

	/**
	 * Echo the markup for the page, upto and include the body tag.
	 *
	 * Include an internal style sheet, so that no external resources are needed.
	 *
	 * Outputs with a HTML5 doctype.
	 */
	protected function show_head() {

		?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8" />
		<title><?php echo $this->title; ?></title>
	</head>
	<style>
		body {
			font-family: 'Lucida Grande', Verdana, sans-serif;
		}

		code {
			font-size: 1.3em
		}

		.success {
			background: #0f0;
			border: 3px solid #0d0;
			margin: 0 auto;
			padding: 1px 10px;
			width: 50%;
		}

		.notice {
			margin: 30px auto;
			padding: 1px 10px;
		}

		.notice p {
			font-weight: bold;
		}

		.notice ul {
			list-style: square;
		}

		div.fatal {
			background: #faa;
			border: 3px solid #d00;
		}

		div.warning {
			background: #f60;
			border: 3px solid #e40;
		}

		div.note {
			background: #5cf;
			border: 3px solid #3ad;
		}
	</style>
	<body id="top">
	<?php

	}

	/**
	 * Show the form(s) for the page.
	 *
	 * On the first page load, two forms are shown - one for a URL input, the
	 * other for a textarea input.
	 *
	 * After submission, only the second form (textarea) is shown, populated
	 * with the contents of the readme that was parsed.
	 */
	protected function show_form() {

		if ( ! $this->readme ) {
			?>
			<p>Enter the <label for="readme_url"><abbr title="uniform resource location">URL</abbr> to your <code>readme.txt</code></label> file:</p>
			<form action="#top" method="post">
				<p><input id="readme_url" name="readme_url" size="70" type="text" placeholder="http://" /> <input type="submit" value="Validate!" /></p>
			</form>

			<p>&#x2026; or paste your <code>readme.txt</code> here:</p>
			<?php
		}
		?>
		<form action="#top" method="post">
			<textarea rows="20" cols="100" name="readme_contents"><?php echo $this->readme_contents; // WP (needs escaping) ?></textarea>
			<p><input type="submit" value="Validate!" /></p>
		</form>
		<?php

	}

	/**
	 * Do the output for this page.
	 *
	 * With the readme and readme_contents properties already potentially
	 * populated within the constructor of this class, this method calls for the
	 * validation to be done, then start echoing output with the head, the
	 * result notice (if any), a copy of the readme (if any) and the form(s),
	 * before finally echoing the closing markup.
	 */
	public function show_page() {

		$this->validate();
		$this->show_head();
		$this->show_result();
		$this->show_readme();
		$this->show_form();
		?>
		</body>
</html>
	<?php

	}

}

// That's the class sorted - now lets populate the page
$readme_validator = new Readme_Validator( new Baikonur_ReadmeParser );
$readme_validator->show_page();

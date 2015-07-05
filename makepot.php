<?php
require_once 'not-gettexted.php';
require_once 'extract/extract.php';

if ( !defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}

class MakePOT {
	var $max_header_lines = 30;

	var $projects = array(
		'generic',
	);

	var $rules = array(
		'gT' => array('string'),
		'eT' => array('string'),
		'ngT' => array('singular', 'plural'),
		'neT' => array('singular', 'plural'),
	);

	var $excludes = array( 'framework/*', 'templates/*', 'tmp/*', 'upload/*', 'style/*', 'scripts/*', 'locale/*', 'installer/*', 'images/*', 'fonts/*', 'docs/*' );

	var $meta = array(
		'default' => array(
			'from-code' => 'utf-8',
			'msgid-bugs-address' => 'http://translate.limesurvey.org/',
			'language' => 'php',
			'add-comments' => 'translators',
			'comments' => "{package-name} LANGUAGE FILE.\nCopyright (C) {year} {package-name}\nThis file is distributed under the same license as the {package-name} package.",
			'package-name' => 'LimeSurvey',
			'package-version' => 'language file',


		),
		'generic' => array(),
	);

	function __construct($deprecated = true) {
		$this->extractor = new StringExtractor( $this->rules );
	}

	function realpath_missing($path) {
		return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
	}

	function xgettext($project, $dir, $output_file, $placeholders = array(), $excludes = array(), $includes = array()) {
		$meta = array_merge( $this->meta['default'], $this->meta[$project] );
		$placeholders = array_merge( $meta, $placeholders );
		$meta['output'] = $this->realpath_missing( $output_file );
		$placeholders['year'] = date( 'Y' );
		$placeholder_keys = array_map( create_function( '$x', 'return "{".$x."}";' ), array_keys( $placeholders ) );
		$placeholder_values = array_values( $placeholders );
		foreach($meta as $key => $value) {
			$meta[$key] = str_replace($placeholder_keys, $placeholder_values, $value);
		}

		$originals = $this->extractor->extract_from_directory( $dir, $excludes, $includes );
		$pot = new PO;
		$pot->entries = $originals->entries;

		$pot->set_header( 'Project-Id-Version', $meta['package-name'].' '.$meta['package-version'] );
		$pot->set_header( 'Report-Msgid-Bugs-To', $meta['msgid-bugs-address'] );
		$pot->set_header( 'POT-Creation-Date', gmdate( 'Y-m-d H:i:s+00:00' ) );
		$pot->set_header( 'MIME-Version', '1.0' );
		$pot->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$pot->set_header( 'Content-Transfer-Encoding', '8bit' );
		$pot->set_header( 'PO-Revision-Date', date('Y') . '-MO-DA HO:MI+ZONE' );
		$pot->set_header( 'Last-Translator', 'FULL NAME <EMAIL@ADDRESS>' );
		$pot->set_header( 'Language-Team', 'LimeSurvey Team <c_schmitz@users.sourceforge.net>' );

		// Custom poedit headers
		$pot->set_header( 'X-Poedit-KeywordsList', 'gT;ngt:1,2;eT;neT:1,2' );
		$pot->set_header( 'X-Poedit-SourceCharset', 'utf-8' );
		$pot->set_header( 'X-Poedit-Basepath', '..\\..\\..\\' );
		$pot->set_header( 'X-Poedit-SearchPath-0', '.' );

		$pot->set_comment_before_headers( $meta['comments'] );
		$pot->export_to_file( $output_file );
		return true;
	}

	function ls_generic($dir, $args) {
		$defaults = array(
			'project' => 'ls-core',
			'output' => null,
			'default_output' => 'limesurvey.pot',
			'includes' => array(),
			'excludes' => $this->excludes,
			'extract_not_gettexted' => false,
			'not_gettexted_files_filter' => false,
		);
		$args = array_merge( $defaults, $args );
		extract( $args );
		$placeholders = array();
		$placeholders['version'] = '2.0';
		$output = is_null( $output )? $default_output : $output;
		$res = $this->xgettext( $project, $dir, $output, $placeholders, $excludes, $includes );
		if ( !$res ) return false;

		if ( $extract_not_gettexted ) {
			$old_dir = getcwd();
			$output = realpath( $output );
			chdir( $dir );
			$php_files = NotGettexted::list_php_files('.');
			$php_files = array_filter( $php_files, $not_gettexted_files_filter );
			$not_gettexted = new NotGettexted;
			$res = $not_gettexted->command_extract( $output, $php_files );
			chdir( $old_dir );
			/* Adding non-gettexted strings can repeat some phrases */
			$output_shell = escapeshellarg( $output );
			system( "msguniq --use-first $output_shell -o $output_shell" );
		}
		return $res;
	}

	function ls_core($dir, $output) {
		if ( !file_exists( "$dir/application/core/LSYii_Application.php" ) ) return false;

		return $this->ls_generic( $dir, array(
			'project' => 'default',
			'output' => $output,
		) );
	}

	function ls_19($dir, $output) {
		if ( !file_exists( "$dir/admin/admin.php" ) ) return false;

		return $this->ls_generic( $dir, array(
			'project' => 'default',
			'output' => $output,
			'version' => '1.92'
		) );
	}

	function get_first_lines($filename, $lines = 30) {
		$extf = fopen($filename, 'r');
		if (!$extf) return false;
		$first_lines = '';
		foreach(range(1, $lines) as $x) {
			$line = fgets($extf);
			if (feof($extf)) break;
			if (false === $line) {
				return false;
			}
			$first_lines .= $line;
		}
		return $first_lines;
	}


	function get_addon_header($header, &$source) {
		if (preg_match('|'.$header.':(.*)$|mi', $source, $matches))
			return trim($matches[1]);
		else
			return false;
	}

	function generic($dir, $output) {
		$output = is_null($output)? "generic.pot" : $output;
		return $this->xgettext('generic', $dir, $output, array());
	}

}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if ($included_files[0] == __FILE__) {
	$makepot = new MakePOT;
	if ((3 == count($argv) || 4 == count($argv)) && in_array($method = str_replace('-', '_', $argv[1]), get_class_methods($makepot))) {
		$res = call_user_func(array(&$makepot, $method), realpath($argv[2]), isset($argv[3])? $argv[3] : null);
		if (false === $res) {
			fwrite(STDERR, "Couldn't generate POT file!\n");
		}
	} else {
		$usage  = "Usage: php makepot.php PROJECT DIRECTORY [OUTPUT]\n\n";
		$usage .= "Generate POT file from the files in DIRECTORY [OUTPUT]\n";
		$usage .= "Available projects: ".implode(', ', $makepot->projects)."\n";
		fwrite(STDERR, $usage);
		exit(1);
	}
}

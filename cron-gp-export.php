<?php

date_default_timezone_set( 'GMT' );
ini_set( 'memory_limit', '1024M' );
require_once 'makepot.php';

function silent_system( $command ) {
	global $at_least_one_error;	
	ob_start();
	system( "$command 2>&1", $exit_code );
	$output = ob_get_contents();
	ob_end_clean();
	if ( $exit_code != 0 ) {
		echo "ERROR:\t$command\nCODE:\t$exit_code\nOUTPUT:\n";
		echo $output."\n";
	} else {
		echo "OK:\t$command\n";		
	}	
	return $exit_code;
}

function run_commands( $commands ) {
	global $dry_run;

	foreach ( $commands as $command ) {
		if ( !$dry_run ) {
			$exit = silent_system( $command );
			if ( 0 != $exit ) break;
		} else {
			echo "CMD:\t$command\n";
		}
	}
}

$options = getopt( 'c:p:m:n:a:s:g:j:e:t:dfr' );
if ( empty( $options ) ) {
?>
	-s	Which branch? master by default
	-c	Application git checkout
	-p	POT git checkout in case it differs from application git checkout
	-a	Relative path of POT dir inside dir in -p or -c. If both are supplied, -p is given preference.
	-e	Relative path of dir in which locales will be exported dir in -p or -c. If both are supplied, -p is given preference. Optional.
	-n	POT filename
	-m	MakePOT project
	-g	GlotPress location, within which scripts/import-originals.php file is located, optional.
	-j	GlotPress project path, optional.
	-t	Time to be supplied to contributors script
	-r	Regenerate all the POs and MOs? Would only work of -e is supplied.
	-d	Dry-run
	-f	Fast - do not pull or checkout the branch
	User configuration must be already set
<?php
	die;
}

$application_git_checkout = realpath( $options['c'] );
$pot_git_checkout = isset( $options['p'] ) ? realpath( $options['p'] ) : $application_git_checkout;
$relative_pot_path = isset( $options['a'] ) ? '/' . $options['a'] : '';
$pot_directory = realpath( $pot_git_checkout . $relative_pot_path );
$makepot_project = str_replace( '-', '_', $options['m'] );
$relative_po_path = isset( $options['e'] ) ? '/' . $options['e'] : '';
$pot = $options['n'];
$branch = isset( $options['s'] ) ? $options['s'] : 'master';
$time = isset( $options['t'] ) ? $options['t'] : '';
$dry_run = isset( $options['d'] );
$regenerate = isset( $options['r'] );
$glotpress_path = isset( $options['g'] ) ? realpath( $options['g'] ) : '';
$glotpress_project = isset( $options['j'] ) ? $options['j'] : '';

if ( ! is_dir( "$application_git_checkout" ) || ! is_dir( dirname( "$pot_git_checkout" ) ) || ! is_dir( dirname( "{$pot_directory}/{$pot}" ) ) )
	continue;

chdir( $application_git_checkout );
if ( ! isset( $options['f'] ) ) {
	$exit = silent_system( "git checkout {$branch}" );
	if ( 0 != $exit ) die();
	$exit = silent_system( "git pull" );
	if ( 0 != $exit ) die();
}

if ( $application_git_checkout != $pot_git_checkout ) {
	chdir( $pot_git_checkout );
	$exit = silent_system( "git pull" );
	if ( 0 != $exit ) die();
}

// Make the POT
chdir($pot_directory);
$exists = is_file( $pot );
$makepot = new MakePOT;
if ( ! isset( $options['d'] ) ) {
	if ( !call_user_func( array( &$makepot, $makepot_project ), $application_git_checkout, "$pot_directory/$pot" ) ) continue;
}
if ( !file_exists( "$pot_directory/$pot" ) ) continue;
// do not commit if the difference is only in the header, but always commit a new file
$real_differences = `git diff $pot | wc -l` > 16;
if ( $exists && !$real_differences )
	silent_system( "git checkout $pot" );

$commands = array();
$po_files = array();
if ( !empty( $glotpress_path ) && !empty( $glotpress_project ) && is_dir( "$glotpress_path" ) ) {
	if ( ( !$exists || $real_differences ) && file_exists( "{$glotpress_path}/scripts/import-originals.php" ) )
		$commands[] = "php '{$glotpress_path}/scripts/import-originals.php' -f '{$pot_directory}/{$pot}' -p {$glotpress_project}";
	if ( !empty( $relative_po_path ) && file_exists( "{$glotpress_path}/scripts/export.php" ) && is_dir( $pot_git_checkout . $relative_po_path ) && $dh = opendir( $pot_git_checkout . $relative_po_path ) ) {
		$cmd_extra = $dry_run == true ? ' -d' : '';
		$cmd_extra .= !empty( $time ) ? " -t '{$time}'" : '';
		$commit_message = `php '{$glotpress_path}/scripts/contributors.php' -p {$glotpress_project}{$cmd_extra}`;
		$commit_message = array_filter( explode( "\n", $commit_message ) );
		$languages_to_update_raw = explode( ', ', array_shift( $commit_message ) );
		if ( ( !empty( $languages_to_update_raw ) && is_array( $languages_to_update_raw ) && count( $languages_to_update_raw ) >= 1 ) || $regenerate == true ) {
			$languages_to_update = array();
			$replacements = array(
				'hans'     => 'simplified',
				'hant'     => 'traditional',
				'informal' => 'informal',
				'formal'   => 'formal',
			);
			if ( !$regenerate ) {
				/**
				 * LIMESURVEY SPECIFIC CODE, WOULD HAVE TO BE CHANGED AS MORE SPECIAL LANGUAGES ARE ADDED
				 */
				$swap_replacements = array_combine( array_values( $replacements ), array_keys( $replacements ) );
				foreach ( $languages_to_update_raw as $language_to_update_raw ) {
					list( $language_to_update, $slug ) = explode( '/', $language_to_update_raw );
					switch ( $slug ) {
						case 'simplified' :
						case 'traditional' :
							$parts = explode( '-', $language_to_update );
							$new_lang = array( $parts[0], ucfirst( $swap_replacements[$slug] ), strtoupper( $parts[1] ) );
							break;
						case 'informal' :
						case 'formal' :
							$new_lang = array( $language_to_update, $slug );
							break;
						default :
							$new_lang = array( $language_to_update );
							break;
					}
					$languages_to_update[] = join( '-', $new_lang );
				}
			}
			while ( ( $locale = readdir( $dh ) ) !== false ) {
				if ( !in_array( $locale, array( '.', '..', '.svn', 'index.html', '_template' ) ) && ( $regenerate == true || in_array( $locale, $languages_to_update ) ) && filetype( $pot_git_checkout . $relative_po_path . '/' . $locale ) == 'dir' ) {
					$low_locale = strtolower( $locale );
					$tags_locale = explode( '-', $low_locale );
					$t = array();
					if ( is_array( $tags_locale ) && count( $tags_locale ) >= 2 ) {
						foreach ( $replacements as $search => $replacement ) {
							$pos = array_search( $search, $tags_locale );
							if ( $pos !== false ) {
								unset( $tags_locale[$pos] );
								$t[] = $replacement;
							}
						}
					}
					if ( is_array( $t ) && count( $t ) >= 1 ) {
						$t = join( '-', $t );
						$low_locale = join( '-', $tags_locale );
					} else {
						$t = 'default';
					}
					$commands[] = "php '{$glotpress_path}/scripts/export.php' -p {$glotpress_project} -l {$low_locale} -t {$t} -o po > {$pot_git_checkout}{$relative_po_path}/{$locale}/LC_MESSAGES/{$locale}.po";
					//$commands[] = "php '{$glotpress_path}/scripts/export.php' -p {$glotpress_project} -l {$low_locale} -t {$t} -o mo > {$pot_git_checkout}{$relative_po_path}/{$locale}/LC_MESSAGES/{$locale}.mo";
					$po_files[] = "{$pot_git_checkout}{$relative_po_path}/{$locale}/LC_MESSAGES/{$locale}.po";
				}
			}
			closedir( $dh );
		}
		$commit_message = join( "\n", $commit_message );
	}
}

// Make the POs
run_commands( $commands );

// Make the MOs
if ( !empty( $po_files ) && !$dry_run ) {
	require_once( 'php-mo.php' );
	foreach ( $po_files as $po_file ) {
		phpmo_convert( $po_file );
	}
}

// Only commit if required
if ( !$exists || $real_differences || !empty( $commit_message ) ) {
	if ( empty( $commit_message ) )
		$commit_message = 'Dev Automatic translation update';
	$commands = array(
		"git commit -am '{$commit_message}'",
		"git push origin $branch"
	);
	run_commands( $commands );
}

<?php

date_default_timezone_set( 'Europe/London' );
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

$options = getopt( 'c:p:m:n:s:df' );
if ( empty( $options ) ) {
?>
	-s	Which branch? master by default
	-c	Application git checkout
	-p	POT git checkout in case it differs from application git checkout
	-a	Relative path of POT dir inside dir in -p or -c. If both are supplied, -p is given preference.
	-n	POT filename
	-m	MakePOT project
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
$pot = $options['n'];
$branch = isset( $options['s'] ) ? $options['s'] : 'master';
$dry_run = isset( $options['d'] );

if ( ! is_dir( "$application_git_checkout" ) || ! is_dir( dirname( "$pot_git_checkout" ) ) || ! is_dir( dirname( "$pot_directory/$pot" ) ) )
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

$exists = is_file( $pot );
$makepot = new MakePOT;
if ( !call_user_func( array( &$makepot, $makepot_project ), $application_git_checkout, "$pot_directory/$pot" ) ) continue;
if ( !file_exists( "$pot_directory/$pot" ) ) continue;
// do not commit if the difference is only in the header, but always commit a new file
$real_differences = `git diff $pot | wc -l` > 16;
if ( !$exists || $real_differences ) {
	foreach ( array(
		"git add $pot",
		"git commit $pot -m 'Automatic POT update'",
		"git push origin $branch",
		//"",
		//""
	) as $command ) {
		if ( !$dry_run ) {
			$exit = silent_system( $command );
			if ( 0 != $exit ) break;
		} else {
			echo "CMD:\t$command\n";
		}
	}
} else {
	silent_system( "git checkout $pot" );
}

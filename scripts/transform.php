<?php

require_once __DIR__ . '/../screenshots-browser-extension/wp-php-importer/html.inferer.php';

function main() {
	global $argv;

	$html = file_get_contents( 'php://stdin' );

	switch ( $argv[1] ) {
		case 'build.outline':
			echo HTML_Inferer::build_outline( $html );
			break;

		case 'extract':
			echo HTML_Inferer::extract( $html, $argv[2] );
			break;

		case 'for.context':
			echo HTML_Inferer::for_context( $html );
			break;

		case 'for.structure':
			echo HTML_Inferer::for_structure( $html );
			break;

		case 'stamp.out':
			echo HTML_Inferer::stamp_out( $html, $argv[2], $argv[3], $argv[4] );
			break;

		case 'strip.output':
			echo HTML_Inferer::strip_output( $html );
			break;

		default:
			var_dump( $argv );
			exit(1);
	}
}

main();

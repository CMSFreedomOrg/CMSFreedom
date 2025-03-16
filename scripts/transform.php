<?php

require_once __DIR__ . '/../prompts/html.inferer.php';

function main() {
	global $argv;

	$html = file_get_contents( 'php://stdin' );

	switch ( $argv[1] ) {
		case 'for.context':
			echo HTML_Inferer::for_context( $html );
			break;

		case 'for.structure':
			echo HTML_Inferer::for_structure( $html );
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

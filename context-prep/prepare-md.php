<?php

require_once __DIR__ . '/../prompts/html.inferer.php';

$md = file_get_contents( 'php://stdin' );

$lines = explode( "\n", $md );
foreach ( $lines as $line ) {
	if ( str_starts_with( $line, '/fetch ' ) ) {
		$url = substr( $line, strlen( '/fetch ' ) );
		echo "\e[90mFetching '\e[34m{$url}\e[90m'\e[m\n";
		$resource = file_get_contents( $url );
		$resource = HTML_Inferer::for_context( $resource );
		echo "\n\n<|FETCH_START:{$url}|>\n{$resource}\n<|FETCH_END:{$url}|>\n\n";
	} else {
		echo "{$line}\n";
	}
}

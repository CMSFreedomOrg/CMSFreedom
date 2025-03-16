<?php

require_once '/Users/dmsnell/load-html-api.php';

class HTML_Inferer extends WP_HTML_Processor {
	public static function for_structure( $html ) {
		$p = WP_HTML_Processor::create_full_parser( $html );
		$o = '';

		while ( $p->next_token() ) {
		  $tn = $p->get_token_name();
			$tt = $p->get_token_type();

			$to_skip = [
				'SCRIPT', 'STYLE', 'IFRAME',
				// Formatting elements
				'B', 'I', 'EM', 'STRONG', 'TT', 'DATA', 'OUTPUT',
				'SMALL', 'STRIKE', 'S', 'U', 'FONT',

				'TEMPLATE',
			];

			if ( in_array( $tn, $to_skip, true ) ) {
				continue;
			}

			switch ( $tn ) {
				case 'PATH':
					$p->remove_attribute( 'd' );
					break;
			}

			$o .= $p->serialize_token();
		}

		return preg_replace( "~ +~", ' ', preg_replace( "~[\t\f\r\n]+~", "\n", $o ) );
	}

	public static function strip_output( $html ) {
		$last_think_at = strrpos( strtolower( $html ), "\n</think>\n" );
		if ( false !== $last_think_at ) {
			$html = substr( $html, $last_think_at + strlen( "\n</think>\n" ) );
		}

		$html = preg_replace( "~(?:^```(?:[a-zA-Z][a-zA-Z0-0]*)?[\n])|(?:```$)~", '', trim( $html, " \t\r\f\n" ) );

		echo $html;
	}
}

function main() {
	global $argv;

	$html = file_get_contents( 'php://stdin' );

	switch ( $argv[1] ) {
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

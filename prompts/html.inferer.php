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

	public static function classes( $class ) {
		// assumes no-quirks mode: thus lowercase everything
		return array_unique(preg_split('~[ \f\r\n\t]+~', $class, 0, PREG_SPLIT_NO_EMPTY ) );
	}

	public static function outline( $html ) {
		$p = WP_HTML_Processor::create_full_parser( $html );
		$o = '';
		$depth = 0;

		$out = function ( $s ) use ( &$o, &$depth ) {
			$o .= str_repeat( '  ', $depth );
			$o .= $s;
		};

		while ( $p->next_token() ) {
			$tn = $p->get_token_name();
			$tt = $p->get_token_type();
			$ic = $p->is_tag_closer();
			$ec = $p->expects_closer();
			$dd = $ic ? -1 : 1; // Depth delta

			$class = $p->get_attribute( 'class' );
			$classes = is_string( $class ) ? self::classes( $class ) : [];

			$data = $p->get_attribute_names_with_prefix( 'data-' );
			$c = implode( '', array_map( fn ( $c ) => ".{$c}", $classes ) );
			$d = implode( '', array_map( fn ( $k ) => "[{$k}=\"{$p->get_attribute($k)}\"]", $data ?? [] ) );

			if ( is_string( $data ) ) {
				$data = array_combine(
					$data,
					array_map( fn ( $name ) => $p->get_attribute( $name ), $data )
				);
			}

			switch ( $tn ) {
				case 'HTML':
					$lang = $p->get_attribute( 'lang' );

					if ( ! $ic && ( isset( $lang ) || isset( $class ) || isset( $data ) ) ) {
						$l = $lang ? "[lang=\"{$lang}\"]" : '';
						$out( "HTML{$c}{$l}{$d}\n" );
					}

					$depth += $dd;
					break;

				case 'BASE':
				case 'LINK':
				case 'META':
				case 'TITLE':
				case 'BODY':
				case 'ARTICLE':
				case 'DIV':
				case 'FOOTER':
				case 'HEADER':
				case 'HGROUP':
				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
				case 'MAIN':
				case 'MENU':
				case 'NAV':
				case 'SECTION':
				case 'DETAILS':
				case 'SUMMARY':
				case 'FORM':
				case 'OL':
				case 'UL':
				case 'LI':
				case 'A':

				// Table layouts: sigh.
				case 'TABLE':
				case 'TH':
				case 'SVG':
					if ( $ic ) {
						$depth--;
						break;
					}

					$attys = [];
					foreach ( [ 'rel', 'href', 'src', 'name', 'content', 'id' ] as $k ) {
						$v = $p->get_attribute( $k );
						if ( ! is_string( $v ) ) {
							continue;
						}

						$v = str_replace( '"', "''", $v );
						$attys[] = "[{$k}=\"{$v}\"]";
					}
					$attys = count( $attys ) > 0 ? implode( '', $attys ) : '';

					$out( "{$tn}{$attys}{$c}{$d}\n" );
					if ( $ec ) {
						$depth++;
					}
					break;
			}
		}

		echo preg_replace( "~[\r\n]+~", "\n", $o );
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
		case 'build.outline':
			echo HTML_Inferer::outline( $html );
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

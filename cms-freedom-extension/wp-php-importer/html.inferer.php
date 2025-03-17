<?php

class HTML_Inferer extends WP_HTML_Processor {
	public static function extract( $html, $selector ) {
		set_error_handler( function () { return true; } );
		$dom = \DOM\HtmlDocument::createFromString( $html );
		$node = $dom->querySelector( $selector );
		$fragment = $dom->saveHtml( $node );
		restore_error_handler();

		return $fragment;
	}

	public static function stamp_out( $html, $selector, $id, $label ) {
		set_error_handler( function () { return true; } );
		$dom = \DOM\HtmlDocument::createFromString( $html );
		$node = $dom->querySelector( $selector );
		if ( empty( $node ) ) {
			return $html;
		}
		$div = $dom->createElement( 'div' );
		$div->setAttribute( 'id', $id );
		$div->innerHTML = $label;
		$node->parentNode->replaceChild( $div, $node );
		$fragment = $dom->saveHtml();
		restore_error_handler();

		return $fragment;
	}

	public static function build_outline( $html ) {
		$p = WP_HTML_Processor::create_full_parser( $html );
		$o = '';
		$depth = 0;

		while ( $p->next_token() ) {
			$tn = $p->get_token_name();
			$tt = $p->get_token_type();
			$ic = $p->is_tag_closer();
			$ec = $p->expects_closer();

			if ( '#tag' !== $tt ) {
				continue;
			}

			$delta = $ic ? -1 : 1;

			$structural_elements = [
				'HTML',
				'BASE',
				'LINK',
				'META',
				'TITLE',
				'BODY',
				'ARTICLE',
				'ASIDE',
				'DIV',
				'FOOTER',
				'HEADER',
				'H1',
				'H2',
				'H3',
				'H4',
				'H5',
				'H6',
				'HGROUP',
				'MAIN',
				'MENU',
				'NAV',
				'TABLE',
				'SECTION',
				'FORM',
			];

			if ( ! in_array( $tn, $structural_elements, true ) ) {
				continue;
			}

			if ( $ic ) {
				$depth--;
				continue;
			}

			$data = $p->get_attribute_names_with_prefix( 'data-' ) ?? array();
			$datas = array_map( function ( $n ) use ( $p ) {
				$v = $p->get_attribute( $n );
				if ( true === $v ) { return "[{$n}]"; }
				$vs = str_replace( '"', "''", $v );
				return "[{$n}=\"{$vs}\"]";
			}, $data );
			$datas = implode( '', $data );
			$class = $p->get_attribute( 'class' );
			$classes = is_string( $class )
				? array_unique(preg_split("~[ \t\f\r\n]+~", $class, 0, PREG_SPLIT_NO_EMPTY))
				: array();
			$classes = implode( '', array_map( fn ( $c ) => ".{$c}", $classes ) );

			$lang = $p->get_attribute( 'lang' );
			$lang = is_string( $lang ) ? "[lang=\"{$lang}\"]" : '';
			$id   = $p->get_attribute( 'id' );
			$id   = is_string( $id ) ? "[id=\"{$id}\"]" : '';

			$o .= str_repeat( '  ', $depth );
			$o .= "{$tn}{$classes}{$lang}{$id}{$datas}\n";

			if ( $ec ) {
				$depth++;
			}
		}

		return $o;
	}

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

	public static function for_context( $html ) {
		$p = WP_HTML_Processor::create_full_parser( $html );
		$o = '';

		while ( $p->next_token() ) {
			$tn = $p->get_token_name();
			$tt = $p->get_token_type();

			if ( '#text' === $tn ) {
				$o .= str_replace( '<', '&lt;', $p->get_modifiable_text() );
				continue;
			}

			$to_skip = [
				'SCRIPT', 'STYLE', 'IFRAME',
				'BUTTON',
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

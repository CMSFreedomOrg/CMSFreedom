<?php

/**
 * Prototype/broken code exploring the flow of generating the site
 * from the source URL. Calls LLMs and performs post-processing
 * follow-up.
 */

// This this to the base file path for a working WordPress
// with a valid `wp-config.php`
require_once __DIR__ . '/src/wp-load.php';

function run() {
	$seen_urls = array();
	$url = file_get_contents( 'php://stdin' );
	$seen_urls[ $url ] = true;
	$html = run_llm( $url );

	// Establish the theme
	extract_and_set_theme( $url );

	$url_queue = array( $url );
	crawl( $seen_urls, $url_queue );

	while ( count( $url_queue ) ) {
		crawl( $seen_urls, $url_queue );
	}
}

function crawl( &$seen_urls, &$url_queue ) {
	$url = array_shift( $url_queue );
	if ( empty( $url ) ) {
		return;
	}

	$html = file_get_contents( $url );
	$html_kind = run_llm( $html, 'classify-content' );
	switch ( $html_kind ) {
		case 'single-view':
			$main_content = run_llm( $html, 'main-content' );
			$author       = run_llm( $html, 'get-author' );
			$publish_date = run_llm( $html, 'publish-date' );
			$next_prev    = run_llm( $html, 'next-prev' );
			if ( $next_prev && ! isset( $seen_urls, $next_prev ) ) {
				$seen_urls[ $next_prev ] = true;
				$url_queue[] = $next_prev;
			}
			$parent       = run_llm( $html, 'parent' );
			if ( $parent && ! isset( $seen_urls, $parent ) ) {
				$seen_urls[ $parent ] = true;
				$url_queue[] = $parent;
			}
			break;

		case 'list-view':
			$urls = run_llm( $html, 'single-view-links' );
			foreach ( $urls as $url ) {
				if ( ! isset( $seen_urls[ $url ] ) {
					$url_queue[] = $url;
				}
			}
			break;
	}
}

run();

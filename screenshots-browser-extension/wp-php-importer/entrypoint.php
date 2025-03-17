<?php

require_once '/wordpress/wp-load.php';
require_once __DIR__ . '/openai-prompt.php';
require_once __DIR__ . '/html.inferer.php';

$response = wp_remote_get(getenv('ENTRY_URL'));
$html = $response['body'];

$screenshots = glob('/tmp/screenshot-*.base64');

function run_llm( $systems, $users, $options = array() ) {
	return openAIPrompt(
		$systems,
		$users,
		array_merge_recursive(
			[
				'apiEndpoint' => getenv('OPENAI_API_ENDPOINT'),
				'apiKey' => getenv('OPENAI_API_KEY'),
				'payload' => [
					'model' => getenv('OPENAI_API_MODEL')
				]
			],
			$options
		)
	);
}

function prompt( $name ) {
	return file_get_contents( __DIR__ . '/prompts/' . $name . '.txt' );
}

$html_for_context = HTML_Inferer::for_context( $html );
$html_for_structure = HTML_Inferer::for_structure( $html );
$html_outline = HTML_Inferer::build_outline( $html );

// Extract main content
$response = run_llm(
	[
		prompt( 'main-content' ),
	],
	[
		"<|HTML_OUTLINE_START|>\n{$html_outline}\n<|HTML_OUTLINE_END|>\n",
		"<|HTML_START|>\n{$html_for_structure}\n<|HTML_END|>\n",
		prompt( 'selector.main-content' )
	]
);

list( 'selector' => $main_content_selector, 'id' => $id ) = (array) json_decode( $response );

$theme_only_html = HTML_Inferer::stamp_out( $html, $main_content_selector, 'layout-replacement::main-content', 'MAIN_CONTENT' );
$main_content_html = HTML_Inferer::extract( $html, $main_content_selector );
var_dump( [
	'outline' => $html_outline,
	'theme' => $theme_only_html,
	'main' => $main_content_html,
] );

$response = run_llm(
	[ prompt( 'main-content' ) ],
	[
		"<|HTML_OUTLINE_START|>\n{$html_outline}\n<|HTML_OUTLINE_END|>\n",
		"<|HTML_START|>{$html}<|HTML_END|>\n",
		prompt( 'selector.post-title' )
	]
);
var_dump( [ 'post_title_query' => $response ] );

list( 'selector' => $post_title_selector ) = (array) json_decode( $response );

if ( ! empty( $post_title_selector ) ) {
	$post_title = HTML_Inferer::extract( $html, $post_title_selector );
} else {
	$post_title = '';
}

$post_id = wp_insert_post( [
	'post_content' => $main_content_html,
	'post_title' => $post_title,
	'post_status' => 'publish'
] );

if ( ! empty( $post_title_selector ) ) {
	$theme_only_html = HTML_Inferer::stamp_out( $theme_only_html, $post_title_selector, '', '' );
}

$response = run_llm(
	[ prompt( 'theme-generation-system' ), prompt( 'block-theme' ) ],
	array_merge(
		[ "<|HTML_START|>\n{$theme_only_html}\n<|HTML_END|>\n" ],
		$screenshots,
	),
	[
		'stream' => false,
		'payload' => [
			'response_format' => [
				"type" => "json_schema",
				"json_schema" => [
					"name" => "file_list_response",
					"schema" => [
						"type" => "object",
						"properties" => [
							"files" => [
								"type" => "array",
								"items" => [
									"type" => "object",
									"properties" => [
										"path" => [
											"type" => "string",
											"description" => "File path including name"
										],
										"content" => [
											"type" => "string",
											"description" => "File content"
										]
									],
									"required" => ["path", "content"],
									"additionalProperties" => false
								]
							]
						],
						"required" => ["files"],
						"additionalProperties" => false
					],
					"strict" => true
				]
			]
		],
	]
);

$files = parse_structured_output($response);

function parse_structured_output($response) {
	$file_objects = json_decode($response, true);
	$files_kv = [];
	foreach($file_objects['files'] as $file) {
		$files_kv[$file['path']] = $file['content'];
	}
	return $files_kv;
}

function parse_string_output($response) {
	// Parse the response to extract files
	$files = [];
	$current_file = null;
	$current_content = '';
	// Split the response into lines for processing
	$lines = explode("\n", $response);
	$in_file = false;

	foreach ($lines as $line) {
		// Check for file start marker
		if (preg_match('/<\|CREATE_FILE_START:(.*?)\|>/', $line, $start_match)) {
			// If we were already processing a file, save it before starting a new one
			if ($current_file !== null) {
				$files[$current_file] = $current_content;
				$current_content = '';
			}
			$current_file = $start_match[1];
			$in_file = true;
			continue;
		}
		// Check for file end marker
		elseif (preg_match('/<\|CREATE_FILE_END:(.*?)\|>/', $line, $end_match)) {
			if ($current_file !== null) {
				// Save the current file
				$files[$current_file] = $current_content;
				$current_file = null;
				$current_content = '';
			}
			$in_file = false;
			continue;
		}

		// If we're inside a file, add the line to the content
		if ($in_file && $current_file !== null) {
			$current_content .= $line . "\n";
		}
	}
	// Handle case where the last file doesn't have a closing tag
	if ($current_file !== null) {
	    $files[$current_file] = $current_content;
	}
	return $files;
}



// Create a directory for the theme if it doesn't exist
$theme_dir = WP_CONTENT_DIR . '/themes/cf2025-gen-theme';
if (!file_exists($theme_dir)) {
    mkdir($theme_dir, 0755, true);
}

// Process each file
foreach ($files as $file_path => $file_content) {
	if(str_starts_with($file_path, 'wp-content/themes/')) {
		$file_path = substr($file_path, strlen('wp-content/themes/'));
	}
	if(str_starts_with($file_path, 'cf2025-gen-theme/')) {
		$file_path = substr($file_path, strlen('cf2025-gen-theme/'));
	}
    // Create subdirectories if needed
    $full_path = $theme_dir . '/' . $file_path;
    $dir_path = dirname($full_path);
    if (!file_exists($dir_path)) {
        mkdir($dir_path, 0755, true);
    }

    // Write the file

    // Push the content back in.
    if ( str_contains( $file_content, 'layout-replacement::main-content' ) ) {
        $file_content = str_replace(
            '<div id="layout-replacement::main-content"></div>',
            <<<BLOCKS
            <!-- wp:post-title {"level":1} /-->
            <!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->
            BLOCKS,
            $file_content
        );
    }

    file_put_contents($full_path, $file_content);
	echo "<|FILE_START:{$file_path}|>\n{$file_content}\n<|FILE_END|>\n";
}

echo "Theme files created in: " . $theme_dir;
echo "Activating the theme...\n";

// Set current user to admin
wp_set_current_user( get_users(array('role' => 'Administrator') )[0]->ID );

$theme_name = 'cf2025-gen-theme';
switch_theme( $theme_name );

if( wp_get_theme()->get_stylesheet() !== $theme_name ) {
	throw new Exception( 'Theme ' . $theme_name . ' could not be activated.' );
}

echo "Theme activated: " . $theme_name . "\n";

post_message_to_js(
	"{\"goTo\": \"/?p={$post_id}\"}"
);

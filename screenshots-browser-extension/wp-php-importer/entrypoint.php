<?php

require_once '/wordpress/wp-load.php';
require_once __DIR__ . '/openai-prompt.php';

$response = wp_remote_get(getenv('ENTRY_URL'));
$html = $response['body'];

$screenshots = glob('/tmp/screenshot-*.base64');

$response = openAIPrompt(
	[
	],
	array_merge(
		[
			file_get_contents( __DIR__ . '/prompts/theme-generation-system.txt' ),
			'<|HTML_START|>',
			$html,
			'<|HTML_END|>',
		],
		$screenshots,
	),
	[
		'apiEndpoint' => getenv('OPENAI_API_ENDPOINT'),
		'apiKey' => getenv('OPENAI_API_KEY'),
		'stream' => false,
		'payload' => [
			'model' => getenv('OPENAI_API_MODEL'),
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
    file_put_contents($full_path, $file_content);
	echo "Created file: " . $file_path . "\n";
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

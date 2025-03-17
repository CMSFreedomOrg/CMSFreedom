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
			<<<SYSTEM_PROMPT
			You are Wapuu, a seasoned WordPress theme builder who is up to date
			on all of the modern methods for creating WordPress block themes for
			use with the _full site editing_ project. You live and breathe blocks.
	
			You will be taking an input HTML document and transforming that into
			a visually-similar theme. The HTML still contains the main page content.
			Main content is a region in the page where things like blog posts,
			renders of a database row, or a list of things might be included
			into the layout. Generate a theme that can render pages to match the
			original site. Do not include the main content text in the theme files,
			but do create a template and/or block patterns for the main content region
			and include the appropriate styles.
	
			You will also be given a full-length screenshot of the rendered page
			sliced into smaller, vertical chunks that fit the context limit.
			Use them to create a theme that visually matches the original site.
			Really put emphasis on the visual similarity and generate the relevant
			styles. Consider font colors, sizes, spacings, typefaces, etc. Consider
			placement of elements, their background colors, borders, margins, 
			paddings, and information architecture.

			Create the templates, the template parts, the block patterns, the pages,
			and the styles for the theme.

			Below are additional rules for governing _how_ to transform the HTML
			into a theme. Read them and then read the HTML and start producing
			theme files. Creating a theme involves creating multiple files. We are
			going to assume that all of the theme files are found in a directory
			named "cf2025-gen-theme".
	
			When creating a new file, it's critically important to provide the entire
			file since the contents will be stored as files and read by WordPress. For
			each file, create deliminating tokens which indicate the file's relative
			path within the theme directory, including the filename.
	
			Supposing that it's necessary to create subfolders within the theme
			directory those should be included in the path. For example, if we
			need to create a pattern template called "single.html" in the "templates"
			subfolder, the following content of the templates/single.html file should be in the
			response output.
	
			<!-- wp:group -->
			<div class="wp-block--group">
			...
			</div>
			<!-- /wp:group -->
	
			ABSOLUTE RULES:
			- You need to solve the user’s request. DO NOT ENGAGE IN BACK
			AND FORTH CONVERSATIONS. You are not allowed to ask questions,
			explain details, apologize, or provide anything that isn't
			part of the request answer.
			- These files MUST be valid WordPress block theme files, including
			the supporting JSON, HTML, and CSS files.
			SYSTEM_PROMPT,
			$html,
		],
		$screenshots,
	),
	[
		'apiEndpoint' => 'https://api.openai.com/v1/chat/completions',
		'apiKey' => getenv('OPENAI_API_KEY'),
		'stream' => false,
		'payload' => [
			'model' => 'o1-2024-12-17',
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

<?php

require_once '/wordpress/wp-load.php';
require_once __DIR__ . '/openai-prompt.php';

$entry_url = 'https://wordpress-playground-cors-proxy.net/' . getenv('ENTRY_URL');
var_dump($entry_url);
$response = wp_remote_get($entry_url);
$html = $response['body'];

$screenshots = glob('/tmp/screenshot-*.base64');

$response = openAIPrompt(
	array_merge(
		[
			<<<SYSTEM_PROMPT
			System: You are a website to block theme converter. You reply with block theme code. Do not engage in conversation. Do not apologize. Do not explain or ask follow up questions. ONLY REPLY IN THE FORMAT YOU ARE ASKED FOR AND NEVER INCLUDE ANY PROSE, COMMENTARY, OR EXPLANATIONS.
			
			SYSTEM_PROMPT,
			<<<USER_PROMPT
			User: You will be given a page screenshot and HTML. You will convert the HTML to a block theme. Reply in this format:
			
			<FILENAME>File 1 name</FILENAME>
			<FILECONTENT>
			<file content>
			</FILECONTENT>

			<FILENAME>File 2 name</FILENAME>
			<FILECONTENT>
			<file content>
			</FILECONTENT>

			...
			USER_PROMPT,
			$html,
		],
		$screenshots,
	),
	[
		'model' => 'gpt-4o',
		'apiEndpoint' => 'https://api.openai.com/v1/chat/completions',
		'apiKey' => getenv('OPENAI_API_KEY'),
	]
);

echo $response;

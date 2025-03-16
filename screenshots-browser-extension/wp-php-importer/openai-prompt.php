<?php

/**
 * Makes a request to OpenAI API with support for text and image inputs
 * 
 * @param array $inputs Array of strings or URLs/file paths
 * @param array $options Configuration options including apiEndpoint, apiKey, model, etc.
 * @return string The response text from the API
 * @throws Exception If required options are missing or on API errors
 */
function openAIPrompt(array $systemPrompt, array $userPrompt, array $options): string {
    // Validate required options
    if (empty($options['apiEndpoint'])) {
        throw new Exception('API endpoint is required in options');
    }
    if (empty($options['apiKey'])) {
        throw new Exception('API key is required in options');
    }
    if (empty($options['model'])) {
        throw new Exception('Model is required in options');
    }

    // Process all inputs to create content parts
	$prompts = [];
	foreach (['system' => $systemPrompt, 'user' => $userPrompt] as $role => $inputs) {
		$contentParts = [];
		foreach ($inputs as $index => $item) {
			if (! is_string($item)) {
				throw new Exception("Input at index {$index} is not a string or valid file path: " . print_r($item, true));
			}
			if (filter_var($item, FILTER_VALIDATE_URL)) {
				// If it's a URL, fetch the image and convert to base64
				$imageData = file_get_contents($item);
				if ($imageData === false) {
					throw new Exception("Failed to fetch image from URL at index {$index}");
				}
				$base64Image = 'data:image/jpeg;base64,' . base64_encode($imageData);
				$contentParts[] = [
					'type' => 'image_url',
					'image_url' => ['url' => $base64Image]
				];
			} elseif (is_file($item)) {
				// Handle local file paths
				$imageData = file_get_contents($item);
				if ($imageData === false) {
					throw new Exception("Failed to read image file at index {$index}");
				}
				$mimeType = mime_content_type($item);
				// If the file is already base64 encoded, use it directly, otherwise encode it
				if (pathinfo($item, PATHINFO_EXTENSION) === 'base64') {
					$base64Image = $imageData;
				} else {
					$base64Image = "data:{$mimeType};base64," . base64_encode($imageData);
				}
				$contentParts[] = [
					'type' => 'image_url',
					'image_url' => ['url' => $base64Image]
				];
			} else {
				// Regular text input
				$contentParts[] = [
					'type' => 'text',
					'text' => $item
				];
			}
		}
		$prompts[] = [
			'role' => $role,
			'content' => $contentParts
		];
	}

	$stream = $options['stream'] ?? false;
    // Prepare the request payload
    $payload = [
        'model' => $options['model'],
        'messages' => $prompts,
        'stream' => $stream
    ];

    // Add optional parameters if provided
    if (isset($options['temperature'])) {
        $payload['temperature'] = $options['temperature'];
    }
    if (isset($options['maxTokens'])) {
        $payload['max_tokens'] = $options['maxTokens'];
    }

    // Initialize cURL session
    // Note: wp_remote_post doesn't support streaming responses directly
    // We'll need to modify our approach to use WordPress HTTP API
    $response = wp_remote_post($options['apiEndpoint'], [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $options['apiKey']
        ],
        'body' => json_encode($payload),
        'timeout' => 10000, // Increase timeout for large responses
        'stream' => false, // wp_remote_post doesn't support streaming
    ]);

    if (is_wp_error($response)) {
        throw new Exception("Request failed: " . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        throw new Exception("Request failed with status {$status_code}: {$body}");
    }

	if (!$stream) {
		return $body;
	}

    // Process the response
    $resultText = '';
    $lines = explode("\n", $body);
    
    foreach ($lines as $line) {
        if (empty($line) || str_starts_with($line, ':')) {
            continue;
        }

        if (str_starts_with($line, 'data: ')) {
            $jsonData = substr($line, 6); // Remove 'data: ' prefix
            if ($jsonData === '[DONE]') {
                break;
            }

            try {
                $parsed = json_decode($jsonData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $parsed['choices'][0]['delta']['content'] ?? 
                             $parsed['choices'][0]['text'] ?? '';
                    $resultText .= $content;
                }
            } catch (Exception $e) {
                error_log('Failed to parse JSON chunk: ' . $line);
            }
        }
    }

    return $resultText;
}

// // Example usage
// function exampleImageAndTextPrompt($options = []) {
//     try {
//         $options = array_merge([
//             'apiEndpoint' => 'https://api.openai.com/v1/chat/completions',
//             'model' => 'gpt-4o'
//         ], $options);

//         $response = openAIPrompt([
//             'What can you see in this image? Provide a detailed description. Tell me about the objects, style, colors, etc.',
//             'https://adamadam.blog/wp-content/uploads/2023/04/F62290E2-8A50-4C4F-821B-C4C085E6DF4A-768x768.jpeg'
//         ], $options);

//         echo "Image and text response: " . $response;
//         return $response;
//     } catch (Exception $e) {
//         error_log('Error in image and text example: ' . $e->getMessage());
//         throw $e;
//     }
// }

// // Uncomment to run the example
// exampleImageAndTextPrompt(); 

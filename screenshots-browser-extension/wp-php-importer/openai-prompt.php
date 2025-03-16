<?php

/**
 * Makes a request to OpenAI API with support for text and image inputs
 * 
 * @param array $inputs Array of strings or URLs/file paths
 * @param array $options Configuration options including apiEndpoint, apiKey, model, etc.
 * @return string The response text from the API
 * @throws Exception If required options are missing or on API errors
 */
function openAIPrompt(array $inputs, array $options): string {
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
    $contentParts = [];
    foreach ($inputs as $index => $item) {
        if (is_string($item)) {
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
            } else {
                // Regular text input
                $contentParts[] = [
                    'type' => 'text',
                    'text' => $item
                ];
            }
        } elseif (is_file($item)) {
            // Handle local file paths
            $imageData = file_get_contents($item);
            if ($imageData === false) {
                throw new Exception("Failed to read image file at index {$index}");
            }
            $mimeType = mime_content_type($item);
            $base64Image = "data:{$mimeType};base64," . base64_encode($imageData);
            $contentParts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $base64Image]
            ];
        } else {
            throw new Exception("Input at index {$index} is not a string or valid file path");
        }
    }

    // Prepare the request payload
    $payload = [
        'model' => $options['model'],
        'messages' => [
            [
                'role' => 'user',
                'content' => $contentParts
            ]
        ],
        'stream' => true
    ];

    // Add optional parameters if provided
    if (isset($options['temperature'])) {
        $payload['temperature'] = $options['temperature'];
    }
    if (isset($options['maxTokens'])) {
        $payload['max_tokens'] = $options['maxTokens'];
    }

    // Initialize cURL session
    $ch = curl_init($options['apiEndpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $options['apiKey']
        ],
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            static $buffer = '';
            static $resultText = '';

            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

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
                            // echo $content; // Stream the content immediately
                            flush();
                        }
                    } catch (Exception $e) {
                        error_log('Failed to parse JSON chunk: ' . $line);
                    }
                }
            }

            return strlen($data);
        }
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode !== 200) {
        throw new Exception("Request failed with status {$httpCode}: {$response}");
    }

    curl_close($ch);
    return $response;
}

// Example usage
function exampleImageAndTextPrompt() {
    try {
        $options = [
            'apiEndpoint' => 'https://api.openai.com/v1/chat/completions',
            'apiKey' => '<OPENAI API KEY HERE>',
            'model' => 'gpt-4o'
        ];

        $response = openAIPrompt([
            'What can you see in this image? Provide a detailed description. Tell me about the objects, style, colors, etc.',
            'https://adamadam.blog/wp-content/uploads/2023/04/F62290E2-8A50-4C4F-821B-C4C085E6DF4A-768x768.jpeg'
        ], $options);

        echo "Image and text response: " . $response;
        return $response;
    } catch (Exception $e) {
        error_log('Error in image and text example: ' . $e->getMessage());
        throw $e;
    }
}

// Uncomment to run the example
exampleImageAndTextPrompt(); 

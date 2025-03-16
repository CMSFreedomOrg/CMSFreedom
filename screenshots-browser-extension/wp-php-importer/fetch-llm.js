// FileReader polyfill for environments that don't support it (like Node.js)
if (typeof FileReader === 'undefined') {
	class FileReader {
		constructor() {
			this.onloadend = null;
		}

		readAsDataURL(blob) {
			// For Node.js environments, we can use Buffer
			if (typeof Buffer !== 'undefined' && blob.arrayBuffer) {
				blob.arrayBuffer().then(buffer => {
					const base64 = Buffer.from(buffer).toString('base64');
					const mimeType = blob.type || 'application/octet-stream';
					this.result = `data:${mimeType};base64,${base64}`;

					if (this.onloadend) {
						this.onloadend();
					}
				});
			} else {
				// Fallback for other environments
				console.warn('FileReader polyfill: Environment does not support required APIs');
				setTimeout(() => {
					this.result = 'data:image/png;base64,';
					if (this.onloadend) {
						this.onloadend();
					}
				}, 0);
			}
		}
	}

	global.FileReader = FileReader;
}

async function openAIPrompt(inputs, options) {
	// Validate required options
	if (!options) {
		throw new Error('Options parameter is required');
	}

	if (!options.apiEndpoint) {
		throw new Error('API endpoint is required in options');
	}

	if (!options.apiKey) {
		throw new Error('API key is required in options');
	}

	if (!options.model) {
		throw new Error('Model is required in options');
	}

	// Determine if we need to use FormData (when there are image inputs)
	const hasImage = inputs.some(item => typeof item !== 'string');

	// Prepare the request options for fetch
	let fetchUrl = options.apiEndpoint;
	let fetchOptions;
	// For requests with images, we'll use JSON directly as OpenAI's API expects
	// Build the content array for the message
	const contentParts = [];

	// First, process all inputs and fetch any URLs in parallel
	const processedInputs = await Promise.all(
		inputs
			.map(async (item, index) => {
				if (!(item instanceof URL)) {
					return item;
				}

				try {
					// Fetch the URL and convert to a Blob
					const response = await fetch(item);
					if (!response.ok) {
						throw new Error(`Failed to fetch URL at index ${index}: ${response.statusText}`);
					}
					return await response.blob();
				} catch (error) {
					console.error(`Error fetching URL at index ${index}:`, error);
					// Return the original item if fetch fails
					return item;
				}
				return item;
			})
	);

	// Now process all inputs (including fetched blobs) to create content parts
	for (let index = 0; index < processedInputs.length; index++) {
		const item = processedInputs[index];
		if (typeof item === 'string') {
			// Text input: add as text content
			contentParts.push({ type: 'text', text: item });
		} else if (item instanceof Blob || item instanceof File) {
			// Convert the image blob to base64
			const reader = new FileReader();
			reader.readAsDataURL(item);
			const base64Image = await new Promise(resolve => {
				reader.onloadend = () => {
					// Get the full data URL
					resolve(reader.result);
				};
			});
			// Add the image as an image_url with the full data URL
			contentParts.push({
				type: 'image_url',
				image_url: { url: base64Image },
			});
		} else {
			throw new Error(`Input at index ${index} is not a string, Blob, or File`);
		}
	}

	// Create the JSON payload
	const payload = {
		model: options.model,
		messages: [{ role: 'user', content: contentParts }],
		stream: true,
	};

	// Add optional parameters if provided
	if (options.temperature !== undefined) {
		payload.temperature = options.temperature;
	}
	if (options.maxTokens !== undefined) {
		payload.max_tokens = options.maxTokens;
	}

	fetchOptions = {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Authorization: `Bearer ${options.apiKey}`,
		},
		body: JSON.stringify(payload),
	};

	// Perform the API call using fetch
	const response = await fetch(fetchUrl, fetchOptions);

	// Throw an error if the request failed (non-2xx status)
	if (!response.ok) {
		const errorText = await response.text();
		throw new Error(`Request failed with status ${response.status}: ${errorText}`);
	}

	// Ensure the response has a body to read from
	if (!response.body) {
		throw new Error('No response body received');
	}

	// Set up a reader to stream the response
	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let resultText = '';
	let done = false;

	// Read the stream chunk by chunk
	while (!done) {
		const { value, done: streamDone } = await reader.read();
		if (streamDone) {
			break; // Exit loop if streaming is finished
		}
		// Decode the received chunk into text
		const chunk = decoder.decode(value, { stream: true });

		// The stream may contain multiple JSON messages (SSE format) separated by newlines
		const lines = chunk.split('\n');
		for (const line of lines) {
			if (!line || line.trim() === '') continue; // skip empty lines
			if (line.startsWith(':')) continue; // skip any SSE comment lines
			if (line.startsWith('data: ')) {
				const data = line.slice('data: '.length).trim(); // remove "data: " prefix
				if (data === '[DONE]') {
					done = true; // Received end-of-stream signal
					break;
				}
				try {
					// Parse the JSON data to extract the assistant's reply content
					const parsed = JSON.parse(data);
					// Depending on API, the text may be in different fields:
					const content = parsed.choices?.[0]?.delta?.content ?? parsed.choices?.[0]?.text ?? '';
					resultText += content;
				} catch (e) {
					console.error('Failed to JSON-parse a response chunk: "', line, '". We are skipping this chunk and parsing the rest of the response.');
				}
			}
		}
	}

	// Return the accumulated response text
	return resultText;
}

// Example usage of openAIPrompt function with text and image inputs
async function exampleImageAndTextPrompt() {
	try {
		const options = {
			apiEndpoint: 'https://api.openai.com/v1/chat/completions',
			apiKey: '<PUT API KEY HERE>',
			model: 'gpt-4o',
		};

		const response = await openAIPrompt(
			[
				'What can you see in this image? Provide a detailed description. Tell me about the objects, style, colors, etc.',
				new URL(
					'https://adamadam.blog/wp-content/uploads/2023/04/F62290E2-8A50-4C4F-821B-C4C085E6DF4A-768x768.jpeg'
				),
			],
			options
		);
		console.log('Image and text response:', response);
		return response;
	} catch (error) {
		console.error('Error in image and text example:', error);
		throw error;
	}
}

await exampleImageAndTextPrompt();

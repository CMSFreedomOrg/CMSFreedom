import { startPlaygroundWeb } from './playground-client.js';

/**
 * Recursive function to process files from a JSON structure
 * @param {Object} fileTree - The file tree object
 * @param {string} basePath - The base path for the files
 * @returns {Promise<Object>} - Object containing file contents
 */
async function processFileTree(fileTree, basePath = '') {
	const result = {};

	for (const [name, content] of Object.entries(fileTree)) {
		const currentPath = basePath ? `${basePath}/${name}` : name;

		if (typeof content === 'string') {
			// It's a file
			try {
				const fileContent = await fetch(chrome.runtime.getURL(currentPath)).then(response =>
					response.text()
				);
				result[currentPath] = fileContent;
			} catch (error) {
				console.error(`Error loading file ${currentPath}:`, error);
				result[currentPath] = null;
			}
		} else if (typeof content === 'object') {
			// It's a directory
			const subDirResults = await processFileTree(content, currentPath);
			Object.assign(result, subDirResults);
		}
	}

	return result;
}

// Define the file tree structure
const phpFilesToPutInPlayground = await processFileTree({
	'wp-php-importer': {
		'context-prep': {
			'theme.md': 'theme.md'
		},
		'entrypoint.php': 'entrypoint.php',
		'html.inferer.php': 'html.inferer.php',
		'openai-prompt.php': 'openai-prompt.php',
		'prepare-md.php': 'prepare-md.php',
		'prompts': {
			'block-theme.txt': 'block-theme.txt',
			'main-content.txt': 'main-content.txt',
			'selector.main-content.txt': 'selector.main-content.txt',
			'selector.next-item.txt': 'selector.next-item.txt',
			'selector.parent-index.txt': 'selector.parent-index.txt',
			'selector.post-title.txt': 'selector.post-title.txt',
			'theme-generation-system.txt': 'theme-generation-system.txt',
		},
	},
});

let siteUrl = null;
const screenshots = await new Promise((resolve, reject) => {
	const screenshots = [];
	chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
		console.log('Message received', msg);
		if (msg.type === 'PROGRESS_UPDATE') {
			const { currentY, totalY, totalScreenshots, currentScreenshot } = msg;
			const percentage = Math.round((currentY / totalY) * 100);
			document.getElementById('progressBar').value = percentage;
			document
				.getElementById('modalContent')
				.querySelector(
					'#progress-prompt'
				).textContent = `Screenshot ${currentScreenshot}/${totalScreenshots} (${percentage}%) completed.`;
		} else if (msg.type === 'SCREENSHOT_COMPLETE') {
			console.log('Screenshot complete', msg);
			screenshots.push(msg.screenshot);
		} else if (msg.type === 'ALL_SCREENSHOTS_COMPLETE') {
			console.log('All screenshots complete', { length: screenshots.length });
			siteUrl = msg.siteUrl;
			resolve(screenshots);
		}
	});
});

const screeenshotFiles = {};
for (let i = 0; i < screenshots.length; i++) {
	// Process screenshot: resize and slice into manageable chunks
	const img = new Image();
	img.src = screenshots[i];

	// Create a canvas to resize the image
	const canvas = document.createElement('canvas');
	const ctx = canvas.getContext('2d');

	// Wait for the image to load
	await new Promise(resolve => {
		img.onload = () => {
			// Calculate new dimensions (max width 2000px)
			const maxWidth = 2000;
			let newWidth = img.width;
			let newHeight = img.height;

			if (newWidth > maxWidth) {
				const ratio = maxWidth / newWidth;
				newWidth = maxWidth;
				newHeight = Math.floor(img.height * ratio);
			}

			// Set canvas size for the resized image
			canvas.width = newWidth;
			canvas.height = newHeight;

			// Draw the resized image
			ctx.drawImage(img, 0, 0, newWidth, newHeight);

			// Slice the image into chunks of max 768px height
			const maxChunkHeight = 768;
			const numChunks = Math.ceil(newHeight / maxChunkHeight);

			for (let j = 0; j < numChunks; j++) {
				const chunkCanvas = document.createElement('canvas');
				const chunkCtx = chunkCanvas.getContext('2d');

				const chunkHeight = Math.min(maxChunkHeight, newHeight - (j * maxChunkHeight));
				chunkCanvas.width = newWidth;
				chunkCanvas.height = chunkHeight;

				// Draw the slice to the chunk canvas
				chunkCtx.drawImage(
					canvas,
					0, j * maxChunkHeight, newWidth, chunkHeight,
					0, 0, newWidth, chunkHeight
				);

				// Convert to base64 and store
				const chunkDataUrl = chunkCanvas.toDataURL('image/png');
				screeenshotFiles[`screenshot-${i}-chunk-${j}.base64`] = chunkDataUrl;
			}

			resolve();
		};
	});
}

document.getElementById('progress-prompt').textContent = 'Using AI to analyze and convert your site. This may take a while...';
document.getElementById('progressBar').removeAttribute('value');

const client = await startPlaygroundWeb({
	iframe: document.getElementById('wp-playground'),
	remoteUrl: `https://playground.wordpress.net/remote.html`,
	corsProxy: 'https://wordpress-playground-cors-proxy.net/',
	blueprint: {
		login: true,
		landingPage: '/wp-admin/',
		preferredVersions: {
			php: '8.4',
			wp: 'latest',
		},
		features: {
			networking: true,
		},
		steps: [
			{
				step: 'mkdir',
				path: '/wordpress/entrypoint',
			},
			{
				step: 'writeFiles',
				writeToPath: '/wordpress',
				filesTree: {
					resource: 'literal:directory',
					name: 'wordpress',
					files: phpFilesToPutInPlayground,
				},
			},
			{
				step: 'writeFiles',
				writeToPath: '/tmp',
				filesTree: {
					resource: 'literal:directory',
					name: 'wordpress',
					files: screeenshotFiles,
				},
			}
		],
	},
});


client.onMessage(
	(message) => {
		console.log(message);

		try {
			const { goTo } = JSON.parse(message);
			client.goTo(goTo);
		} catch (e) {
			// pass
		}
	}
);

const response = await client.run({
	code: '<?php require_once "/wordpress/wp-php-importer/entrypoint.php";',
	env: {
		ENTRY_URL: siteUrl,
		OPENAI_API_KEY: '',
		OPENAI_API_ENDPOINT: 'https://api.openai.com/v1/chat/completions',
		OPENAI_API_MODEL: 'o1-2024-12-17'
	},
});

console.log('Response', await response.text);

document.getElementById('modalContent').remove();
await client.goTo('/');

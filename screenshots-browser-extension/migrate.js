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
		'entrypoint.php': 'entrypoint.php',
		'openai-prompt.php': 'openai-prompt.php',
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
			document.getElementById('progressBar').style.width = `${percentage}%`;
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
	screeenshotFiles[`screenshot-${i}.base64`] = screenshots[i];
}

document.getElementById('progress-prompt').textContent = 'Using AI to analyze and convert your site. This may take a while...';
document.getElementById('progressBar').classList.add('indeterminate');

const client = await startPlaygroundWeb({
	iframe: document.getElementById('wp-playground'),
	remoteUrl: `https://playground.wordpress.net/remote.html`,
	corsProxy: 'https://wordpress-playground-cors-proxy.net/',
	blueprint: {
		login: true,
		landingPage: '/wp-admin/',
		preferredVersions: {
			php: '8.0',
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
			},
			{
				step: 'runPHPWithOptions',
				options: {
					code: '<?php require_once "/wordpress/wp-php-importer/entrypoint.php";',
					env: {
						ENTRY_URL: siteUrl,
						OPENAI_API_KEY: '',
					},
				},
			},
		],
	},
});

document.getElementById('modalContent').remove();


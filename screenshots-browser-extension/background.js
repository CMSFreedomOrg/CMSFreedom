// This script handles the process of repeatedly scrolling and capturing.

// Store the target tab ID for capturing
let targetTabId = null;

chrome.runtime.onMessage.addListener(async (message, sender, sendResponse) => {
	if (message.type === 'START_CAPTURE') {
		// Store the target tab ID
		targetTabId = message.tabId;
		const dims = await sendMessageToTab(targetTabId, { action: 'GET_DIMENSIONS' });
		const SIZES = [dims.totalWidth, 1024, 400];

		try {
			// We'll capture a full-page screenshot for each size in SIZES.
			for (let i = 0; i < SIZES.length; i++) {
				// Make sure the target tab is active before capturing
				await activateTab(targetTabId);
				await captureFullPage(targetTabId, SIZES[i]);
				// Wait between different sizes to avoid overwhelming the browser
				if (i < SIZES.length - 1) {
					await wait(1000);
				}
			}
			sendResponse({ status: 'done' });
			// Reset target tab ID after completion
			targetTabId = null;
		} catch (error) {
			console.error('Error during capture process:', error);
			sendResponse({ status: 'error', message: error.message });
			// Reset target tab ID on error
			targetTabId = null;
		}
	}
	return true;
});

// Rate limiter for captureVisibleTab
const captureQueue = [];
let isProcessingCapture = false;

// Process the capture queue with rate limiting
async function processNextCapture() {
	if (captureQueue.length === 0 || isProcessingCapture) {
		return;
	}

	isProcessingCapture = true;
	const { resolve, reject } = captureQueue.shift();

	try {
		// Make sure we're on the target tab before capturing
		await activateTab(targetTabId);

		// Wait a moment to ensure the tab is fully active
		await wait(50);

		// Capture the specific tab
		// Capture the specific tab
		// Get the window ID from the target tab
		let windowId = null;
		try {
			const tab = await chrome.tabs.get(targetTabId);
			windowId = tab.windowId;
		} catch (error) {
			console.error('Error getting window ID from tab:', error);
		}
		console.log({ windowId, targetTabId });
		const dataUrl = await chrome.tabs.captureVisibleTab(
			windowId, // Use the original window
			{ format: 'png', quality: 100 }
		);
		resolve(dataUrl);
	} catch (error) {
		console.error('Error capturing tab:', error);
		reject(error);
	}

	// Wait to respect the rate limit
	await wait(300); // Minimum 300ms between captures
	isProcessingCapture = false;

	// Process next item in queue
	if (captureQueue.length > 0) {
		processNextCapture();
	}
}

// Rate-limited version of captureVisibleTab
function captureVisibleTabRateLimited() {
	return new Promise((resolve, reject) => {
		captureQueue.push({ resolve, reject });
		if (!isProcessingCapture) {
			processNextCapture();
		}
	});
}

// Helper function to activate a specific tab
async function activateTab(tabId) {
	return;
	try {
		// Check if the tab still exists
		const tab = await chrome.tabs.get(tabId);
		if (!tab) {
			throw new Error(`Tab ${tabId} no longer exists`);
		}

		// Activate the tab and its window
		// await chrome.tabs.update(tabId, { active: true });
		// await chrome.windows.update(tab.windowId, { focused: true });

		// Wait a moment for the tab to become active
		await wait(200);

		return true;
	} catch (error) {
		console.error(`Error activating tab ${tabId}:`, error);
		throw error;
	}
}

// Check if content script is already injected
async function isContentScriptInjected(tabId) {
	try {
		// Try to send a test message to the content script
		const response = await sendMessageToTab(tabId, { action: 'PING' }, true);
		return response && response.success;
	} catch (error) {
		return false;
	}
}

// Inject content script and ensure it's ready
async function ensureContentScriptInjected(tabId) {
	// First check if it's already injected
	const isInjected = await isContentScriptInjected(tabId);
	if (isInjected) {
		return true;
	}

	// Inject the content script
	try {
		await chrome.scripting.executeScript({
			target: { tabId },
			files: ['contentScript.js'],
		});

		// Wait for the content script to initialize
		let attempts = 0;
		while (attempts < 5) {
			await wait(200);
			const isReady = await isContentScriptInjected(tabId);
			if (isReady) {
				return true;
			}
			attempts++;
		}

		throw new Error('Content script failed to initialize after injection');
	} catch (error) {
		console.error('Error injecting content script:', error);
		throw error;
	}
}

async function captureFullPage(tabId, width) {
	try {
		// Make sure the target tab is active
		await activateTab(tabId);

		// First resize the browser window
		const tab = await chrome.tabs.get(tabId);
		const currentWindow = await chrome.windows.get(tab.windowId);
		await chrome.windows.update(currentWindow.id, { width: width });

		// Wait for the resize to take effect
		await wait(1000); // Increased wait time for resize

		// Ensure content script is injected and ready
		console.log('Before content script');
		await ensureContentScriptInjected(tabId);
		console.log('After content script');

		// Get dimensions from the content script
		const dims = await sendMessageToTab(tabId, { action: 'GET_DIMENSIONS' });
		if (!dims) {
			throw new Error('Failed to get page dimensions');
		}
		console.log({ dims });

		const { totalWidth, totalHeight, height, devicePixelRatio } = dims;

		// How many scroll steps?
		const scrollIncrement = Math.floor(height * 0.7); // Overlap by 30% to avoid issues
		const steps = Math.ceil(totalHeight / scrollIncrement);
		console.log({ steps });

		// Remove the assumption of totalHeight and scroll until the bottom or 20,000 pixels
		let totalScrolledHeight = 0;
		const maxScrollHeight = 20000; // Upper bound for scrolling

		// Initialize canvas in content script
		const canvasInit = await sendMessageToTab(tabId, {
			action: 'INIT_CANVAS',
			width: totalWidth * devicePixelRatio,
			height: maxScrollHeight * devicePixelRatio,
		});

		if (!canvasInit || !canvasInit.success) {
			throw new Error('Failed to initialize canvas');
		}

		let step = 0;
		while (totalScrolledHeight < maxScrollHeight) {
			const scrollY = step * scrollIncrement;

			// Make sure the target tab is still active
			await activateTab(tabId);

			// Scroll the page and wait for content to stabilize
			const scrollResult = await sendMessageToTab(tabId, {
				action: 'SCROLL_TO',
				scrollY: scrollY,
			});

			if (!scrollResult || !scrollResult.success) {
				console.error('Failed to scroll page');
				break;
			}

			const actualScrollY = scrollResult.actualScrollY;
			totalScrolledHeight = actualScrollY;

			// Wait a bit after scrolling before capturing
			await wait(50);

			// Capture the viewport with rate limiting
			let dataUrl;
			try {
				dataUrl = await captureVisibleTabRateLimited();
			} catch (error) {
				console.error('Error capturing tab:', error);
				break;
			}

			if (!dataUrl) {
				console.error('No data URL returned from capture');
				break;
			}

			// Draw the captured image onto the canvas in content script
			const drawResult = await sendMessageToTab(tabId, {
				action: 'DRAW_IMAGE',
				dataUrl: dataUrl,
				x: 0,
				y: actualScrollY * devicePixelRatio,
			});

			if (!drawResult || !drawResult.success) {
				console.error('Failed to draw image on canvas');
				break;
			}

			// Send progress
			chrome.runtime.sendMessage({
				type: 'PROGRESS_UPDATE',
				completed: step + 1,
				total: Math.ceil(maxScrollHeight / scrollIncrement),
			});

			if (step === 0) {
				// Hide any elements that stick around on scrolling. We don't
				// want them to be visible in every segment of the screenshot.
				await sendMessageToTab(tabId, {
					action: 'HIDE_STICKY_ELEMENTS',
				});
			}

			if (actualScrollY !== scrollY) {
				break;
			}

			// Wait between captures to avoid running into the chrome security timeout.
			await wait(50);

			step++;
		}

		// Get the final screenshot from content script
		const finalResult = await sendMessageToTab(tabId, { action: 'GET_FINAL_SCREENSHOT' });
		if (!finalResult || !finalResult.success) {
			throw new Error('Failed to get final screenshot');
		}

		// Save the screenshot directly using the array buffer data
		const timestamp = new Date().toISOString().replace(/:/g, '-');
		const filename = `screenshot-${width}px-${timestamp}.png`;

		await chrome.downloads.download({
			url: finalResult.imageData,
			filename: filename,
			saveAs: false,
		});

		console.log(`Screenshot (width ${width}) saved as ${filename}`);
	} catch (error) {
		console.error(`Error capturing page at width ${width}:`, error);
		throw error; // Re-throw to be handled by the caller
	}
}

// Helper function to convert ArrayBuffer to base64 string
function arrayBufferToBase64(buffer) {
	let binary = '';
	const bytes = new Uint8Array(buffer);
	const len = bytes.byteLength;
	for (let i = 0; i < len; i++) {
		binary += String.fromCharCode(bytes[i]);
	}
	return btoa(binary);
}

// Helper function to send a message to a tab and get a response
async function sendMessageToTab(tabId, message, silent = false) {
	try {
		// Send a PING message to check readiness
		const pingResponse = await new Promise((resolve) => {
			chrome.tabs.sendMessage(tabId, { action: 'PING' }, (response) => {
				resolve(response);
			});
		});

		if (!pingResponse || !pingResponse.ready) {
			throw new Error('Content script not ready');
		}

		// Send the actual message
		return new Promise((resolve, reject) => {
			chrome.tabs.sendMessage(tabId, message, (response) => {
				if (chrome.runtime.lastError) {
					if (!silent) {
						console.error('Error sending message:', chrome.runtime.lastError);
					}
					resolve(null);
				} else {
					resolve(response);
				}
			});
		});
	} catch (error) {
		console.error('Error ensuring content script is ready:', error);
		throw error;
	}
}

function wait(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

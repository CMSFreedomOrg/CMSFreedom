// Background script (service worker) for coordinating screenshot captures
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
	if (message.action === 'startScreenshots') {
		// Handle the screenshot capture sequence
		(async () => {
			// Get the current active tab and window
			const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
			if (!tab) {
				sendResponse({ status: 'error', message: 'No active tab' });
				return;
			}
			const tabId = tab.id;
			const windowId = tab.windowId;
			// Instruct content script to show overlay notification
			chrome.tabs.sendMessage(tabId, {
				action: 'showOverlay',
				message: 'Capturing screenshots...',
			});
			// Save current window size to restore later
			const currentWin = await chrome.windows.get(windowId);
			const originalWidth = currentWin.width;
			const originalHeight = currentWin.height;
			// Define target screen sizes for mobile, tablet, desktop
			const sizes = [
				{ name: 'mobile', width: 375, height: 667 },
				{ name: 'tablet', width: 768, height: 1024 },
				{ name: 'desktop', width: originalWidth, height: originalHeight },
			];
			// Loop through each device size and capture full-page screenshot
			for (const size of sizes) {
				// Resize the browser window to simulate the device dimensions
				await chrome.windows.update(windowId, { width: size.width, height: size.height });
				// Brief delay to allow page layout to adjust to new size
				await new Promise((r) => setTimeout(r, 500));
				// Trigger the content script to capture the full page at this size
				const imageData = await captureFullPage(tabId);
				// Save the captured image data as a file in Downloads
				const filename = `screenshot-${size.name}.png`;
				chrome.downloads.download({ url: imageData, filename: filename, saveAs: false });
			}
			// Restore the original window size
			await chrome.windows.update(windowId, { width: originalWidth, height: originalHeight });
			// Remove the overlay notification
			chrome.tabs.sendMessage(tabId, { action: 'hideOverlay' });
			// Respond to popup (if still open) that we're done
			sendResponse({ status: 'completed' });
		})();
		// Return true to indicate we'll send a response asynchronously
		return true;
	} else if (message.action === 'captureSegment') {
		// Handle a request from content script to capture the current visible area
		chrome.tabs.captureVisibleTab(sender.tab.windowId, { format: 'png' }, (dataUrl) => {
			sendResponse({ data: dataUrl });
		});
		return true; // Keep the message channel open for sendResponse
	}
});

// Helper function: wait for content script to finish full-page capture and return image
function captureFullPage(tabId) {
	return new Promise((resolve) => {
		// One-time listener for the final image data from content script
		function onMessage(msg, sender) {
			if (msg.action === 'capturedFull' && sender.tab && sender.tab.id === tabId) {
				chrome.runtime.onMessage.removeListener(onMessage);
				resolve(msg.data); // Final stitched image as data URL
			}
		}
		chrome.runtime.onMessage.addListener(onMessage);
		// Tell content script to start capturing the page
		chrome.tabs.sendMessage(tabId, { action: 'startCapture' });
	});
}

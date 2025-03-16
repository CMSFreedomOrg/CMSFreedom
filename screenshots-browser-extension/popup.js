const captureBtn = document.getElementById('captureBtn');
const currentState = document.getElementById('currentState');
const progressBar = document.getElementById('progressBar');
const statusMessage = document.getElementById('statusMessage');
const modal = document.getElementById('modal');

// Store the target tab ID
let targetTabId = null;

// Get the current tab when popup opens
chrome.tabs.query({ active: true, currentWindow: true }, tabs => {
	if (tabs && tabs.length > 0 && tabs[0].id) {
		targetTabId = tabs[0].id;

		// Check if this is a valid tab we can capture
		const url = tabs[0].url || '';
		if (
			url.startsWith('chrome:') ||
			url.startsWith('chrome-extension:') ||
			url.startsWith('devtools:')
		) {
			captureBtn.disabled = true;
			statusMessage.textContent =
				'Cannot capture this page. Please navigate to a regular web page.';
		} else {
			statusMessage.textContent = '';
		}
	} else {
		captureBtn.disabled = true;
		statusMessage.textContent = 'No valid tab found.';
	}
});

captureBtn.addEventListener('click', async () => {
	if (!targetTabId) {
		statusMessage.textContent = 'No valid tab to capture.';
		return;
	}

	chrome.windows.create({
		url: 'migrate.html',
		type: 'popup',
		width: 800,
		height: 600
	});

	currentState.style.display = 'block';
	statusMessage.textContent = 'Taking screenshots... don\'t touch the page.';
	captureBtn.disabled = true;
	progressBar.style.width = '0%';

	try {
		// Make sure the target tab is still valid
		const tab = await chrome.tabs.get(targetTabId).catch(() => null);
		if (!tab) {
			statusMessage.textContent = 'Target tab no longer exists.';
			captureBtn.disabled = false;
			return;
		}

		// Listen for progress updates
		chrome.runtime.onMessage.addListener(function listener(msg) {
			if (msg.type === 'PROGRESS_UPDATE') {
				const { completed, total } = msg;
				const percentage = Math.round((completed / total) * 100);
				progressBar.style.width = `${percentage}%`;
				if (completed === total) {
					chrome.runtime.onMessage.removeListener(listener);
				}
			}
		});

		// Trigger the background process with the stored tab ID
		const response = await chrome.runtime.sendMessage({ 
			type: 'START_CAPTURE', 
			tabId: targetTabId 
		});
		
		if (response && response.status === 'done') {
			statusMessage.textContent = 'Screenshots captured successfully! Check your downloads folder.';
		} else if (response && response.status === 'error') {
			statusMessage.textContent = `Error: ${response.message || 'Unknown error'}`;
		} else {
			statusMessage.textContent = 'Finished capturing, but with unknown status.';
		}
	} catch (err) {
		console.error('Error during capture:', err);
		statusMessage.textContent = `Error capturing screenshots: ${err.message || 'Unknown error'}`;
	}

	captureBtn.disabled = false;
});

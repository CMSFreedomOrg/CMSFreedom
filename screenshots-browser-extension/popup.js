const statusEl = document.getElementById('status');
const captureBtn = document.getElementById('captureBtn');

captureBtn.addEventListener('click', () => {
	statusEl.textContent = 'Capturing screenshots...';
	// Send message to background to start the screenshot process
	chrome.runtime.sendMessage({ action: 'startScreenshots' }, (response) => {
		if (chrome.runtime.lastError) {
			statusEl.textContent = 'Error starting capture.';
			console.error(chrome.runtime.lastError);
		} else if (response && response.status === 'completed') {
			// Update status when done
			statusEl.textContent = 'Screenshots saved.';
		}
	});
});

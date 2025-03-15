// Content script: handles overlay display and full-page capture by scrolling
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
	if (msg.action === 'showOverlay') {
		// Create a full-page overlay to prevent interaction and show a message
		const overlay = document.createElement('div');
		overlay.id = 'screenshot-overlay';
		overlay.style.position = 'fixed';
		overlay.style.top = 0;
		overlay.style.left = 0;
		overlay.style.width = '100%';
		overlay.style.height = '100%';
		overlay.style.backgroundColor = 'rgba(0,0,0,0.3)';
		overlay.style.color = '#fff';
		overlay.style.display = 'flex';
		overlay.style.alignItems = 'center';
		overlay.style.justifyContent = 'center';
		overlay.style.fontSize = '24px';
		overlay.style.zIndex = '999999999';
		overlay.textContent = msg.message || 'Taking screenshot...';
		document.body.appendChild(overlay);
		// No response needed
	} else if (msg.action === 'hideOverlay') {
		const overlay = document.getElementById('screenshot-overlay');
		if (overlay) overlay.remove();
		// No response needed
	} else if (msg.action === 'startCapture') {
		// Scroll to top to begin full-page capture
		window.scrollTo(0, 0);
		const totalWidth = document.documentElement.scrollWidth;
		const totalHeight = document.documentElement.scrollHeight;
		const viewportHeight = window.innerHeight;
		// Prepare an off-screen canvas to stitch screenshots
		const canvas = document.createElement('canvas');
		canvas.width = totalWidth;
		canvas.height = totalHeight;
		const context = canvas.getContext('2d');
		let scrollY = 0;
		// Capture and scroll in segments until the entire page is done
		function captureNextSegment() {
			chrome.runtime.sendMessage({ action: 'captureSegment' }, (response) => {
				if (response && response.data) {
					// Draw the captured segment onto the canvas at the correct position
					const img = new Image();
					img.onload = () => {
						context.drawImage(img, 0, scrollY);
						scrollY += viewportHeight;
						if (scrollY < totalHeight) {
							// Scroll down by one viewport and capture next part
							window.scrollTo(0, scrollY);
							setTimeout(captureNextSegment, 150); // slight delay for rendering
						} else {
							// All segments captured (page bottom reached or exceeded)
							finalizeCapture();
						}
					};
					img.src = response.data;
				} else {
					// In case of an error, finalize with what we have
					finalizeCapture();
				}
			});
		}
		function finalizeCapture() {
			// Send the complete screenshot image (as data URL) back to the background script
			const fullImageData = canvas.toDataURL('image/png');
			chrome.runtime.sendMessage({ action: 'capturedFull', data: fullImageData });
		}
		// Start the first capture after a short delay to ensure initial render
		setTimeout(captureNextSegment, 100);
	}
});

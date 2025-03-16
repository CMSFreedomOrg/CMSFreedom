(function () {
	if (window.CMS_FREEDOM_SCREENSHOT) {
		return;
	}
	window.CMS_FREEDOM_SCREENSHOT = true;

	// Signal that the content script is loaded
	console.log('Screenshot content script loaded');

	// Track images that are currently loading
	let loadingImages = new Set();
	// Store canvas for screenshot stitching
	let screenshotCanvas = null;
	let screenshotCtx = null;
	// Track if we're currently processing a screenshot
	let isProcessingScreenshot = false;
	// Flag to indicate the script is ready
	let isScriptReady = false;

	// Initialize the script
	function initializeScript() {
		try {
			// Start tracking images
			trackAllImages();

			// Start observing for new images
			startImageObserver();

			// Mark script as ready
			isScriptReady = true;

			console.log('Screenshot content script initialized');
		} catch (error) {
			console.error('Error initializing content script:', error);
		}
	}

	// Observer to detect when new images are added to the page
	let imageObserver = null;

	function startImageObserver() {
		// Create the observer if it doesn't exist
		if (!imageObserver) {
			imageObserver = new MutationObserver((mutations) => {
				for (const mutation of mutations) {
					if (mutation.type === 'childList') {
						for (const node of mutation.addedNodes) {
							if (node.nodeName === 'IMG') {
								trackImage(node);
							} else if (node.querySelectorAll) {
								const images = node.querySelectorAll('img');
								images.forEach(trackImage);
							}
						}
					}
				}
			});
		}

		// Start observing the document with a try-catch to handle potential errors
		try {
			imageObserver.observe(document.documentElement, {
				childList: true,
				subtree: true,
			});
		} catch (error) {
			console.error('Error starting image observer:', error);
		}
	}

	// Track all existing images
	function trackAllImages() {
		try {
			document.querySelectorAll('img').forEach(trackImage);
		} catch (error) {
			console.error('Error tracking initial images:', error);
		}
	}

	// Track an image for loading
	function trackImage(img) {
		try {
			if (!img.complete && !loadingImages.has(img)) {
				loadingImages.add(img);
				img.addEventListener(
					'load',
					() => {
						loadingImages.delete(img);
					},
					{ once: true }
				);
				img.addEventListener(
					'error',
					() => {
						loadingImages.delete(img);
					},
					{ once: true }
				);
			}
		} catch (error) {
			console.error('Error tracking image:', error);
		}
	}

	// Function to check if all images are loaded
	function areAllImagesLoaded() {
		return loadingImages.size === 0;
	}

	// Function to scroll to a specific position and wait for content to stabilize
	async function scrollAndWaitForContent(scrollY) {
		return new Promise(async (resolve) => {
			try {
				// Scroll to the position
				window.scrollTo({
					top: scrollY,
					behavior: 'instant', // Use instant instead of smooth for more reliable screenshots
				});

				// Wait for initial scroll to complete
				await new Promise((r) => setTimeout(r, 200));

				// Track layout stability
				let lastHeight = document.body.scrollHeight;
				let stableCount = 0;
				let timeout = setTimeout(() => {
					// Safety timeout after 5 seconds
					console.log('Stability timeout reached, continuing anyway');
					resolve();
				}, 500);

				const checkStability = async () => {
					try {
						// Wait for all images to load
						if (!areAllImagesLoaded()) {
							setTimeout(checkStability, 50);
							return;
						}

						// Check if layout has stabilized
						const currentHeight = document.body.scrollHeight;
						if (currentHeight === lastHeight) {
							stableCount++;
							if (stableCount >= 3) {
								// Stable for 3 consecutive checks
								clearTimeout(timeout);
								resolve();
								return;
							}
						} else {
							stableCount = 0;
							lastHeight = currentHeight;
						}

						setTimeout(checkStability, 100);
					} catch (error) {
						console.error('Error in stability check:', error);
						clearTimeout(timeout);
						resolve(); // Continue anyway on error
					}
				};

				checkStability();
			} catch (error) {
				console.error('Error in scrollAndWaitForContent:', error);
				resolve(); // Continue anyway on error
			}
		});
	}

	// Function to get dimensions without changing the page
	function getDimensions() {
		try {
			const devicePixelRatio = window.devicePixelRatio || 1;
			const width = window.innerWidth * devicePixelRatio;
			const height = window.innerHeight * devicePixelRatio;
			const totalWidth =
				Math.max(document.body.scrollWidth, document.documentElement.scrollWidth) *
				devicePixelRatio;
			const totalHeight =
				Math.max(document.body.scrollHeight, document.documentElement.scrollHeight) *
				devicePixelRatio;

			return { width, height, totalWidth, totalHeight, devicePixelRatio };
		} catch (error) {
			console.error('Error getting dimensions:', error);
			return {
				width: 800,
				height: 600,
				totalWidth: 800,
				totalHeight: 600,
				devicePixelRatio: 1,
			};
		}
	}

	// Initialize canvas for screenshot stitching
	function initScreenshotCanvas(width, height) {
		try {
			screenshotCanvas = document.createElement('canvas');
			screenshotCanvas.width = width;
			screenshotCanvas.height = height;
			screenshotCtx = screenshotCanvas.getContext('2d');
			return { success: true };
		} catch (error) {
			console.error('Error initializing canvas:', error);
			return { success: false, error: error.message };
		}
	}

	function hideStickyElements() {
		const stickyElements = [];
		
		// Find elements with position: fixed or sticky
		const allElements = document.querySelectorAll('*');
		allElements.forEach(element => {
			const computedStyle = window.getComputedStyle(element);
			const position = computedStyle.getPropertyValue('position');
			
			if (position === 'fixed' || position === 'sticky') {
				// Store original styles before modifying
				stickyElements.push({
					element: element,
					originalPosition: position,
					originalDisplay: computedStyle.getPropertyValue('display'),
					originalVisibility: computedStyle.getPropertyValue('visibility')
				});
				
				// Hide the element
				element.style.setProperty('display', 'none', 'important');
				// Alternative approach if needed:
				// element.style.setProperty('visibility', 'hidden', 'important');
				// element.style.setProperty('position', 'absolute', 'important');
				// element.style.setProperty('z-index', '-9999', 'important');
			}
		});
		
		console.log(`Hidden ${stickyElements.length} sticky/fixed elements for screenshot`);
		
		// Return function to restore elements if needed
		return function restoreStickyElements() {
			stickyElements.forEach(item => {
				item.element.style.position = item.originalPosition;
				item.element.style.display = item.originalDisplay;
				item.element.style.visibility = item.originalVisibility;
			});
			console.log(`Restored ${stickyElements.length} sticky/fixed elements`);
		};
	}

	// Draw image on canvas at specified position
	async function drawImageOnCanvas(dataUrl, x, y) {
		return new Promise((resolve) => {
			try {
				const img = new Image();
				img.crossOrigin = 'anonymous'; // Try to handle cross-origin images

				img.onload = () => {
					try {
						screenshotCtx.drawImage(img, x, y);
						resolve({ success: true });
					} catch (error) {
						console.error('Error drawing image on canvas:', error);
						resolve({ success: false, error: error.message });
					}
				};

				img.onerror = (error) => {
					console.error('Error loading image:', error);
					resolve({ success: false, error: 'Failed to load image' });
				};

				img.src = dataUrl;
			} catch (error) {
				console.error('Error in drawImageOnCanvas:', error);
				resolve({ success: false, error: error.message });
			}
		});
	}

	// Get the final screenshot as a blob
	function getFinalScreenshot() {
		return new Promise((resolve) => {
			try {
				screenshotCanvas.toBlob((blob) => {
					console.log({ blob });
					// Render blob on the page for debugging/preview
					const blobUrl = URL.createObjectURL(blob);
					const previewImg = document.createElement('img');
					previewImg.src = blobUrl;
					previewImg.style.position = 'fixed';
					previewImg.style.top = '10px';
					previewImg.style.right = '10px';
					previewImg.style.maxWidth = '300px';
					previewImg.style.maxHeight = '300px';
					previewImg.style.border = '2px solid red';
					previewImg.style.zIndex = '9999';
					document.body.appendChild(previewImg);

					// Clean up the blob URL when the image is removed
					previewImg.onload = () => URL.revokeObjectURL(blobUrl);
					if (blob) {
						resolve(blob);
					} else {
						console.error('Failed to create blob from canvas');
						resolve(new Blob([], { type: 'image/png' })); // Empty blob as fallback
					}
				}, 'image/png');
			} catch (error) {
				console.error('Error getting final screenshot:', error);
				resolve(new Blob([], { type: 'image/png' })); // Empty blob as fallback
			}
		});
	}

	// Listen for messages from the background script
	console.log('Content scfipt ran');
	chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
		try {
			// Handle ping to check if content script is ready
			console.log('Ping received');
			if (message.action === 'PING') {
				sendResponse({ success: true, ready: isScriptReady });
				return;
			}

			// For all other actions, ensure script is ready
			if (!isScriptReady) {
				sendResponse({ success: false, error: 'Content script not fully initialized' });
				return;
			}

			if (message.action === 'GET_DIMENSIONS') {
				sendResponse(getDimensions());
			} else if (message.action === 'SCROLL_TO') {
				if (isProcessingScreenshot) {
					// If we're already processing, wait a bit
					setTimeout(() => {
						scrollAndWaitForContent(message.scrollY).then(() =>
							sendResponse({ success: true, actualScrollY: window.scrollY })
						);
					}, 100);
				} else {
					isProcessingScreenshot = true;
					scrollAndWaitForContent(message.scrollY).then(() => {
						isProcessingScreenshot = false;
						sendResponse({ success: true, actualScrollY: window.scrollY });
					});
				}
				return true; // Indicates async response
			} else if (message.action === 'HIDE_STICKY_ELEMENTS') {
				hideStickyElements();
				sendResponse({ success: true });
				return true; // Indicates async response
			} else if (message.action === 'INIT_CANVAS') {
				const result = initScreenshotCanvas(message.width, message.height);
				sendResponse(result);
			} else if (message.action === 'DRAW_IMAGE') {
				console.log('Drawing image', message.x, message.y);
				drawImageOnCanvas(message.dataUrl, message.x, message.y).then((result) =>
					sendResponse(result)
				);
				return true; // Indicates async response
			} else if (message.action === 'GET_FINAL_SCREENSHOT') {
				getFinalScreenshot().then((blob) => {
					try {
						// Convert blob to array buffer for sending via message
						const reader = new FileReader();
						reader.onloadend = () => {
							sendResponse({
								success: true,
								imageData: reader.result,
							});
						};
						reader.onerror = (error) => {
							console.error('Error reading blob:', error);
							sendResponse({ success: false, error: 'Failed to read blob' });
						};
						reader.readAsArrayBuffer(blob);
					} catch (error) {
						console.error('Error processing final screenshot:', error);
						sendResponse({ success: false, error: error.message });
					}
				});
				return true; // Indicates async response
			}
		} catch (error) {
			console.error('Error handling message:', error);
			sendResponse({ success: false, error: error.message });
		}
	});

	// Initialize the script when loaded
	initializeScript();
})();

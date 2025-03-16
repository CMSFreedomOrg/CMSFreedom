import { startPlaygroundWeb } from './playground-client.js';

const client = await startPlaygroundWeb({
	iframe: document.getElementById('wp-playground'),
	remoteUrl: `https://playground.wordpress.net/remote.html`,
	blueprint: {
		landingPage: '/wp-admin/',
		preferredVersions: {
			php: '8.0',
			wp: 'latest',
		},
		steps: [
			{
				step: 'login',
				username: 'admin',
				password: 'password',
			},
			{
				step: 'installPlugin',
				pluginData: {
					resource: 'wordpress.org/plugins',
					slug: 'friends',
				},
			},
		],
	},
});

const response = await client.run({
	// wp-load.php is only required if you want to interact with WordPress.
	code: '<?php require_once "/wordpress/wp-load.php"; $posts = get_posts(); echo "Post Title: " . $posts[0]->post_title;',
});
console.log(response.text);

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
	}
});

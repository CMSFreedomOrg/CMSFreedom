module.exports = {
	env: {
		browser: true,
		es2022: true,
		webextensions: true, // This enables the chrome global
	},
	extends: ['eslint:recommended'],
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module',
	},
	rules: {
		// Add any custom rules here
		'prefer-const': 'error',
		'no-var': 'error',
		'prefer-arrow-callback': 'error',
		'prefer-template': 'error',
		'object-shorthand': 'error',
	},
};

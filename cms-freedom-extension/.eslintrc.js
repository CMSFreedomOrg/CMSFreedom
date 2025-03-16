module.exports = {
  env: {
    browser: true,
    es2020: true,
    webextensions: true // This enables the chrome global
  },
  extends: [
    'eslint:recommended'
  ],
  parserOptions: {
    ecmaVersion: 2020,
    sourceType: 'module'
  },
  rules: {
    // Add any custom rules here
  }
}; 
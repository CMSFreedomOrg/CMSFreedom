# CMS Freedom Screenshots Browser Extension

A browser extension for capturing screenshots of websites at different device sizes.

## Development Setup

1. Install dependencies:
   ```
   npm install
   ```

2. VSCode Setup for Chrome API Autocompletion:
   - The project includes a `jsconfig.json` file that configures TypeScript/JavaScript support
   - Chrome API types are provided by the `@types/chrome` package
   - ESLint is configured to recognize the `chrome` global object

3. Recommended VSCode Extensions:
   - ESLint
   - Chrome Extension Development Tools

## Building and Testing

1. Load the extension in Chrome:
   - Open Chrome and navigate to `chrome://extensions/`
   - Enable "Developer mode"
   - Click "Load unpacked" and select the extension directory

## Project Structure

- `manifest.json`: Extension configuration
- `background.js`: Service worker for coordinating screenshot captures
- `contentScript.js`: Script injected into web pages to capture screenshots
- `popup.html/js`: Extension popup UI 
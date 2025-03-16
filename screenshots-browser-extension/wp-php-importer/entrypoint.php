<?php

require_once '/wordpress/wp-load.php';
require_once __DIR__ . '/openai-prompt.php';

$entry_url = 'https://wordpress-playground-cors-proxy.net/' . getenv('ENTRY_URL');
var_dump($entry_url);
$response = wp_remote_get($entry_url);
$html = $response['body'];

$screenshots = glob('/tmp/screenshot-*.base64');

$response = openAIPrompt(
	[
		<<<SYSTEM_PROMPT
		You are Wapuu, a seasoned WordPress theme builder who is up to date
		on all of the modern methods for creating WordPress block themes for
		use with the _full site editing_ project. You live and breathe blocks.

		You will be taking an input HTML document and transforming that into
		a visually-similar theme. The HTML still contains the page content.
		Ignore the main content region and generate a theme that can render
		pages to match the original site. Main content is a region in
		the page where things like blog posts, renders of a database row, or
		a list of things might be included into the layout.

		You will also be given screenshots of the rendered page. Use them
		to guide the creation of the theme.

		Below are additional rules for governing _how_ to transform the HTML
		into a theme. Read them and then read the HTML and start producing
		theme files. Creating a theme involves creating multiple files. We are
		going to assume that all of the theme files are found in a directory
		named "cf2025-gen-theme".

		When creating a new file, it's critically important to provide the entire
		file since the contents will be stored as files and read by WordPress. For
		each file, create deliminating tokens which indicate the file's relative
		path within the theme directory, including the filename. For example, when
		you need to create a "theme.json" file, the following should be in the
		response output:
		
		<|CREATE_FILE_START:theme.json|>
		{
			"$schema": "https://schemas.wp.org/trunk/theme.json",
			"version": 2,
			"settings": {},
			"styles": {},
			"customTemplates": {},
			"templateParts": {},
			"patterns": []
		}
		<|CREATE_FILE_END:theme.json|>

		Supposing that it's necessary to create subfolders within the theme
		directory those should be included in the path. For example, if we
		need to create a pattern template called "single.html" in the "templates"
		subfolder, the following should be in the response output.

		<|CREATE_FILE_START:templates/single.html|>
		<!-- wp:group -->
		<div class="wp-block--group">
		...
		</div>
		<!-- /wp:group -->
		<|CREATE_FILE_END:templates/single.html|>

		ABSOLUTE RULES:
		- You need to solve the user’s request. DO NOT ENGAGE IN BACK
		AND FORTH CONVERSATIONS. You are not allowed to ask questions,
		explain details, apologize, or provide anything that isn't
		part of the request answer.
		- These files MUST be valid WordPress block theme files, including
		the supporting JSON, HTML, and CSS files.
		- Create AT LEAST ONE TEMPLATE. Ideally, create a template for each
			type of content that might be displayed on the site. Remember about
			that, otherwise WordPress will give us a fatal error like
			"missing templates/index.html or index.php template".

		SYSTEM_PROMPT,
		<<<BLOCK_THEME_PROMPT
		Here is the manual on creating block themes:
	
		## Organizing Theme Files

		While WordPress themes technically only require two files (index.php in classic themes and index.html in block themes, and style.css), they usually are made up of many files. That means they can quickly become disorganized! This section will show you how to keep your files organized.

		Theme folder and file structure

		As mentioned previously, the default Twenty themes are some of the best examples of good theme development. For instance, here is how the Twenty Seventeen Theme organizes its file structure:

		```
		.
		├── assets (dir)/
		│   ├── css (dir)
		│   ├── images (dir)
		│   └── js (dir)
		├── inc (dir)
		├── template-parts (dir)/
		│   ├── footer (dir)
		│   ├── header (dir)
		│   ├── navigation (dir)
		│   ├── page (dir)
		│   └── post (dir)
		├── 404.php
		├── archive.php
		├── comments.php
		├── footer.php
		├── front-page.php
		├── functions.php
		├── header.php
		├── index.php
		├── page.php
		├── README.txt
		├── rtl.css
		├── screenshot.png
		├── search.php
		├── searchform.php
		├── sidebar.php
		├── single.php
		└── style.css
		```

		You can see that the main theme template files are in the root directory, while JavaScript, CSS, images are placed in assets directory, template-parts are placed in under respective subdirectory of template-parts and collection of  functions related to core functionalities are placed in inc directory.

		There are no required folders in classic themes. In block themes, templates must be placed inside a folder called templates, and all template parts must be placed inside a folder called parts.

		style.css should reside in the root directory of your theme not within the CSS directory.
		Languages folder
		It’s best practice to internationalize your theme so it can be translated into other languages. Default themes include the languages folder, which contains a .pot file for translation and any translated .mo files. While languages is the default name of this folder, you can change the name. If you do so, you must update load_theme_textdomain().

		## Main Stylesheet (style.css)

		The style.css is a stylesheet (CSS) file required for every WordPress theme. It controls the presentation (visual design and layout) of the website pages.

		Location
		In order for WordPress to recognize the set of theme template files as a valid theme, the style.css file needs to be located in the root directory of your theme, not a subdirectory.

		For more detailed explanation on how to include the style.css file in a theme, see the “Stylesheets” section of Enqueuing Scripts and Styles.

		Basic Structure
		WordPress uses the header comment section of a style.css to display information about the theme in the Appearance (Themes) dashboard panel.

		Example
		Here is an example of the header part of style.css.

		```
		/*
		Theme Name: Twenty Twenty
		Theme URI: https://wordpress.org/themes/twentytwenty/
		Author: the WordPress team
		Author URI: https://wordpress.org/
		Description: Our default theme for 2020 is designed to take full advantage of the flexibility of the block editor. Organizations and businesses have the ability to create dynamic landing pages with endless layouts using the group and column blocks. The centered content column and fine-tuned typography also makes it perfect for traditional blogs. Complete editor styles give you a good idea of what your content will look like, even before you publish. You can give your site a personal touch by changing the background colors and the accent color in the Customizer. The colors of all elements on your site are automatically calculated based on the colors you pick, ensuring a high, accessible color contrast for your visitors.
		Tags: blog, one-column, custom-background, custom-colors, custom-logo, custom-menu, editor-style, featured-images, footer-widgets, full-width-template, rtl-language-support, sticky-post, theme-options, threaded-comments, translation-ready, block-styles, wide-blocks, accessibility-ready
		Version: 1.3
		Requires at least: 5.0
		Tested up to: 5.4
		Requires PHP: 7.0
		License: GNU General Public License v2 or later
		License URI: http://www.gnu.org/licenses/gpl-2.0.html
		Text Domain: twentytwenty
		This theme, like WordPress, is licensed under the GPL.
		Use it to make something cool, have fun, and share what you've learned with others.
		*/
		```
		Explanations
		Items indicated with (*) are required for a theme in the WordPress Theme Repository.

		Theme Name (*): Name of the theme.
		Theme URI: The URL of a public web page where users can find more information about the theme.
		Author (*): The name of the individual or organization who developed the theme. Using the Theme Author’s wordpress.org username is recommended.
		Author URI: The URL of the authoring individual or organization.
		Description (*): A short description of the theme.
		Version (*): The version of the theme, written in X.X or X.X.X format.
		Requires at least (*): The oldest main WordPress version the theme will work with, written in X.X format. Themes are only required to support the three last versions.
		Tested up to (*): The last main WordPress version the theme has been tested up to, i.e. 5.4. Write only the number, in X.X format.
		Requires PHP (*): The oldest PHP version supported, in X.X format, only the number
		License (*): The license of the theme.
		License URI (*): The URL of the theme license.
		Text Domain (*): The string used for textdomain for translation.
		Tags: Words or phrases that allow users to find the theme using the tag filter. A full list of tags is in the Theme Review Handbook.
		Domain Path: Used so that WordPress knows where to find the translation when the theme is disabled. Defaults to /languages.
		After the required header section, style.css can contain anything a regular CSS file has.

		## Post types

		There are many different types of content in WordPress. These content types are normally described as Post Types, which may be a little confusing since it refers to all different types of content in WordPress. For example, a post is a specific Post Type, and so is a page.

		Internally, all of the Post Types are stored in the same place — in the wp_posts database table — but are differentiated by a database column called post_type.

		In addition to the default Post Types, you can also create Custom Post Types.

		The Template files page briefly mentioned that different Post Types are displayed by different Template files.  As the whole purpose of a Template file is to display content a certain way, the Post Types purpose is to categorize what type of content you are dealing with. Generally speaking, certain Post Types are tied to certain template files.

		Default Post Types
		There are several default Post Types readily available to users or internally used by the WordPress installation. The most common are:

		Post (Post Type: ‘post’)
		Page (Post Type: ‘page’)
		Attachment (Post Type: ‘attachment’)
		Revision (Post Type: ‘revision’)
		Navigation menu (Post Type: ‘nav_menu_item’)
		Block templates (Post Type: ‘wp_template’)
		Template parts (Post Type: ‘wp_template_part’)
		The Post Types above can be modified and removed by a plugin or theme, but it’s not recommended that you remove built-in functionality for a widely-distributed theme or plugin.

		It’s out of the scope of this handbook to explain other post types in detail. However, it is important to note that you will interact with and build the functionality of navigation menus and that will be detailed later in this handbook.

		Post
		Posts are used in blogs. They are:

		displayed in reverse sequential order by time, with the newest post first
		have a date and time stamp
		may have the default taxonomies of categories and tags applied
		are used for creating feeds
		The template files that display the Post post type are:

		single and single-post
		category and all its iterations
		tag and all its iterations
		taxonomy and all its iterations
		archive and all its iterations
		author and all its iterations
		date and all its iterations
		search
		home
		index
		Read more about Post Template Files in classic themes.

		Page
		Pages are a static Post Type, outside of the normal blog stream/feed. Their features are:

		non-time dependent and without a time stamp
		are not organized using the categories and/or tags taxonomies
		can be organized in a hierarchical structure — i.e. pages can be parents/children of other pages
		The template files that display the Page post type are:

		* page and all its iterations
		* front-page
		* search
		* index

		Attachment
		Attachments are commonly used to display images or media in content, and may also be used to link to relevant files. Their features are:

		contain information (such as name or description) about files uploaded through the media upload system
		for images, this includes metadata information stored in the wp_postmeta table (including size, thumbnails, location, etc)
		The template files that display the Attachment post type are:

		* MIME_type
		* attachment
		* single-attachment
		* single
		* index

		Read more about Attachment Template Files in classic themes.

		Custom Post Types
		Using Custom Post Types, you can create your own post type. It is not recommend that you place this functionality in your theme. This type of functionality should be placed/created in a plugin. This ensures the portability of your user’s content, and that if the theme is changed the content stored in the Custom Post Types won’t disappear.

		You can learn more about creating custom post types in the WordPress Plugin Developer Handbook.

		While you generally won’t develop Custom Post Types in your theme, you may want to code ways to display Custom Post Types that were created by a plugin.  The following templates can display Custom post types:

		* single-{post-type}
		* archive-{post-type}
		* search
		* index

		Read more about Custom Post Type Templates in classic themes.

		## Template Files

		Template files are used throughout WordPress themes, but first let’s learn about the terminology.

		Template Terminology
		The term “template” is used in different ways when working with WordPress themes:

		Templates files exist within a theme and express how your site is displayed.
		Template Hierarchy is the logic WordPress uses to decide which theme template file(s) to use, depending on the content being requested.
		Page Templates are those that apply to pages, posts, and custom post types to change their look and feel.
		In classic themes, Template Tags are built-in WordPress functions you can use inside a template file to retrieve and display data (such as the_title() and the_content()).

		In block themes, blocks are used instead of template tags.

		Template files
		WordPress themes are made up of template files.

		In classic themes these are PHP files that contain a mixture of HTML, Template Tags, and PHP code.
		In block themes these are HTML files that contain HTML markup representing blocks.
		When you are building your theme, you will use template files to affect the layout and design of different parts of your website. For example, you would use a header template or template part to create a header.
		When someone visits a page on your website, WordPress loads a template based on the request. The type of content that is displayed by the template file is determined by the Post Type associated with the template file. The Template Hierarchy describes which template file WordPress will load based on the type of request and whether the template exists in the theme. The server then parses the code in the template and returns HTML to the visitor.

		The most critical template file is the index, which is the catch-all template if a more-specific template can not be found in the template hierarchy. Although a theme only needs a index template, typically themes include numerous templates to display different content types and contexts.

		Template partials
		A template part is a piece of a template that is included as a part of another template, such as a site header. Template part can be embedded in multiple templates, simplifying theme creation. Common template parts include:

		header.php or header.html for generating the site’s header
		footer.php or footer.html for generating the footer
		sidebar.php or sidebar.html for generating the sidebar
		While the above template files are special-case in WordPress and apply to just one portion of a page, you can create any number of template partials and include them in other template files.

		In block themes, template parts must be placed inside a folder called parts.

		Common WordPress template files
		Below is a list of some basic theme templates and files recognized by WordPress.

		index.php (classic theme) or index.html (block theme)

		The main template file. It is required in all themes.

		style.css

		The main stylesheet. It is required in all themes and contains the information header for your theme.

		rtl.css

		The right-to-left stylesheet is included automatically if the website language’s text direction is right-to-left.

		front-page.php (classic theme) or front-page.html (block theme)

		The front page template is always used as the site front page if it exists, regardless of what settings on Admin > Settings > Reading.

		home.php (classic theme) or home.html (block theme)

		The home page template is the front page by default. If you do not set WordPress to use a static front page, this template is used to show latest posts.

		singular.php (classic theme) or singular.html (block theme)

		The singular template is used for posts when single.php is not found, or for pages when page.php are not found. If singular.php is not found, index.php is used.

		single.php (classic theme) or single.html (block theme)

		The single post template is used when a visitor requests a single post.

		single-{post-type}.php (classic theme) or single-{post-type}.html (block theme)

		The single post template used when a visitor requests a single post from a custom post type. For example, single-book.php would be used for displaying single posts from a custom post type named book.

		archive-{post-type}.php (classic theme) or archive-{post-type}.html (block theme)

		The archive post type template is used when visitors request a custom post type archive. For example, archive-books.php would be used for displaying an archive of posts from the custom post type named books. The archive template file is used if the archive-{post-type} template is not present.

		page.php (classic theme) or page.html (block theme)

		The page template is used when visitors request individual pages, which are a built-in template.

		page-{slug}.php (classic theme) or page-{slug}.html (block theme)

		The page slug template is used when visitors request a specific page, for example one with the “about” slug (page-about.php).

		category.php (classic theme) or category.html (block theme)

		The category template is used when visitors request posts by category.

		tag.php (classic theme) or tag.html (block theme)

		The tag template is used when visitors request posts by tag.

		taxonomy.php (classic theme) or taxonomy.html (block theme)

		The taxonomy term template is used when a visitor requests a term in a custom taxonomy.

		author.php (classic theme) or author.html (block theme)

		The author page template is used whenever a visitor loads an author page.

		date.php (classic theme) or date.html (block theme)

		The date/time template is used when posts are requested by date or time. For example, the pages generated with these slugs:
		http://example.com/blog/2014/
		http://example.com/blog/2014/05/
		http://example.com/blog/2014/05/26/

		archive.php (classic theme) or archive.html (block theme)

		The archive template is used when visitors request posts by category, author, or date. Note: this template will be overridden if more specific templates are present like category.php, author.php, and date.php.

		search.php (classic theme) or search.html (block theme)

		The search results template is used to display a visitor’s search results.

		attachment.php (classic theme) or attachment.html (block theme)

		The attachment template is used when viewing a single attachment like an image, pdf, or other media file.

		image.php (classic theme) or image.html (block theme)

		The image attachment template is a more specific version of attachment.php and is used when viewing a single image attachment. If not present, WordPress will use attachment.php instead.

		404.php (classic theme) or 404.html (block theme)

		The 404 template is used when WordPress cannot find a post, page, or other content that matches the visitor’s request.

		comments.php

		The comments template in classic themes. In block themes, blocks are used instead.

		Using template files
		Classic themes
		In classic themes, within WordPress templates, you can use Template Tags to display information dynamically, include other template files, or otherwise customize your site.

		For example, in your index.php you can include other files in your final generated page:

		To include the header, use get_header()
		To include the sidebar, use get_sidebar()
		To include the footer, use get_footer()
		To include the search form, use get_search_form()
		To include custom theme files, use get_template_part()
		Here is an example of WordPress template tags to include specific templates into your page:

		```
		<?php get_sidebar(); ?>
		<?php get_template_part( 'featured-content' ); ?>
		<?php get_footer(); ?>
		```

		There’s an entire page on Template Tags that you can dive into to learn all about them.

		Refer to the section Linking Theme Files & Directories for more information on linking component templates.

		Block themes
		In block themes you use blocks instead of template tags. Block markup is the HTML code that WordPress uses to display the block. Template parts are blocks, and you add them to your template files the same way as you add blocks.

		To include a header or footer template part, add the block markup for the template part. The slug is the name of the part. If the file you want to include is called header.html, then the slug is “header”:

		```
		<!-- wp:template-part {"slug":"header"} /-->
		(your page content)
		<!-- wp:template-part {"slug":"footer"} /-->
		```

		To include the search form, use the block markup for the search block:

		```
		<!-- wp:search {"label":"Search","buttonText":"Search"} /-->
		```

		BLOCK_THEME_PROMPT,
	],
	array_merge(
		[
			$html,
		],
		$screenshots,
	),
	[
		'model' => 'gpt-4o-mini',
		'apiEndpoint' => 'https://api.openai.com/v1/chat/completions',
		'apiKey' => getenv('OPENAI_API_KEY'),
		'stream' => false,
	]
);

echo $response;

// Parse the response to extract files
$files = [];
$current_file = null;
$current_content = '';

// Split the response into lines for processing
$lines = explode("\n", $response);
foreach ($lines as $line) {
    // Check for file start marker
    if (preg_match('/<\|CREATE_FILE_START:(.*?)\|>/', $line, $start_match)) {
        // If we were already processing a file, save it before starting a new one
        if ($current_file !== null) {
            $files[$current_file] = $current_content;
            $current_content = '';
        }
        $current_file = $start_match[1];
    } 
    // Check for file end marker
    elseif (preg_match('/<\|CREATE_FILE_END:(.*?)\|>/', $line, $end_match) && $current_file !== null) {
        // Save the current file
        $files[$current_file] = $current_content;
        $current_file = null;
        $current_content = '';
    }
    // If we're inside a file, add the line to the content
    elseif ($current_file !== null) {
        $current_content .= $line . "\n";
    }
}

// Handle case where the last file doesn't have a closing tag
if ($current_file !== null) {
    $files[$current_file] = $current_content;
}

// Create a directory for the theme if it doesn't exist
$theme_dir = WP_CONTENT_DIR . '/themes/cf2025-gen-theme';
if (!file_exists($theme_dir)) {
    mkdir($theme_dir, 0755, true);
}

// Process each file
foreach ($files as $file_path => $file_content) {
	if(str_starts_with($file_path, 'wp-content/themes/')) {
		$file_path = substr($file_path, strlen('wp-content/themes/'));
	}
	if(str_starts_with($file_path, 'cf2025-gen-theme/')) {
		$file_path = substr($file_path, strlen('cf2025-gen-theme/'));
	}
    // Create subdirectories if needed
    $full_path = $theme_dir . '/' . $file_path;
    $dir_path = dirname($full_path);
    if (!file_exists($dir_path)) {
        mkdir($dir_path, 0755, true);
    }
    
    // Write the file
    file_put_contents($full_path, $file_content);
	echo "Created file: " . $file_path . "\n";
}

echo "Theme files created in: " . $theme_dir;
echo "Activating the theme...\n";

// Set current user to admin
wp_set_current_user( get_users(array('role' => 'Administrator') )[0]->ID );

$theme_name = 'cf2025-gen-theme';
switch_theme( $theme_name );

if( wp_get_theme()->get_stylesheet() !== $theme_name ) {
	throw new Exception( 'Theme ' . $theme_name . ' could not be activated.' );				
}

echo "Theme activated: " . $theme_name . "\n";

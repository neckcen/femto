/*
Title: Welcome
Description: This description will go in the meta description tag
*/

Welcome to Femto
================

Congratulations, you have successfully installed
[Femto](https://eiky.net/tinkering/femto/). Femto is an extermely simple yet
easily customisable flat file content manager.


Creating Content
----------------

Femto is a flat file CMS, this means there is no administration backend and
database to deal with. You simply create `.md` files in the "content" folder
and that becomes a page. For example, this file is called `index.md` and is
shown as the main landing page.

If you create a folder within the content folder (e.g. `content/sub`) and put
an `index.md` inside it, you can access that folder at the URL
`http://yousite.com/sub/`. If you want another page within the sub folder,
simply create a text file with the corresponding name (e.g.
`content/sub/page.md`) and you will be able to access it from the URL
`http://yousite.com/sub/page`. Below we've shown some examples of content
locations and their corresponing URL's:

<table class="width-100 table-striped">
	<thead>
		<tr><th>Physical Location</th><th>URL</th></tr>
	</thead>
	<tbody>
		<tr><td>content/index.md</td><td>/</td></tr>
		<tr><td>content/sub.md</td><td>/sub</td></tr>
		<tr><td>content/sub/index.md</td><td>/sub/ (note the trailing slash)</td></tr>
		<tr><td>content/sub/page.md</td><td>/sub/page</td></tr>
		<tr><td>content/a/very/long/url.md</td><td>/a/very/long/url</td></tr>
	</tbody>
</table>

If a file cannot be found, the file `content/404.md` will be shown.

Text File Markup
----------------

Text files are marked up using
[Markdown](http://daringfireball.net/projects/markdown/syntax). They can also
contain regular HTML.

At the top of text files you can place a block comment and specify certain
attributes of the page. For example:

	/*
	Title: Welcome
	Description: This description will go in the meta description tag
	Author: Joe Bloggs
	Date: 2013/01/01
	Robots: noindex,nofollow
	*/

These values will be contained in the `{{ page }}` variable in themes (see below).

There are also certain variables that you can use in your text files:

* <code>&#37;base_url&#37;</code> - The URL to your Femto site

Themes
------

You can create themes for your Femto installation in the "themes" folder. Check
out the default theme for an example of a theme. Femto uses
[Twig](http://twig.sensiolabs.org/documentation) for it's templating engine. You
can select your theme by setting the `$config['theme']` variable in index.php.

All themes must include an `index.html` file to define the HTML structure of the
theme. Below are the Twig variables that are available to use in your theme:

* `{{ config }}` - Contains the configuration (e.g. `{{config.theme}}` outputs _default_).
* `{{ base_url }}` - The URL to your Femto site
* `{{ theme_dir }}` - The path to the theme directory
* `{{ theme_url }}` - The URL to the theme directory
* `{{ site_title }}` - Shortcut to the site title (defined in config.php)
* `{{ current_page }}` - Contains the values from the current page
	* `{{ current_page.title }}`
	* `{{ current_page.description }}`
	* `{{ current_page.author }}`
	* `{{ current_page.date }}`
	* `{{ current_page.date_formatted }}`
    * `{{ current_page.robots }}`
    * `{{ current_page.content }}`
    * `{{ current_page.excerpt }}`

Additionally two functions are also available:

* `{{ page(url) }}` - Return the page corresponding to _url_.
* `{{ directory(url, sort, order) }}` - Return all pages in the directory
corresponding to _url_ sorted by _sort_ and ordered by _order_. These pages will
not have the content field.

Example use:

<pre>&lt;nav&gt;&lt;ul&gt;
	{% for page in directory('/') %}
	&lt;li&gt;&lt;a href=&quot;{{ page.url }}&quot;&gt;{{ page.title }}&lt;/a&gt;&lt;/li&gt;
	{% endfor %}
&lt;/ul&gt;&lt;/nav&gt;</pre>

### Plugins

See [http://pico.dev7studios.com/plugins](http://pico.dev7studios.com/plugins)

### Config

You can override the default Pico settings (and add your own custom settings) by editing config.php in the root Pico directory. The config.php file
lists all of the settings and their defaults. To override a setting, simply uncomment it in config.php and set your custom value.

### Documentation

For more help have a look at the Pico documentation at [http://pico.dev7studios.com/docs](http://pico.dev7studios.com/docs)

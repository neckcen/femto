Femto
=====

A minimalist and efficient content management system (CMS) in PHP.

Requirements
------------

* PHP 5.4+
* mod_rewrite or equivalent

Installation
------------

1. [Get the latest version.](https://github.com/neckcen/femto/releases/latest)
2. Unzip it.
3. Upload it to your server.
4. Make sure the `cache` directory is writeable.
5. (Optional) customise the settings by editing `index.php` in the root Femto
directory.
6. (Non Apache users) Set up URL rewriting to point to `index.php`.

Creating Content
----------------
Femto is a flat file CMS, this means there is no administration backend or
database to deal with. You simply create `.md` files in the `content` folder
and that becomes a page.

If you create a folder within the content folder (e.g. `content/sub`) and put
an `index.md` inside it, you can access that folder at the URL
`http://example.com/sub/`. If you want another page within the sub folder,
simply create a text file with the corresponding name (e.g.
`content/sub/page.md`) and you will be able to access it from the URL
`http://example.com/sub/page`. Below are some examples of content
locations and their corresponding URL's:

Physical Location           | URL
--------------------------- | --------------------------------
content/index.md            | /
content/sub.md              | /sub
content/sub/index.md        | /sub/ (note the trailing slash)
content/sub/page.md         | /sub/page
content/a/very/long/url.md  | /a/very/long/url

If a file cannot be found, the file `content/404.md` will be shown.

### Text File Markup
Text files are marked up using
[Markdown](http://daringfireball.net/projects/markdown/syntax). They can also
contain regular HTML.

At the top of text files you can place a block comment and specify certain
attributes of the page. For example:

    /*
    Title: Welcome
    Description: This description will go in the meta description tag
    Robots: noindex,nofollow
    Flags: no-markdown,no-theme
    */

These values will be contained in the `$page` variable in themes (see below).

The `Flags` attribute let you customise how the page behaves. Possible flags are
(more can be added by plugins):

* `no-markdown` content will not be processed as markdown
* `no-theme` the page content will not be inserted in your website's theme
* `no-directory` the page will not show in directory listings (see theme below)
* `no-cache` disable page content cache (see cache below)

There are also certain variables that you can use in your text files:

* `femto://` - The URL to your Femto site
* `femto://directory` - The URL to the directory containing the page
* `femto://self` - The URL to the current page

For example you could link another page in the same directory like so:

    [another page](femto://directory/another_page)

Themes
------
You can create themes for your Femto installation in the "themes" folder. Check
out the default theme for an example. You can select your theme by setting the 
`$config['theme']` variable in `index.php`.

All themes must include a default template named `index.html.php`. Pages can 
specify a different template by setting the `template` header:

    /*
    Title: Welcome
    Template: mytemplate
    */

Below are the variables that are available to use in your theme:

* `$config` - Contains the configuration (e.g. `$config['theme']` outputs
_default_)
* `$base_url` - The URL to your Femto site (no trailing slash)
* `$theme_url` - The URL to the theme directory (no trailing slash)
* `$site_title` - Your site's title (defined in `index.php`)
* `$page` - Contains the values from the current page
    * `$page['url']`
    * `$page['title']`
    * `$page['description']`
    * `$page['robots']`
    * `$page['content']`
    * `$page['directory']`

You can also access some functions:

* `page($url)` - Return the page object corresponding to `$url` or null if
the page doesn't exist.
* `directory($url)` - Return the directory object corresponding to `$url` or
null if the directory doesn't exist. Use the sort method to list the directory.
Pages are returned by this function without their content.

Example use:

    <nav><ul>
        <?php foreach(directory('/')->sort('alpha', 'desc') as $p): ?>
            <li><a href="<?=$p['url']?>"><?=$p['title']?></a></li>
        <?php endforeach; ?>
    </ul></nav>

When using the short echo tags `<?=` the content is automatically escaped. Use
`<?php echo` or `<?==` to display unescaped content.

Cache
-----
Femto features a powerful cache system, each page is only rendered once unless
you modify it. There is also a cache in place for directory information.

You can disable the cache for a specific page by adding the no-cache flag.

    /*
    Title: Welcome
    Flags: no-cache
    */

Plugins
-------

[See the dedicated repository.](https://github.com/neckcen/femto-plugin)

Credits
-------

Femto is developed by Sylvain Didelot.

It is originally a fork of [Pico](http://pico.dev7studios.com/) by Gilbert
Pellegrom and make use of the following libraries:

* [php-markdown](https://github.com/michelf/php-markdown) by Michel Fortin.
* [twig](http://twig.sensiolabs.org/) by Fabien Potencier.

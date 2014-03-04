Femto
=====

A minimalist and efficient content management system (CMS) in PHP.

Requirements
------------

* PHP 5.3+
* mod_rewrite or equivalent

Installation
------------

1. [Get the latest version.](https://github.com/neckcen/femto/releases/latest)
2. Unzip it.
3. Upload it to your server.
4. Make sure the `cache` directory is writable.
5. (Optional) customise the settings by editing `index.php` in the root Femto
directory.
6. (Non Apache users) Set up URL rewriting to point to `index.php`.

Creating Content
----------------
Femto is a flat file CMS, this means there is no administration backend and
database to deal with. You simply create `.md` files in the `content` folder
and that becomes a page.

If you create a folder within the content folder (e.g. `content/sub`) and put
an `index.md` inside it, you can access that folder at the URL
`http://example.com/sub/`. If you want another page within the sub folder,
simply create a text file with the corresponding name (e.g.
`content/sub/page.md`) and you will be able to access it from the URL
`http://example.com/sub/page`. Below are some examples of content
locations and their corresponing URL's:

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
    */

These values will be contained in the `{{ current_page }}` variable in themes
(see below).

There are also certain variables that you can use in your text files:

* `%base_url%` - The URL to your Femto site

Themes
------
You can create themes for your Femto installation in the "themes" folder. Check
out the default theme for an example. Femto uses
[Twig](http://twig.sensiolabs.org/documentation) for it's templating engine. You
can select your theme by setting the `$config['theme']` variable in `index.php`.

All themes must include an `index.html` file to define the HTML structure of the
theme. Pages can specify a different template by setting the `template` header:

    /*
    Title: Welcome
    Template: mytemplate
    */

Below are the Twig variables that are available to use in your theme:

* `{{ config }}` - Contains the configuration (e.g. `{{config.theme}}` outputs
_default_)
* `{{ base_url }}` - The URL to your Femto site
* `{{ theme_dir }}` - The path to the theme directory
* `{{ theme_url }}` - The URL to the theme directory
* `{{ site_title }}` - Your site's title (defined in `index.php`)
* `{{ current_page }}` - Contains the values from the current page
    * `{{ current_page.title }}`
    * `{{ current_page.description }}`
    * `{{ current_page.robots }}`
    * `{{ current_page.content }}`

Additionally two functions are also available:

* `{{ page(url) }}` - Return the page corresponding to `url` or nothing if
the page doesn't exist.
* `{{ directory(url, sort, order) }}` - Return all pages in the directory
corresponding to `url` sorted by `sort` and ordered by `order`.<br/>
`Sort` defaults to _alpha_, no other value possible by default but
plugins can add more.<br/>
`Order` defaults to _desc_, other value possible: _asc_.<br/>
Pages are returned without their content.

Example use:

    <nav><ul>
        {% for page in directory('/') %}
        <li><a href="{{ page.url }}">{{ page.title }}</a></li>
        {% endfor %}
    </ul></nav>

Cache
-----
Femto features a powerful cache system, each page is only rendered once unless
you modify it. There is also a cache in place for directory information and for
the themes themselves.

You can disable the cache in three different way. First globally in `index.php`
by setting `cache_enabled` to false. This is not recommended due to the negative
impact on performances for your entire website.

Instead you can disable the cache for a specific page by adding the no-cache
header.

    /*
    Title: Welcome
    No-Cache: page,directory,template
    */

Finally you can disable the cache for a single request by adding `?purge=1` at
the end of the url (e.g. `http://example.com/sub/page?purge=1`).

Plugins
-------

[See the dedicated repository.](https://github.com/neckcen/femto-plugin)

Credits
-------

Femto is developped by Sylvain Didelot.

It is originally a fork of [Pico](http://pico.dev7studios.com/) by Gilbert
Pellegrom and make use of the following libraries:

* [php-markdown](https://github.com/michelf/php-markdown) by Michel Fortin
(altered version to fix some bugs).
* [twig](http://twig.sensiolabs.org/) by Fabien Potencier.

/*
Title: Welcome
Description: This description will go in the meta description tag
*/

Welcome to Femto
================

Congratulations, you have successfully installed Femto. Femto is an
[open source](https://github.com/neckcen/femto) minimalist and efficient flat
file content management system.

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
    Robots: noindex,nofollow
    */

These values will be contained in the `{{ current_page }}` variable in themes
(see below).

There are also certain variables that you can use in your text files:

* <code>&#37;base_url&#37;</code> - The URL to your Femto site

More
----
The above is enough to get a basic website up, for more information on plugins,
themes and such check out the
[project page](https://github.com/neckcen/femto).

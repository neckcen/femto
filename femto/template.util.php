<?php

/**
 * Useful functions available inside templates.
 *
 */
namespace femto\template;

/**
 * Return the page associated with the url.
 *
 * @see \femto\Page::resolve()
 *
 * @param string $url The url to resolve
 * @return Page Femto page, null if not found
 */
function page($url) {
    return \femto\Page::resolve($url);
}

/**
 * Return the directory associated with the url.
 *
 * @see \femto\Directory::resolve()
 *
 * @param string $url The url to list
 * @return Directory List of Femto pages with content removed
 */
function directory($url) {
    return \femto\Directory::resolve($url);
}

/**
 * Escape a string for use within HTML.
 *
 * @see \femto\escape()
 *
 * @param string $string String to escape
 * @return string Escaped string
 */
function escape() {
    $string = implode('', func_get_args());
    echo \femto\escape($string);
}

<?php $config = [];

/**
 * Femto is a simple content manager, designed to be fast and easily
 * customisable. Femto is a complete rewrite of Pico 0.8 by Gilbert Pellegrom.
 *
 * Below is the configuration of your website, uncomment (remove #) and change
 * values if needed.
 *
 * @author Sylvain Didelot
 * @license http://opensource.org/licenses/MIT
 * @version 5.0
 */


// Your site's title. Default: Femto.
#$config['site_title'] = 'Femto';

// Your site's base url. If this file is located at
// http://example.com/foo/index.php then base url would be /foo/.
// base url must begin with a single /.
// Default: Femto attempts to guess it.
#$config['base_url'] = '';

// Directory in which pages are located. Default: content/.
#$config['content_dir'] = 'content/';

// Whether cache debug mode is enabled. Default: false.
// In debug mode cache files are created but immediately expire.
#$config['cache_debug'] = false;

// Directory in which the cache is saved. Default cache/.
#$config['cache_dir'] = 'cache/';

// Your site's theme. Default: default.
#$config['theme'] = 'default';

// Directory in which themes are found. Default themes/.
#$config['theme_dir'] = 'themes/';

// Base url to the theme directory. Default $config['base_url']
#$config['theme_base_url'] = '';

// A comma separated list of enabled plugins. E.g. gallery,php. Default: empty.
#$config['plugin_enabled'] = '';

// Directory in which plugins are found. Default: directory in which femto.php
// is plus plugins/.
#$config['plugin_dir'] = 'plugins/';

// Do not touch anything below.
require 'femto/femto.php';
\femto\run($config);

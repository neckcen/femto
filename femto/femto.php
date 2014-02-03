<?php
/**
 * Femto is a simple content manager, designed to be fast and easily
 * customisable. Femto is a complete rewrite of Pico 0.8 by Gilbert Pellegrom.
 *
 * @author Sylvain Didelot
 * @license http://opensource.org/licenses/MIT
 * @version 0.2
 */

namespace eiky\femto;

require __DIR__.'/vendor/michelf/php-markdown/Michelf/MarkdownExtra.inc.php';
require __DIR__.'/vendor/twig/twig/lib/Twig/Autoloader.php';

/**
 * A dumb class to avoid poluting the global namespace with the variables that
 * should be available everywhere in Femto.
 */
class local {
    public static $config = array();
    public static $plugin = array();
    public static $current_page = null;
    public static $current_cache = null;

    // prevents instances
    private function __construct() {}
}


/**
 * The core logic of Femto.
 * Load the plugins, route the url and render the corresponding page.
 *
 * @param array $site_config Optional configuration for this website.
 */
function run($site_config=array()) {
    $config =& local::$config;
    $plugin =& local::$plugin;
    $current_page =& local::$current_page;

    // config
    $config = array(
        'site_title' => 'Femto',
        'base_url' => null,
        'date_format' => 'jS M Y',
        'excerpt_length' => 50,
        'content_dir' => 'content/',
        'cache_enabled' => true,
        'cache_dir' => 'cache/',
        'theme' => 'default',
        'theme_dir' => 'themes/',
        'twig_autoescape' => true,
        'twig_debug' => false,
        'plugin_enabled' => '',
        'plugin_dir' => __DIR__.'/plugins/',
    );
    $config = array_merge($config, $site_config);
    if($config['base_url'] === null) {
        if(isset($_SERVER['PHP_SELF'])) {
            $config['base_url'] = dirname($_SERVER['PHP_SELF']);
        } else {
            $config['base_url'] = '';
        }
    } else {
        $config['base_url'] = rtrim($config['base_url'], '/');
    }
    $config['content_dir'] = rtrim($config['content_dir'], '/').'/';
    $config['cache_dir'] = rtrim($config['cache_dir'], '/').'/';
    $config['theme_dir'] = rtrim($config['theme_dir'], '/').'/';
    $config['plugin_dir'] = rtrim($config['plugin_dir'], '/').'/';

    // load plugins
    if(empty($config['plugin_enabled'])) {
        $config['plugin_enabled'] = array();
    } else {
        $config['plugin_enabled'] = explode(',', $config['plugin_enabled']);
        foreach($config['plugin_enabled'] as $p) {
            include($config['plugin_dir'].$p.'/plugin.php');
            $plugin[$p] = new $p($config);
        }
    }

    // get url and normalise it if needed
    $url = '';
    if(isset($_SERVER['REDIRECT_URL'])) {
        $url = $_SERVER['REDIRECT_URL'];
    } else {
        $qs = strpos($_SERVER['REQUEST_URI'], '?');
        if($qs === false) {
            $url = $_SERVER['REQUEST_URI'];
        } else {
            $url = substr($_SERVER['REQUEST_URI'], 0, $qs);
        }
    }
    if(substr($url, -6) == '/index') {
        header('Location: '.substr($url, 0, -6));
        exit();
    }
    $url = substr($url, strlen($config['base_url']));
    hook('request_url', array(&$url));

    // plugin url
    $match = array();
    if(preg_match('`^/plugin/([^/]+)/(.*)$`', $url, $match)) {
        list(,$p, $url) = $match;
        if(isset($plugin[$p]) && is_callable(array($plugin[$p], 'url'))) {
            $current_page = call_user_func(array($plugin[$p], 'url'), $url);
            if($current_page == null) {
                return;
            }
        }
    // normal page
    } else {
        $current_page = page($url);
    }
    // not found
    if($current_page == null) {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        $current_page = page('/404');
        hook('page_not_found', array(&$current_page));
    }

    // render
    hook('before_twig_register');
    \Twig_Autoloader::register();
    $loader = new \Twig_Loader_Filesystem($config['theme_dir'].$config['theme']);
    $cache = false;
    if($config['cache_enabled'] && in_array('template', $current_page['cache'])) {
        $cache = $config['cache_dir'].'twig';
    }
    $settings = array(
        'cache' => $cache,
        'debug' => $config['twig.debug'],
        'autoescape' => $config['twig.autoescape'],
    );
    $twig = new \Twig_Environment($loader, $settings);
    $twig->addFunction(new \Twig_SimpleFunction('directory', '\eiky\femto\directory'));
    $twig->addFunction(new \Twig_SimpleFunction('page', '\eiky\femto\page'));
    if($config['twig.debug']) {
        $twig->addExtension(new \Twig_Extension_Debug());
    }
    $twig_vars = array(
        'config' => $config,
        'base_url' => $config['base_url'],
        'theme_dir' => $config['theme_dir'].$config['theme'],
        'theme_url' => $config['base_url'].$config['theme_dir'].$config['theme'],
        'site_title' => $config['site_title'],
        'current_page' => $current_page,
    );
    hook('before_render', array(&$twig_vars, &$twig, &$current_page['template']));
    $output = $twig->render($current_page['template'] .'.html', $twig_vars);
    hook('after_render', array(&$output));
    echo $output;
}


/**
 * Translate an url to its corresponding file and pass it to page_from_file. If
 * the url doesn't start with a slash it will be considered relative to the
 * current page.
 *
 * @see page_from_file()
 *
 * @param string $url The url to resolve.
 * @return array Femto page, null if not found.
 */
function page($url) {
    $file = substr($url, -1) == '/' ? $url.'index.md' : $url.'.md';
    $file = $file[0] == '/' ? local::$config['content_dir'].substr($file, 1) :
      dirname(local::$current_page['file']).'/'.$file;
    return page_from_file($file);
}


/**
 * Transform a file in a Femto page.
 *
 * The Femto page returned is a php array with the following keys:
 * file - the file containing the page
 * url - the url corresponding to this page
 * title - the page title (if set in header)
 * description - the page description (if set in header)
 * author - the page author (if set in header)
 * date - the date the page was last edited (if set in header)
 * date_timestamp - timestamp of the date if set
 * date_formated - formated version of the date if set
 * robots - robot meta tag value (if set in header)
 * template - index by default
 * cache - page,directory,template by default
 * content - the content of the page
 * excerpt - an excerpt of the content (length depends on configuration
 * Additional keys can be created by plugins.
 *
 * @param string $file The file to read.
 * @return array Femto page, null if not found.
 */
function page_from_file($file) {
    if(($time = @filemtime($file)) === false) {
        return null;
    }
    if(($page = cache_retrieve($file, $time)) === null) {
        $page = array();
        $page['file'] = $file;
        $page['url'] = '/'.substr($file, strlen(local::$config['content_dir']));
        $page['url'] = substr($page['url'], -9) == '/index.md' ?
          substr($page['url'], 0, -8) : substr($page['url'], 0, -3);

        hook('before_load_content', array(&$file));
        $page['content'] = file_get_contents($file);
        hook('after_load_content', array(&$page['content']));

        $meta = array(
            'title' => null,
            'description' => null,
            'author' => null,
            'date' => null,
            'robots' => null,
            'template' => 'index',
            'cache' => 'page,directory,template',
        );
        hook('before_read_file_meta', array(&$meta));
        $page = array_merge($page, $meta);
        if(substr($page['content'], 0, 2) == '/*') {
            $meta_block_end = strpos($page['content'], '*/')+2;
            $meta_block = substr($page['content'], 0, $meta_block_end);
            foreach ($meta as $key => $default) {
                $match = array();
                $k = preg_quote($key, '`');
                if(preg_match('`\*?\s*'.$k.'\s*:([^\r\n]*)`i', $meta_block, $match)) {
                    $page[$key] = trim($match[1]);
                }
            }
            $page['content'] = substr($page['content'], $meta_block_end);
        }
        if(!empty($page['date'])) {
            $page['timestamp'] = strtotime($page['date']);
            $page['date_formatted'] = date(local::$config['date_format'], $page['timestamp']);
        } else {
            $page['timestamp'] = null;
            $page['date_formatted'] = null;
        }
        $page['cache'] = explode(',', $page['cache']);
        hook('after_read_file_meta', array(&$page));

        $page['excerpt'] = explode(' ', strip_tags($page['content']), 51);
        if(count($page['excerpt']) > 50) {
            $page['excerpt'][51] = '';
            $page['excerpt'] = trim(implode(' ', $page['excerpt'])).'…';
        } else {
            $page['excerpt'] = $page['content'];
        }

        hook('before_parse_content', array(&$page['content']));
        $page['content'] = str_replace('%base_url%', $config['base_url'], $page['content']);
        $page['content'] = \Michelf\MarkdownExtra::defaultTransform($page['content']);
        hook('after_parse_content', array(&$page['content']));

        if(in_array('page', $page['cache'])) {
            cache_store($page);
        }
    }

    return $page;
}


/**
 * List the content of the directory corresponding to the url. If a directory
 * is found its index.md page will be used.
 *
 * @param string $url The url to list.
 * @param string $sort Sorting criteria.
 * @param string $sort_order Sorting order.
 * @return array List of Femto pages with content removed.
 */
function directory($url, $sort='alpha', $sort_order='asc') {
    $file = substr($url, -1) == '/' ? $url : dirname($url).'/';
    $file = $file[0] == '/' ? local::$config['content_dir'].substr($file, 1) :
      dirname($current_page['file']).'/'.$file;

    if(($time = @filemtime($file.'.')) === false) {
        return array();
    }
    if(($dir = cache_retrieve($file, $time)) === null) {
        $dir = array();
        $cache = true;
        foreach(scandir($file) as $f) {
            if($f == '.' || $f == '..') {
                continue;
            }
            if(is_dir($file.$f)) {
                $f .= '/index.md';
            }
            if(substr($f, -3) == '.md') {
                $page = page_from_file($file.$f);
                if($page !== null) {
                    unset($page['content']);
                    if(!in_array('directory', $page['cache'])) {
                        $cache = false;
                    }
                    $dir[] = $page;
                }
            }
        }
        if($cache) {
            cache_store($dir);
        }
    }
    //sorting
    if($sort == 'alpha') {
        usort($dir, '\eiky\femto\directory_sort_alpha');
    } else if($sort == 'date') {
        usort($dir, '\eiky\femto\directory_sort_date');
    }
    hook('directory_sort', array(&$sort, &$dir));
    if($sort_order != 'asc') {
        $dir = array_reverse($dir);
    }
    return $dir;
}

/**
 * Used to sort director by title.
 *
 * @see usort()
 *
 * @param array $a Page a.
 * @param array $b Page b.
 * @return int 0 if equal, 1 if a > b, -1 if b > a.
 */
function directory_sort_alpha($a, $b) {
    return strcmp($a['title'], $b['title']);
}

/**
 * Used to sort director by date.
 *
 * @see usort()
 *
 * @param array $a Page a.
 * @param array $b Page b.
 * @return int 0 if equal, 1 if a > b, -1 if b > a.
 */
function directory_sort_date($a, $b) {
    return $a['timestamp'] == $b['timestamp'] ? 0 :
        ($a['timestamp'] < $b['timestamp'] ? -1 : 1);
}


/**
 * Check if key is in cache and fresher than given time.
 *
 * @param mixed $key Cache key.
 * @param int $time Timestamp for when the reference was last modified.
 * @return mixed Key value, null if expired or not found.
 */
function cache_retrieve($key, $time) {
    if(!local::$config['cache_enabled']) {
        return null;
    }
    $hash = md5($key);
    local::$current_cache = sprintf(
      '%sfemto/%s/%s/%s.php',
      local::$config['cache_dir'],
      substr($hash, 0, 2),
      substr($hash, 2, 2),
      $hash
    );

    if(@filemtime($cache) > $time) {
        include(local::$current_cache);
        return $value;
    }
    return null;
}


/**
 * Store a value in the cache for the last key checked.
 *
 * @param mixed $value Cache value.
 */
function cache_store($value) {
    if(!local::$config['cache_enabled']) {
        return;
    }
    @mkdir(dirname(local::$current_cache), 0777, true);
    file_put_contents(local::$current_cache,
      sprintf('<?php $value = %s;', var_export($value, true)));
}

/**
 * Run a hook on all loaded plugins.
 *
 * @param string $hook Hook name.
 * @param array $args Arguments for the hook.
 */
function hook($hook, $args=array()) {;
    foreach(local::$plugin as $p){
        if(is_callable(array($p, $hook))){
            call_user_func_array(array($p, $hook), $args);
        }
    }
}
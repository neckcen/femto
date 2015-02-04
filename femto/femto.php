<?php
/**
 * Femto is a simple content manager, designed to be fast and easily
 * customisable. Femto is a complete rewrite of Pico 0.8 by Gilbert Pellegrom.
 *
 * @author Sylvain Didelot
 * @license http://opensource.org/licenses/MIT
 * @version 3.1
 */

namespace femto;

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
    $template_dir = array();

    // config
    $config = array(
        'site_title' => 'Femto',
        'base_url' => null,
        'content_dir' => 'content/',
        'cache_enabled' => true,
        'cache_dir' => 'cache/',
        'theme' => 'default',
        'theme_dir' => 'themes/',
        'twig_debug' => false,
        'plugin_enabled' => '',
        'plugin_dir' => __DIR__.'/plugins/',
    );
    $config = array_merge($config, $site_config);
    if($config['base_url'] === null && isset($_SERVER['PHP_SELF'])) {
        $config['base_url'] = dirname($_SERVER['PHP_SELF']);
    }
    $config['base_url'] = rtrim($config['base_url'], '/');
    $config['content_dir'] = rtrim($config['content_dir'], '/').'/';
    $config['cache_dir'] = rtrim($config['cache_dir'], '/').'/';
    $config['theme_dir'] = rtrim($config['theme_dir'], '/').'/';
    $config['plugin_dir'] = rtrim($config['plugin_dir'], '/').'/';

    // load plugins
    if(empty($config['plugin_enabled'])) {
        $config['plugin_enabled'] = array();
    } else {
        $config['plugin_enabled'] = explode(',', $config['plugin_enabled']);
        foreach($config['plugin_enabled'] as $P) {
            $p = strtolower($P);
            include($config['plugin_dir'].$p.'.php');
            $P = __NAMESPACE__.'\plugin\\'.$P;
            $plugin[$p] = new $P($config);
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
    $normal_url = $url;
    $normal_url = str_replace(array('./', '../'), '', $url);
    if(substr($url, -6) == '/index') {
        $normal_url = substr($url, 0, -5);
    }
    if($normal_url != $url) {
        header('Location: '.$normal_url);
        exit();
    }
    if($config['base_url'] != '') {
        if(strpos($url, $config['base_url']) === 0) {
            $url = substr($url, strlen($config['base_url']));
        } else {
            $url = '/';
        }
    }
    hook('request_url', array(&$url));

    // plugin url
    $match = array();
    if(preg_match('`^/plugin/([^/]+)/(.*)$`', $url, $match)) {
        list(,$p, $url) = $match;
        if(isset($plugin[$p]) && is_callable(array($plugin[$p], 'url'))) {
            $current_page = call_user_func(array($plugin[$p], 'url'), $url);
            $template_dir[] = $config['plugin_dir'].$p;
        }
    // normal page
    } else {
        $current_page = page($url);
    }
    // not found
    if($current_page == null) {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        $current_page = page('/404');
        hook('request_not_found', array(&$current_page));
    }
    hook('request_complete', array(&$current_page));

    // render
    \Twig_Autoloader::register();
    array_unshift($template_dir, $config['theme_dir'].$config['theme']);
    $loader = new \Twig_Loader_Filesystem($template_dir);
    $cache = false;
    if($config['cache_enabled'] && !in_array('template', $current_page['no-cache'])) {
        $cache = $config['cache_dir'].'twig';
    }
    $settings = array(
        'cache' => $cache,
        'debug' => $config['twig_debug'],
        'autoescape' => false,
    );
    $twig = new \Twig_Environment($loader, $settings);
    if(isset($_GET['purge'])) {
        $twig->clearCacheFiles();
    }
    $twig->addFunction(new \Twig_SimpleFunction('directory', __NAMESPACE__.'\directory'));
    $twig->addFunction(new \Twig_SimpleFunction('page', __NAMESPACE__ .'\page'));
    if($config['twig_debug']) {
        $twig->addExtension(new \Twig_Extension_Debug());
    }
    $twig_vars = array(
        'config' => $config,
        'base_url' => $config['base_url'],
        'theme_url' => $config['base_url'].'/'.$config['theme_dir'].$config['theme'],
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
 * robots - robot meta tag value (if set in header)
 * template - index by default
 * cache - page,directory,template by default
 * content - the content of the page
 * Additional keys can be created by plugins.
 *
 * @param string $file The file to read.
 * @return array Femto page, null if not found.
 */
function page_from_file($file) {
    $cache = Cache::factory($file);
    if(!$cache) {
        return null;
    }
    if(($page = $cache->retrieve()) == null) {
        $config =& local::$config;
        $page = array();
        $page['file'] = $file;
        $page['url'] = substr($file, strlen($config['content_dir'])-1);
        if(substr($page['url'], -9) == '/index.md') {
            $page['url'] = substr($page['url'], 0, -8);
        } else {
            $page['url'] = substr($page['url'], 0, -3);
        }

        $page['content'] = file_get_contents($file);

        $header = array(
            'title' => null,
            'description' => null,
            'robots' => null,
            'template' => 'index',
            'no-cache' => '',
        );
        hook('page_before_read_header', array(&$header));
        $page = array_merge($page, $header);
        if(substr($page['content'], 0, 2) == '/*') {
            $header_block_end = strpos($page['content'], '*/')+2;
            $header_block = substr($page['content'], 0, $header_block_end);
            foreach ($header as $key => $default) {
                $match = array();
                $re = '`\*?\s*'.preg_quote($key, '`').'\s*:([^\r\n]*)`i';
                if(preg_match($re, $header_block, $match)) {
                    $page[$key] = trim($match[1]);
                }
            }
            $page['content'] = substr($page['content'], $header_block_end);
        }
        $page['no-cache'] = explode(',', $page['no-cache']);
        $page['title_raw'] = $page['title'];
        if($page['title'] !== null) {
            $page['title'] = htmlspecialchars($page['title'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
        }
        $page['description_raw'] = $page['description'];
        if($page['description'] !== null) {
            $page['description'] = htmlspecialchars($page['description'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
        }

        hook('page_before_parse_content', array(&$page));
        $page['content'] = str_replace('%base_url%', $config['base_url'], $page['content']);
        $page['content'] = str_replace('%self_url%', $page['url'], $page['content']);
        $page['content'] = \Michelf\MarkdownExtra::defaultTransform($page['content']);
        hook('page_complete', array(&$page));

        if(!in_array('page', $page['no-cache'])) {
            $cache->store($page);
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
 * @param string $order Sorting order.
 * @return array List of Femto pages with content removed.
 */
function directory($url, $sort='alpha', $order='asc') {
    $file = substr($url, -1) == '/' ? $url : dirname($url).'/';
    $file = $file[0] == '/' ? local::$config['content_dir'].substr($file, 1) :
      dirname($current_page['file']).'/'.$file;

    $cache = Cache::factory($file.'.');
    if(!$cache) {
        return array();
    }
    if(($dir = $cache->retrieve()) == null) {
        $dir = array();
        $cache_allowed = true;
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
                    if(in_array('directory', $page['no-cache'])) {
                        $cache_allowed = false;
                    }
                    $dir[] = $page;
                }
            }
        }
        hook('directory_complete', array(&$dir));
        if($cache_allowed) {
            $cache->store($dir);
        }
    }
    //sorting
    if($sort == 'alpha') {
        usort($dir, __NAMESPACE__.'\\directory_sort_alpha');
    }
    hook('directory_sort', array(&$dir, &$sort));
    if($order != 'asc') {
        $dir = array_reverse($dir);
    }
    return $dir;
}

/**
 * Used to sort directory by title.
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


/**
 * Simple cache system storing information related to one file.
 *
 */
class Cache {
    protected $file;
    protected $modified;
    protected $cache_file = null;

    /**
     * Construct a cache object associated with file.
     *
     * @param string $file File associated to this cache instance.
     * @param int $modified Time when this file was last modified.
     */
    protected function __construct($file, $modified) {
        $this->file = $file;
        $this->modified = $modified;
    }

    /**
     * Compute the cache file when needed.
     *
     * @return string Cache file corresponding to the original file.
     */
    protected function file() {
        if($this->cache_file === null) {
            $hash = md5($this->file);
            $this->cache_file = sprintf(
              '%sfemto/%s/%s/%s.php',
              local::$config['cache_dir'],
              substr($hash, 0, 2),
              substr($hash, 2, 2),
              $hash
            );
        }
        return $this->cache_file;
    }

    /**
     * Check if there is data in cache.
     *
     * @return mixed Cached data, null if expired or not found.
     */
    public function retrieve() {
        if(!local::$config['cache_enabled']) {
            return null;
        }

        if(@filemtime($this->file()) > $this->modified && !isset($_GET['purge'])) {
            include($this->file());
            return $value;
        }
        return null;
    }

    /**
     * Store a value in the cache.
     *
     * @param mixed $value Value to cache.
     */
    public function store($value) {
        if(!local::$config['cache_enabled']) {
            return;
        }
        @mkdir(dirname($this->file()), 0777, true);
        file_put_contents($this->file(),
          sprintf('<?php $value = %s;', var_export($value, true)));
    }

    /**
     * Create a cache object for the given file.
     *
     * @param string $file A file to associate with this cache.
     * @return Cache object or false if the file doesn't exist.
     */
    public static function factory($file) {
        $time = @filemtime($file);
        if(!$time) {
            return false;
        }
        return new self($file, $time);
    }
}

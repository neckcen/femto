<?php
/**
 * Femto is a simple content manager, designed to be fast and easily
 * customisable. Femto is a complete rewrite of Pico 0.8 by Gilbert Pellegrom.
 *
 * @author Sylvain Didelot
 * @license http://opensource.org/licenses/MIT
 * @version 4.0
 */

namespace femto;

require __DIR__.'/vendor/michelf/php-markdown/Michelf/MarkdownExtra.inc.php';
require __DIR__.'/vendor/twig/twig/lib/Twig/Autoloader.php';

/**
 * A dumb class to avoid poluting the global namespace with the variables that
 * should be available everywhere in Femto.
 */
class local {
    public static $config = [];
    public static $plugin = [];
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
function run($site_config=[]) {
    $config =& local::$config;
    $plugin =& local::$plugin;
    $current_page =& local::$current_page;
    $template_dir = [];

    // config
    $config = [
        'site_title' => 'Femto',
        'base_url' => null,
        'content_dir' => 'content/',
        'cache_enabled' => true,
        'cache_dir' => 'cache/',
        'theme' => 'default',
        'theme_dir' => 'themes/',
        'debug' => false,
        'plugin_enabled' => '',
        'plugin_dir' => __DIR__.'/plugins/',
    ];
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
        $config['plugin_enabled'] = [];
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
    $normal_url = str_replace(['./', '../'], '', $url);
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
    hook('request_url', [&$url]);

    // plugin url
    $match = [];
    if(preg_match('`^/plugin/([^/]+)/(.*)$`', $url, $match)) {
        list(,$p, $url) = $match;
        if(isset($plugin[$p]) && is_callable([$plugin[$p], 'url'])) {
            $current_page = call_user_func([$plugin[$p], 'url'], $url);
            $template_dir[] = $config['plugin_dir'].$p;
        }
    // normal page
    } else {
        $current_page = page($url);
    }
    // not found, try plugin hook
    if($current_page == null) {
        hook('request_not_found', [&$url, &$current_page]);
    }
    // not found
    if($current_page == null) {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        $current_page = page('/404');
    }
    hook('request_complete', [&$current_page]);

    // render
    if(in_array('no-theme', $current_page['flags'])) {
        hook('render_after', [&$current_page['content']]);
        echo $current_page['content'];
        exit();
    }

    \Twig_Autoloader::register();
    array_unshift($template_dir, $config['theme_dir'].$config['theme']);
    $loader = new \Twig_Loader_Filesystem($template_dir);
    $cache = $config['cache_enabled'] ? $config['cache_dir'].'twig' : false;
    $settings = [
        'cache' => $cache,
        'debug' => $config['debug'],
        'autoescape' => false,
    ];
    $twig = new \Twig_Environment($loader, $settings);
    if($config['debug'] && isset($_GET['purge'])) {
        $twig->clearCacheFiles();
    }
    $twig->addFunction(new \Twig_SimpleFunction('directory', __NAMESPACE__.'\directory'));
    $twig->addFunction(new \Twig_SimpleFunction('page', __NAMESPACE__ .'\page'));
    if($config['debug']) {
        $twig->addExtension(new \Twig_Extension_Debug());
    }
    $twig_vars = [
        'config' => $config,
        'base_url' => $config['base_url'],
        'theme_url' => $config['base_url'].'/'.$config['theme_dir'].$config['theme'],
        'site_title' => $config['site_title'],
        'current_page' => $current_page,
    ];
    hook('render_before', [&$twig_vars, &$twig, &$current_page['template']]);
    $output = $twig->render($current_page['template'] .'.html', $twig_vars);
    hook('render_after', [&$output]);
    echo $output;
}


/**
 * Translate an url to its corresponding file and creates the corresponding page.
 *
 * The Femto page returned is a php array with the following keys:
 * file - the file containing the page
 * url - the url corresponding to this page
 * title - the page title (if set in header)
 * description - the page description (if set in header)
 * robots - robot meta tag value (if set in header)
 * template - index by default
 * flags - empty by default, supported flags are no-cache,no-theme,no-markdown
 * more can be added by plugins
 * content - the content of the page
 * Additional keys can be created by plugins.
 *
 * @param string $url The url to resolve.
 * @return array Femto page, null if not found.
 */
function page($url) {
    $file = substr($url, -1) == '/' ? $url.'index.md' : $url.'.md';
    $file = $file[0] == '/' ? local::$config['content_dir'].substr($file, 1) :
      dirname(local::$current_page['file']).'/'.$file;

    $cache = Cache::file($file, 'page');
    if(!$cache) {
        return null;
    }
    if(($page = $cache->retrieve()) == null) {
        $page = page_header($file);
        if($page == null) {
            return null;
        }
        $page['content'] = trim(substr(
            file_get_contents($page['file']), $page['header_end']));
        hook('page_parse_content_before', [&$page]);
        $page['content'] = str_replace('%base_url%', local::$config['base_url'], $page['content']);
        $page['content'] = str_replace('%self_url%', $page['url'], $page['content']);
        if(!in_array('no-markdown', $page['flags'])) {
            $page['content'] = \Michelf\MarkdownExtra::defaultTransform($page['content']);
        }
        hook('page_parse_content_after', [&$page]);

        if(!in_array('no-cache', $page['flags'])) {
            $cache->store($page);
        }
    }
    return $page;
}


/**
 * Read the header, if any, of a Femto page.
 *
 * @see page()
 *
 * @param string $file The file to read.
 * @return array Femto page without content, null if not found.
 */
function page_header($file) {
    $cache = Cache::file($file, 'header');
    if(!$cache) {
        return null;
    }
    if(($page = $cache->retrieve()) == null) {
        $page = [];
        $page['file'] = $file;
        $page['url'] = substr($file, strlen(local::$config['content_dir'])-1);
        if(substr($page['url'], -9) == '/index.md') {
            $page['url'] = substr($page['url'], 0, -8);
        } else {
            $page['url'] = substr($page['url'], 0, -3);
        }

        $content = file_get_contents($file);

        $header = [
            'title' => null,
            'description' => null,
            'robots' => null,
            'template' => 'index',
            'flags' => '',
        ];
        hook('page_parse_header_before', [&$header]);
        $page['header_end'] = 0;
        $page = array_merge($page, $header);
        if(substr($content, 0, 2) == '/*') {
            $page['header_end'] = strpos($content, '*/')+2;
            $header_block = substr($content, 0, $page['header_end']);
            foreach ($header as $key => $default) {
                $match = [];
                $re = '`\*?\s*'.preg_quote($key, '`').'\s*:([^\r\n]*)`i';
                if(preg_match($re, $header_block, $match)) {
                    $page[$key] = trim($match[1]);
                }
            }
        }
        $page['flags'] = explode(',', strtolower(str_replace(' ', '',
          $page['flags'])));
        $page['title_raw'] = $page['title'];
        if($page['title'] !== null) {
            $page['title'] = htmlspecialchars($page['title'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
        }
        $page['description_raw'] = $page['description'];
        if($page['description'] !== null) {
            $page['description'] = htmlspecialchars($page['description'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
        }

        hook('page_parse_header_after', [&$page]);

        $cache->store($page);
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

    $cache = Cache::file($file.'.');
    if(!$cache) {
        return [];
    }
    if(($dir = $cache->retrieve()) == null) {
        $dir = [];
        foreach(scandir($file) as $f) {
            if($f == '.' || $f == '..') {
                continue;
            }
            if(is_dir($file.$f)) {
                $f .= '/index.md';
            }
            if(substr($f, -3) == '.md') {
                $page = page_header($file.$f);
                if($page !== null) {
                    $dir[] = $page;
                }
            }
        }
        hook('directory_complete', [&$dir]);
        $cache->store($dir);
    }
    //sorting
    if($sort == 'alpha') {
        usort($dir, __NAMESPACE__.'\\directory_sort_alpha');
    }
    hook('directory_sort', [&$dir, &$sort]);
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
function hook($hook, $args=[]) {;
    foreach(local::$plugin as $p){
        if(is_callable([$p, $hook])){
            call_user_func_array([$p, $hook], $args);
        }
    }
}


/**
 * Simple cache system storing information related to one key.
 *
 */
class Cache {
    protected $modified;
    protected $cache_file = null;

    /**
     * Construct a cache object associated with a key.
     *
     * @param string $key Key associated to this cache instance.
     * @param int $modified Time when this key was last modified.
     */
    public function __construct($key, $modified) {
        $this->modified = $modified;
        if(!local::$config['cache_enabled']) {
            return;
        }
        $hash = md5($key);
        $this->cache_file = sprintf(
          '%sfemto/%s/%s/%s.php',
          local::$config['cache_dir'],
          substr($hash, 0, 2),
          substr($hash, 2, 2),
          $hash
        );
    }

    /**
     * Check if there is data in cache.
     *
     * @return mixed Cached data, null if expired or not found.
     */
    public function retrieve() {
        if(!local::$config['cache_enabled']
          || (local::$config['debug'] && isset($_GET['purge']))) {
            return null;
        }

        if(@filemtime($this->cache_file) > $this->modified) {
            include $this->cache_file;
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
        @mkdir(dirname($this->cache_file), 0777, true);
        file_put_contents($this->cache_file,
          sprintf('<?php $value = %s;', var_export($value, true)));
    }

    /**
     * Purge a value from the cache.
     *
     */
    public function purge() {
        if(!local::$config['cache_enabled']) {
            return;
        }
        @unlink($this->cache_file);
    }

    /**
     * Create a cache object for the given file.
     *
     * @param string $file A file to associate with this cache.
     * @param string $key Extra characters to be added to the file when used as key.
     * @return Cache object or false if the file doesn't exist.
     */
    public static function file($file, $key=null) {
        $time = @filemtime($file);
        if(!$time) {
            return false;
        }
        return new self($file.$key, $time);
    }
}

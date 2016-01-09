<?php
/**
 * Femto is a simple content manager, designed to be fast and easily
 * customisable. Femto is a complete rewrite of Pico 0.8 by Gilbert Pellegrom.
 *
 * @author Sylvain Didelot
 * @license http://opensource.org/licenses/MIT
 * @version 5.0
 */

namespace femto;

/**
 * A class which holds all the femto variables to avoid poluting the global
 * namespace. Cannot be instanciated.
 *
 */
class _ {
    /**
     * Configuration.
     *
     * @see index.php
     *
     * @var array
     */
    public static $config = [
        'site_title' => 'Femto',
        'base_url' => null,
        'content_dir' => 'content/',
        'cache_enabled' => true,
        'cache_dir' => 'cache/',
        'theme' => 'default',
        'theme_dir' => 'themes/',
        'theme_base_url' => null,
        'plugin_enabled' => '',
        'plugin_dir' => __DIR__.'/plugins/',
    ];

    /**
     * Loaded plugins.
     *
     * @var array
     */
    public static $plugin = [];

    /**
     * The page corresponding to the current URL.
     *
     * @var array
     */
    public static $current_page;

    /**
     * Prevent instances.
     *
     */
    private function __construct() {}
}

/**
 * The core logic of Femto.
 * Load the plugins, route the url and render the corresponding page.
 *
 * @param array $config Configuration for this website.
 */
function run($config=[]) {
    // config
    _::$config = $config + _::$config;
    if(_::$config['base_url'] === null && isset($_SERVER['PHP_SELF'])) {
        _::$config['base_url'] = dirname($_SERVER['PHP_SELF']);
    }
    _::$config['base_url'] = rtrim(_::$config['base_url'], '/');
    _::$config['content_dir'] = rtrim(_::$config['content_dir'], '/').'/';
    _::$config['cache_dir'] = rtrim(_::$config['cache_dir'], '/').'/';
    _::$config['theme_dir'] = rtrim(_::$config['theme_dir'], '/').'/';
    _::$config['theme_base_url'] = _::$config['theme_base_url'] === null ?
      _::$config['base_url'] : rtrim(_::$config['theme_base_url'], '/');
    _::$config['plugin_dir'] = rtrim(_::$config['plugin_dir'], '/').'/';
    _::$config['plugin_enabled'] = empty(_::$config['plugin_enabled']) ? [] :
        explode(',', _::$config['plugin_enabled']);
    Cache::$default['enabled'] = _::$config['cache_enabled'];
    Cache::$default['dir'] = _::$config['cache_dir'];
    Template::$default['dir'] = _::$config['theme_dir']._::$config['theme'].'/';

    Template::$global = [
        'config' => _::$config,
        'base_url' => _::$config['base_url'],
        'theme_url' => sprintf('%s/%s%s',
            _::$config['theme_base_url'],
            _::$config['theme_dir'],
            _::$config['theme']
        ),
        'site_title' => _::$config['site_title'],
    ];

    // load plugins
    foreach(_::$config['plugin_enabled'] as $Plugin) {
        $Plugin = trim($Plugin);
        $plugin = strtolower($Plugin);
        include sprintf('%s%s.php', _::$config['plugin_dir'], $plugin);
        $Plugin = sprintf('%s\plugin\%s', __NAMESPACE__, $Plugin);
        _::$plugin[$plugin] = new $Plugin(_::$config);
    }

    // get url and normalise it if needed
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
    $normal_url = str_replace(['./', '../'], '', $url);
    if(substr($normal_url, -6) == '/index') {
        $normal_url = substr($normal_url, 0, -5);
    }
    if($normal_url != $url) {
        header('Location: '.$normal_url, true, 301);
        exit();
    }
    if(_::$config['base_url'] != '') {
        $url = strpos($url, _::$config['base_url']) === 0 ?
          substr($url, strlen(_::$config['base_url'])) : '/';
    }
    hook('request_url', [&$url]);

    // plugin url
    $match = [];
    if(preg_match('`^/plugin/([^/]+)/(.*)$`', $url, $match)) {
        list(,$plugin, $url) = $match;
        if(isset(_::$plugin[$plugin])
          && is_callable([_::$plugin[$plugin], 'url'])) {
            _::$current_page = call_user_func([_::$plugin[$plugin], 'url'], $url);
        }

    // normal page
    } else {
        page($url, True);
    }
    // not found, try hook
    if(_::$current_page == null) {
        hook('request_not_found', [&$url, &_::$current_page]);
    }
    // not found
    if(_::$current_page == null) {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        page('/404', True);
    }
    hook('request_complete', [&_::$current_page]);

    // render
    if(in_array('no-theme', _::$current_page['flags'])) {
        hook('render_after', [&_::$current_page['content'], false]);
        echo _::$current_page['content'];
        exit();
    }

    $variable = [
        'page' => _::$current_page,
    ];
    hook('render_before', [&$variable, &_::$current_page['template']]);
    $template = new Template(_::$current_page['template'] .'.html.php');
    $template->variable = $variable;
    $output = (string) $template;
    hook('render_after', [&$output, true]);
    echo $output;
}

/**
 * Map an url to its corresponding file and creates the corresponding page.
 *
 * The Femto page returned is a php array with the following keys:
 * file - the file containing the page
 * url - the url corresponding to this page
 * title - the page title (if set in header)
 * title_raw - unescaped page title
 * description - the page description (if set in header)
 * description_raw - unescaped description
 * robots - robot meta tag value (if set in header)
 * template - index by default
 * flags - empty by default, supported flags are no-cache,no-theme,no-markdown
 * more can be added by plugins
 * content - the content of the page
 * Additional keys can be created by plugins.
 *
 * @param string $url The url to resolve
 * @param bool $current Whether the page is the current one
 * @return array Femto page, null if not found
 */
function page($url, $current=False) {
    $file = substr($url, -1) == '/' ? $url.'index.md' : $url.'.md';
    $file = $file[0] == '/' ? _::$config['content_dir'].substr($file, 1) :
      dirname(_::$current_page['file']).'/'.$file;

    if(!is_file($file)) {
        return;
    }

    if($current) {
        $page =& _::$current_page;
    }

    $cache = new FileCache($file, 'page');
    if(($page = $cache->retrieve()) == null) {
        $page = page_header($file);
        $page['content'] = trim(substr(
            file_get_contents($page['file']), $page['header_end']));
        hook('page_parse_content_before', [&$page]);
        $page['content'] = str_replace('%base_url%', _::$config['base_url'], $page['content']);
        $page['content'] = str_replace('%dir_url%', $page['dir_url'], $page['content']);
        $page['content'] = str_replace('%self_url%', $page['url'], $page['content']);
        if(!in_array('no-markdown', $page['flags'])) {
            require __DIR__.'/vendor/michelf/php-markdown/Michelf/MarkdownExtra.inc.php';
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
 * @param string $file The file to read
 * @return array Femto page without content, null if not found
 */
function page_header($file) {
    $cache = new FileCache($file, 'header');
    if(($page = $cache->retrieve()) == null) {
        $page = [];
        $page['file'] = $file;
        $page['url'] = substr($file, strlen(_::$config['content_dir'])-1);
        $page['dir_url'] = dirname($page['url']);
        $page['url'] = substr($page['url'], -9) == '/index.md' ?
          substr($page['url'], 0, -8) : substr($page['url'], 0, -3);

        $content = file_get_contents($file);

        $header = [
            'title' => null,
            'description' => null,
            'robots' => null,
            'template' => 'index',
            'flags' => null,
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

        $page['flags'] = strtolower(str_replace(' ', '',$page['flags']));
        $page['flags'] = empty($page['flags']) ? [] : explode(',', $page['flags']);

        $page['title_raw'] = $page['title'];
        if($page['title'] !== null) {
            $page['title'] = htmlspecialchars($page['title'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
        }
        $page['description_raw'] = $page['description'];
        if($page['description'] !== null) {
            $page['description'] = htmlspecialchars($page['description'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
        }
        $page['robots_raw'] = $page['robots'];
        if($page['robots'] !== null) {
            $page['robots'] = htmlspecialchars($page['robots'], ENT_COMPAT|ENT_HTML5, 'UTF-8');
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
 * @param string $url The url to list
 * @param string $sort Sorting criteria
 * @param string $order Sorting order
 * @return array List of Femto pages with content removed
 */
function directory($url, $sort='alpha', $order='asc') {
    $file = substr($url, -1) == '/' ? $url : dirname($url).'/';
    $file = $file[0] == '/' ? _::$config['content_dir'].substr($file, 1) :
      dirname(_::$current_page['file']).'/'.$file;

    if(!is_dir($file)) {
        return;
    }

    $cache = new FileCache($file);
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
                if($page !== null && !in_array('no-directory', $page['flags'])) {
                    $dir[] = $page;
                }
            }
        }
        hook('directory_complete', [&$dir]);
        $cache->store($dir);
    }
    //sorting
    if($sort == 'alpha') {
        usort($dir, __NAMESPACE__.'\directory_sort_alpha');
    }
    hook('directory_sort', [&$dir, &$sort]);
    if($order != 'asc') {
        $dir = array_reverse($dir);
    }
    return $dir;
}

/**
 * Run a hook on all loaded plugins.
 *
 * @param string $hook Hook name.
 * @param array $args Arguments for the hook.
 */
function hook($hook, $args=[]) {;
    foreach(_::$plugin as $p){
        if(is_callable([$p, $hook])){
            call_user_func_array([$p, $hook], $args);
        }
    }
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
 * Simple cache system.
 *
 * Usage:
 *
 * // create a cache object associated with "key"
 * $cache = new Cache('key');
 *
 * // check if the cache exists and is less than an hour old
 * if(($data = $cache->retrieve(time()-3600)) === null) {
 *     // cache didn't exist, populate $data and store it
 *     $data = 'foo';
 *     $cache->store($data);
 * }
 *
 * // empty the cache
 * $cache->purge();
 *
 */
class Cache {
    /**
     * Default configuration.
     * - enabled: whether the cache system is enabled or not
     * - dir: directory in which cached data are located
     * - raw: whether data is stored raw or as php variables
     *
     * @var array
     */
    static public $default = [
        'enabled' => True,
        'dir' => 'cache/',
        'raw' => False,
    ];

    /**
     * Configuration of a specific cache instance.
     *
     * @var array
     */
    public $config;

    /**
     * File which contains the cached data.
     *
     * @var string
     */
    protected $cache_file;

    /**
     * Create a cache object associated with key.
     *
     * @param string $key Key associated to this cache instance
     * @param array $config Configuration
     */
    public function __construct($key, $config=[]) {
        $this->config = $config + self::$default;
        $hash = md5($key);
        $ext = $this->config['raw'] ? 'bin' : 'php';
        $this->cache_file = sprintf(
          '%s%s/%s/%s.%s',
          $this->config['dir'],
          substr($hash, 0, 2),
          substr($hash, 2, 2),
          $hash,
          $ext
        );
    }

    /**
     * Return data in the cache if any.
     *
     * @param int $modified Timestamp, when the original data was last modified
     * @return mixed Cached data, null if expired or not found
     */
    public function retrieve($modified) {
        if($this->config['enabled']
          && @filemtime($this->cache_file) > $modified) {
            if($this->config['raw']) {
                return file_get_contents($this->cache_file);
            } else {
                include $this->cache_file;
                return $value;
            }
        }
    }

    /**
     * Store data in the cache.
     *
     * @param mixed $value Data to cache
     */
    public function store($value) {
        @mkdir(dirname($this->cache_file), 0777, true);
        if($this->config['raw']) {
            file_put_contents($this->cache_file, $value);
        } else {
            file_put_contents($this->cache_file, sprintf(
              '<?php $value = %s;', var_export($value, true)
            ));
        }
    }

    /**
     * Purge data from the cache.
     *
     */
    public function purge() {
        @unlink($this->cache_file);
    }
}

/**
 * Cache objet associated to a specific file. Modified time become the file's.
 *
 * // create a cache object associated with "key"
 * $cache = new Cache('path/to/file', 'key');
 *
 * // check if the cache exists and is less than an hour old
 * if(($data = $cache->retrieve()) === null) {
 *     // cache didn't exist, populate $data and store it
 *     $data = 'foo';
 *     $cache->store($data);
 * }
 *
 * // empty the cache
 * $cache->purge();
 *
 */
class FileCache extends Cache {
    /**
     * File associated to this cache object.
     *
     * @var string
     */
    protected $file;

    /**
     * Create a cache object associated with file (and optional key).
     *
     * @param string $file File associated to this cache instance
     * @param string $key Key associated to this cache instance
     * @param array $config Configuration
     */
    public function __construct($file, $key='', $config=[]) {
        $this->file = $file;
        parent::__construct($file.$key, $config);
    }

    /**
     * Return data in the cache if any.
     *
     * @param int $modified Timestamp, when the original data was last modified
     * @return mixed Cached data, null if expired or not found
     */
    public function retrieve($modified=null) {
        if($modified == null) {
            $modified = filemtime($this->file);
        }
        return parent::retrieve($modified);
    }
}

/**
 * Simple template system, basically a tiny wrapper for pure php templates.
 *
 * Usage:
 *
 * // create a template object using "file.html"
 * $tpl = new Template('file.html');
 *
 * // assign a value to "key"
 * $tpl['key'] = 'value';
 *
 * // display the template
 * $tpl();
 *
 * // alternatively return the template as string
 * $html = (string) $tpl;
 *
 */
class Template implements \ArrayAccess {
    /**
     * Default configuration.
     * - dir: directory in which templates are located
     *
     * @var array
     */
    public static $default = [
        'dir' => 'themes/default/',
    ];

    /**
     * Configuration of a specific template instance.
     *
     * @var array
     */
    public $config;

    /**
     * Variables available in all templates.
     *
     * @var array
     */
    public static $global = [];

    /**
     * Variables available in the template.
     *
     * @var array
     */
    public $variable = [];

    /**
     * Template file.
     *
     * @var string
     */
    protected $template;

    /**
     * Create a template instance for $template.
     *
     * @param string $template Name of the template file, including extension
     * @param array $config Configuration
     */
    public function __construct($template, $config=[]) {
        $this->config = $config + self::$default;
        $this->template = sprintf(
          '%s%s',
          $this->config['dir'],
          str_replace(['./', '../'], ['', ''], $template)
        );
    }

    /**
     * @see ArrayAccess::offsetSet
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->variable[] = $value;
        } else {
            $this->variable[$offset] = $value;
        }
    }

    /**
     * @see ArrayAccess::offsetExists
     */
    public function offsetExists($offset) {
        return isset($this->variable[$offset]);
    }

    /**
     * @see ArrayAccess::offsetUnset
     */
    public function offsetUnset($offset) {
        unset($this->variable[$offset]);
    }

    /**
     * @see ArrayAccess::offsetGet
     */
    public function offsetGet($offset) {
        return isset($this->variable[$offset]) ? $this->variable[$offset] : null;
    }

    /**
     * Include the template in a clean environment.
     *
     */
    protected function wrap() {
        extract(self::$global);
        extract($this->variable);
        include $this->template;
    }

    /**
     * Print the template.
     *
     * @param array $variable Variables available in the template
     * @param bool $reset Whether to reset the variables after render
     */
    public function __invoke($variable=null, $reset=true) {
        if($variable !== null) {
            $this->variable = $variable + $this->variable;
        }
        $this->wrap();
        if($reset) {
            $this->variable = [];
        }
    }

    /**
     * Return the template as a string.
     *
     * @return string
     */
    public function __toString() {
        ob_start();
        $this->wrap();
        return ob_get_clean();
    }
}

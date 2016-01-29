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

require __DIR__.'/entity.php';
require __DIR__.'/cache.php';
require __DIR__.'/template.php';

/**
 * A class which holds all the femto variables to avoid poluting the global
 * namespace.
 *
 */
class Femto {
    /**
     * Configuration.
     *
     * @see index.php
     *
     * @var array
     */
    static public $config = [
        'site_title' => 'Femto',
        'base_url' => null,
        'content_dir' => 'content',
        'debug' => false,
        'cache_dir' => 'cache',
        'theme' => 'default',
        'theme_dir' => 'themes',
        'theme_base_url' => null,
        'plugin_enabled' => '',
        'plugin_dir' => __DIR__.'/plugins',
    ];

    /**
     * Loaded plugins
     *
     * @var array
     */
    static public $plugin = [];

    /**
     * Current working directory to resolve relative URLs.
     *
     * @var string
     */
    static public $cwd;
}

/**
 * The core logic of Femto.
 * Load the plugins, route the url and render the corresponding page.
 *
 * @param array $config Configuration for this website.
 */
function run($config=[]) {
    // save some typing
    $config = $config + Femto::$config;
    Femto::$config =& $config;

    // process config
    if($config['base_url'] === null && isset($_SERVER['PHP_SELF'])) {
        $config['base_url'] = dirname($_SERVER['PHP_SELF']);
    }
    $config['base_url'] = rtrim($config['base_url'], '/');
    $config['content_dir'] = realpath($config['content_dir']);
    $config['cache_dir'] = realpath($config['cache_dir']);
    $config['debug'] = (bool) $config['debug'];
    $config['theme_dir'] = rtrim($config['theme_dir'], '/');
    $config['theme_base_url'] = $config['theme_base_url'] === null ?
      $config['base_url'] : rtrim($config['theme_base_url'], '/');
    $config['plugin_dir'] = realpath($config['plugin_dir']);
    $config['plugin_enabled'] = empty($config['plugin_enabled']) ? [] :
        explode(',', strtolower(str_replace(' ', '', $config['plugin_enabled'])));

    Cache::$default['debug'] = $config['debug'];
    Cache::$default['dir'] = $config['cache_dir'];

    Template::$default['dir'] = realpath($config['theme_dir'].'/'.$config['theme']);

    Template::$global = [
        'config' => $config,
        'base_url' => $config['base_url'],
        'theme_url' => sprintf('%s/%s/%s',
            $config['theme_base_url'],
            $config['theme_dir'],
            $config['theme']
        ),
        'site_title' => $config['site_title'],
    ];

    // load plugins
    foreach($config['plugin_enabled'] as $plugin) {
        $plugin = trim($plugin);
        include sprintf('%s/%s.php', $config['plugin_dir'], $plugin);
        Femto::$plugin[$plugin] = sprintf('%s\plugin\%s\\', __NAMESPACE__, $plugin);
    }
    hook('config', [&$config]);

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
    $normal_url = str_replace('//', '/', $normal_url);
    if(substr($normal_url, -6) == '/index') {
        $normal_url = substr($normal_url, 0, -5);
    }
    if($normal_url != $url) {
        header('Location: '.$normal_url, true, 301);
        exit();
    }

    // strip base url and call request hook
    if(Femto::$config['base_url'] !== '') {
        if(strpos($url, Femto::$config['base_url']) !== 0 ) {
            throw new \Exception('Missconfigured base url');
        }
        $url = substr($url, strlen(Femto::$config['base_url']));
    }
    hook('request_url', [&$url]);

    // save cwd
    Femto::$cwd = substr($url, -1) == '/' ? rtrim($url, '/') : dirname($url);

    // plugin url
    $page = null;
    if(substr($url, 0, 8) == '/plugin/') {
        if(preg_match('`^/plugin/([^/]+)/(.*)$`', $url, $match)) {
            list(,$plugin, $url) = $match;
            if(isset(Femto::$plugin[$plugin])
              && function_exists(Femto::$plugin[$plugin].'url')) {
                $page = call_user_func(Femto::$plugin[$plugin].'url', $url);
            }
        }
    // normal page
    } else {
        $page = Page::resolve($url);
    }

    // not found, try hook
    if(!$page instanceof VirtualPage) {
        hook('request_not_found', [&$url, $page]);
    }
    // not found
    if(!$page instanceof VirtualPage) {
        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        $page = Page::resolve('/404');
    }
    hook('request_complete', [$page]);

    // render
    if(in_array('no-theme', $page['flags'])) {
        $output = $page['content'];
        hook('render_after', [&$output, false]);
        echo $output;
        exit();
    }

    $variable = [
        'page' => $page,
    ];
    hook('render_before', [&$variable, $page['template']]);
    $template = new Template($page['template']);
    $template->variable = $variable;
    $output = (string) $template;
    hook('render_after', [&$output, true]);
    echo $output;
}

/**
 * Run a hook on all loaded plugins.
 *
 * @param string $hook Hook name
 * @param array $args Arguments for the hook
 */
function hook($hook, $args=[]) {
    foreach(Femto::$plugin as $p){
        if(function_exists($p.$hook)) {
            call_user_func_array($p.$hook, $args);
        }
    }
}

/**
 * Transform a relative URL or one with the base address included into an URL
 * relative to the content directory.
 *
 * @param string $url URL to process
 * @param string $cwd Directory to relate URLs to
 * @param bool $allow_base_url_missmatch Whether to allow absolute URLs which
 * do not start with the base url
 * @return string Processed URL
 */
function real_url($url, $cwd=null, $allow_base_url_missmatch=true) {
    if($cwd === null) $cwd = Femto::$cwd;
    if(substr($url, 0, 1) !== '/') {
        return $cwd.'/'.$url;
    } else if(Femto::$config['base_url'] !== '') {
        if(strpos($url, Femto::$config['base_url']) === 0 ) {
            return substr($url, strlen(Femto::$config['base_url']));
        } else if (!$allow_base_url_missmatch) {
            return $url;
        }
    }
    return $url;
}

/**
 * Map an URL to the corresponding content file.
 *
 * @param string $url URL to map
 * @param string $cwd Directory to relate URLs to
 * @return string Existing content file or null
 */
function url_to_file($url, $cwd=null) {
    $file = Femto::$config['content_dir'].real_url($url, $cwd);
    $file = realpath($file);
    if($file !== false) return $file;
}

/**
 * Compute the URL matching a content file.
 *
 * @param string $file File to map
 * @return string URL to access the file
 */
function file_to_url($file) {
    if(substr($file, -3) === '.md') {
        $file = substr($file, 0, -3);
        if(substr($file, -6) === '/index') {
            $file = substr($file, 0, -5);
        }
    }
    $url = substr($file, strlen(Femto::$config['content_dir']));
    return Femto::$config['base_url'].$url;
}

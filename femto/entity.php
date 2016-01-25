<?php

namespace femto;

/**
 * Entities represent a single page or directory within Femto's content folder.
 *
 */
class Entity implements \ArrayAccess {
    /**
     * The content of the entity.
     *
     * @var array
     */
    protected $data;

    /**
     * Optionally populate the entity.
     *
     * @param array $data content of the entity
     */
    public function __construct($data=[]) {
        $this->data = $data;
    }

    /**
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    /**
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    /**
     * @see ArrayAccess::offsetGet()
     */
    public function &offsetGet($offset) {
        if(!isset($this->data[$offset])) $this->data[$offset] = null;
        return $this->data[$offset];
    }
}

/**
 * Pages which do not exist as files.
 *
 * Plugins should return a VirtualPage if they generate the page on the fly.
 *
 */
class VirtualPage extends Entity {
    /**
     * Default headers of pages.
     *
     * @var array
     */
    static public $header = [
        'title' => '',
        'description' => '',
        'robots' => '',
        'template' => 'index',
        'flags' => [],
    ];

    /**
     * Build the page with default headers and data provided.
     *
     * @param array $data Page's content
     */
    public function __construct($data) {
        $this->data = $data + self::$header;
    }

    /**
     * Process the content of a page. Be mindful of infinite loops when calling
     * from a plugin as hooks are called.
     *
     */
    public function content() {
        if(empty($this->data['content']) ||
          in_array('raw-content', $this->data['flags']))
            return;

        hook('page_content_before', [$this]);

        if(!in_array('no-markdown', $this->data['flags'])) {
            require __DIR__.'/vendor/michelf/php-markdown/Michelf/MarkdownExtra.inc.php';
            $this->data['content'] = \Michelf\MarkdownExtra::defaultTransform($this->data['content']);
        }

        $this->data['content'] = str_replace('femto://self', $this['url'], $this->data['content']);
        $url = isset($this->data['directory']) ? $this->data['directory']['url'] : '';
        $this->data['content'] = str_replace('femto://directory', $url, $this->data['content']);
        $this->data['content'] = str_replace('femto://theme', Template::$global['theme_url'].'/', $this->data['content']);
        $this->data['content'] = str_replace('femto://', Femto::$config['base_url'].'/', $this->data['content']);

        hook('page_content_after', [$this]);
    }
}

/**
 * The basic Femto page.
 *
 * Corresponds to a .md file in the content directory.
 *
 */
class Page extends VirtualPage {
    /**
     * Keep track of already loaded pages.
     *
     * @var array
     */
    static protected $loaded;

    /**
     * Read page information from the corresponding file.
     *
     * @param string $file File corresponding to the page
     */
    public function __construct($file) {
        $cache = new FileCache($file, 'femto_page_meta');
        if(($this->data = $cache->retrieve()) == null) {
            $content = file_get_contents($file);
            $this->data = [];
            $this->data['file'] = $file;
            $this->data['url'] = file_to_url($file);
            $this->data['directory'] = Directory::load(dirname($this->data['file']));

            // extract header data
            $header_end = 0;
            if(substr($content, 0, 2) == '/*') {
                $header_end = strpos($content, '*/')+2;
                $header_block = substr($content, 0, $header_end);
                foreach (self::$header as $key => $default) {
                    $match = [];
                    $re = '`\*?\s*'.preg_quote($key, '`').'\s*:([^\r\n]*)`i';
                    if(preg_match($re, $header_block, $match)) {
                        $this->data[$key] = trim($match[1]);
                    } else {
                        $this->data[$key] = $default;
                    }
                }
            }

            // process header data
            if(!empty($this->data['flags'])) {
                $this->data['flags'] = strtolower(str_replace(' ', '',$this->data['flags']));
                $this->data['flags'] = empty($this->data['flags']) ? [] : explode(',', $this->data['flags']);
            }

            hook('page_header', [$this]);

            $this->data['content'] = trim(substr($content, $header_end));
            $cache->store($this->data);
        }
    }

    /**
     * Map an URL to its corresponding file and creates the page.
     *
     * @param string $url The URL to resolve
     * @return Page Femto page or null
     */
    static public function resolve($url) {
        $url = substr($url, -1) == '/' ? $url.'index.md' : $url.'.md';
        $file = url_to_file($url);
        if($file) return self::load($file);
    }

    /**
     * Create the page corresponding to a file.
     *
     * @param string $file The file to load
     * @return Page Femto page or null
     */
    static public function load($file) {
        if(isset(self::$loaded[$file])) {
            return self::$loaded[$file];
        }

        $cache = new FileCache($file, 'femto_page');
        if(($page = $cache->retrieve()) == null) {
            $page = new self($file);
            $page->content();
            $cache->store($page);
        }

        return self::$loaded[$file] = $page;
    }
}


/**
 * A directory which does not exists in the content folder.
 *
 * Plugins should use VirtualDirectory for structures generated on the fly.
 *
 */
class VirtualDirectory extends Entity {

    /**
     * Optionally populate the directory.
     *
     * @param array $data Directory content
     */
    public function __construct($data=[]) {
        $this->data = $data + ['content'=>[]];
    }

    /**
     * Return the content of the directory as a sorted array.
     *
     * @param string $sort Sort criteria
     * @param string $order Sort order
     * @return array Sorted directory content
     */
    public function sort($sort='alpha', $order='asc') {
        $dir = $this->data['content'];
        //sorting
        if($sort == 'alpha') {
            usort($dir, function($a, $b){
                return strcmp($a['title'], $b['title']);
            });
        }
        hook('directory_sort', [&$dir, &$sort]);

        return $order == 'asc' ? $dir : array_reverse($dir);
    }
}

/**
 * A directory in the content folder.
 *
 */
class Directory extends VirtualDirectory {
    /**
     * Keep track of already loaded directories.
     *
     * @var array
     */
    static protected $loaded;

    /**
     * Return the content of the directory as a sorted array.
     *
     * @param string $sort Sort criteria
     * @param string $order Sort order
     * @return array Sorted directory content
     */
    public function sort($sort='alpha', $order='asc') {
        $this->content();
        return parent::sort($sort, $order);
    }

    /**
     * Load the content.
     * This is done separately to avoid infinite loops when initiating a
     * directory inside a Page header.
     *
     */
    protected function content() {
        if($this->data['content']) return;

        $cache = new FileCache($this->data['file'], 'femto_directory');
        if(($this->data['content'] = $cache->retrieve()) === null) {
            $this->data['content'] = [];
            foreach(scandir($this->data['file']) as $f) {
                if($f == '.' || $f == '..') {
                    continue;
                }
                if(is_dir($this->data['file'].'/'.$f)) {
                    $f .= '/index.md';
                }
                if(substr($f, -3) == '.md') {
                    $page = new Page($this->data['file'].'/'.$f);
                    if(!in_array('no-directory', $page['flags'])) {
                        unset($page['content']);
                        $this->data['content'][] = $page;
                    }
                }
            }
            $cache->store($this->data['content']);
            hook('directory_complete', [$this]);
        }
    }


    /**
     * Map an URL to its corresponding directory and creates it.
     *
     * @param string $url The URL to resolve
     * @return Directory Femto directory
     */
    static public function resolve($url) {
        $file = url_to_file($url);
        return is_dir($file) ? self::load($file) : new VirtualDirectory();
    }

    /**
     * Create the directory corresponding to a file.
     *
     * @param string $file The file to load
     * @return Directory Femto directory
     */
    static public function load($file) {
        if(isset(self::$loaded[$file])) {
            return self::$loaded[$file];
        }

        $directory = new self([
            'file' => $file,
            'url' => file_to_url($file),
        ]);

        return self::$loaded[$file] = $directory;
    }
}

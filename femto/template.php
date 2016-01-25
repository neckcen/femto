<?php

namespace femto;

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
class Template implements \ArrayAccess,\Iterator {
    /**
     * Default configuration.
     * - dir: directory in which templates are located
     *
     * @var array
     */
    public static $default = [
        'dir' => 'themes/default',
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
        $file = $this->config['dir'].'/'.$template.'.html.php';
        $this->template = new FileCache($file, 'template', ['raw'=>True]);
        if(!$this->template->valid()) {
            $template = file_get_contents($file);
            /* this ugly thing matches echo tags without tripping on things like
               <?=$x['?>']?> or <?=(true)?'a':b'?>
            */
            $template = str_replace('<?==', '<?php echo ', $template);
            $template = preg_replace('`<\?=((?:[^\?"\']|\?[^>]|"(?:[^"]|\\\\")*"|\'(?:[^\']|\\\\\')*\')+)\?>`', '<?php escape($1); ?>', $template);
            $template = str_replace('?><?php', '', $template);
            $template = '<?php namespace femto\template; ?>'.$template;
            $this->template->store($template);
        }
    }

    /**
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->variable[] = $value;
        } else {
            $this->variable[$offset] = $value;
        }
    }

    /**
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset) {
        return isset($this->variable[$offset]);
    }

    /**
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset) {
        unset($this->variable[$offset]);
    }

    /**
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset) {
        return isset($this->variable[$offset]) ? $this->variable[$offset] : null;
    }

    /**
     * @see Iterator::rewind()
     */
    function rewind() {
        return reset($this->variable);
    }

    /**
     * @see Iterator::current()
     */
    function current() {
        return current($this->variable);
    }

    /**
     * @see Iterator::key()
     */
    function key() {
        return key($this->variable);
    }

    /**
     * @see Iterator::next()
     */
    function next() {
        return next($this->variable);
    }

    /**
     * @see Iterator::valid()
     */
    function valid() {
        return key($this->variable) !== null;
    }

    /**
     * Include the template in a clean environment.
     *
     */
    protected function wrap() {
        require __DIR__.'/template.util.php';
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

/**
 * Escape a string for use within HTML.
 *
 * @param string $string String to escape
 * @return string Escaped string
 */
function escape($string) {
    return htmlspecialchars($string, ENT_COMPAT|ENT_HTML5|ENT_DISALLOWED, 'UTF-8');
}

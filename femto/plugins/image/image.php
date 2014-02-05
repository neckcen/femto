<?php

/**
 * A plugin for Femto to make working with images easier.
 *
 * @author Sylvain Didelot
 */
class image {
    protected $config;

    /**
     * Instance the plugin with given config.
     *
     * @param array $config The website configuration.
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Display an image or thumbnail.
     *
     * @param string $url The plugin-specific url.
     */
    public function url($url) {
        $file = $this->config['content_dir'].$url;
        // last modified header (so browsers can cache images)
        $time = @filemtime($file);
        if($time == false) {
            return;
        }
        $header = apache_request_headers();
        $header = isset($header['If-Modified-Since']) ?
          strtotime($header['If-Modified-Since']) : 0;
        if($header >= $time) {
            header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
            exit();
        }
        header('Last-Modified: '.date(DATE_RFC1123, $time));

        // width not set, display the full image
        if(!isset($_GET['w'])) {
            $info = @getimagesize($file);
            if($info) {
                header('Content-type: '.$info['mime']);
                readfile($file);
                exit();
            }
        }
        //get info from GET
        $width = (int) $_GET['w'];
        $height = isset($_GET['h']) ? (int) $_GET['h'] : null;

        // create thumbnail
        $data = null;
        if($this->config['cache_enabled']) {
            $hash = md5($file.$width.$height);
            $cache = sprintf(
              '%s/image/%s/%s/%s.jpg',
              $this->config['cache_dir'],
              substr($hash, 0,2), substr($hash, 2,2), $hash
            );
            if(@filemtime($cache) > $time) {
                $data = file_get_contents($cache);
            }
        }
        if($data == null) {
            // check target exists and is an image
            $type = @exif_imagetype($file);
            if($type === false) {
                return;
            }

            if($type == IMAGETYPE_JPEG) {
                $img = imagecreatefromjpeg($file);
            } else if ($type == IMAGETYPE_PNG) {
                $img = imagecreatefrompng($file);
            } else if ($type == IMAGETYPE_GIF) {
                $img = imagecreatefromgif($file);
            } else {
                return;
            }
            if($img == false) {
                return;
            }

            // create thumbnail
            if(!$height) {
                $height = round($width/(imagesx($img)/imagesy($img)));
            }
            $thumb = imagecreatetruecolor($width,$height);
            imagecopyresampled(
                $thumb, $img,
                0, 0, 0, 0,
                $width, $height, imagesx($img), imagesy($img)
            );

            // save it and destroy resources
            ob_start();
            imagejpeg($thumb, null, 65);
            $data = ob_get_clean();
            imagedestroy($img);
            imagedestroy($thumb);
            if($this->config['cache_enabled']) {
                @mkdir(dirname($cache), 0777, true);
                file_put_contents($cache, $data);
            }
        }

        // display image
        header('Content-type: image/jpg');
        echo $data;
        exit();
    }

    /**
     * Search for images and process them. Images starting with "http://" are
     * ignored. If the file name starts with "/" it is treated as relative to
     * content_dir. Othwerise the script looks for it in the same directory as
     * the page.
     *
     * Examples:
     * ![alt text](http://example.tld/file.jpg) - unchanged
     * ![alt text](/file.jpg) - matched to content_dir/file.jpg
     * ![alt text](file.jpg) - matched to current_page_dir/file.jpg
     *
     * Additionally, a dimension can be put after the file name to create a
     * thumbnail. Only works with local files.
     *
     * Examples:
     * ![alt text](http://example.tld/file.jpg 300x200) - ignored
     * ![alt text](file.jpg 300) - 300 width thumbnail, calculate height
     * ![alt text](file.jpg 300x200) - 300x200 thumbnail, ratio ignored
     * ![alt text](file.jpg 300 "caption text") thumbnail with caption
     *
     * @param array $page Femto page.
     */
    public function page_before_parse_content(&$page) {
        $match = array();
        $re = '`!\[([^\]]*)\]\(([^\) ]+)(?: ([0-9]+)(?:x([0-9]+))?)?(?: "([^"]*)")?\)`';
        if(preg_match_all($re, $page['content'], $match, PREG_SET_ORDER)) {
            $url = $this->config['base_url'].'plugin/'.__CLASS__;
            foreach ($match as $m) {
                list($tag, $alt, $file) = $m;
                if(substr($file, 0, 7) == 'http://') {
                    continue;
                }
                $width = isset($m[3]) ? $m[3] : null;
                $height = isset($m[4]) ? $m[4] : null;
                $caption = isset($m[5]) ? $m[5] : null;
                if($file[0] != '/') {
                    $file = dirname($page['file']).'/'.$file;
                    $file = substr($file, strlen($this->config['content_dir']));
                }
                if($width) {
                    if($caption) {
                        $caption = '<figcaption>'.$caption.'</figcaption>';
                    }
                    $parsed = sprintf(
                      '<figure><a href="%s/%s">'.
                      '<img src="%s/%s?w=%d&amp;h=%d" alt="%s"/></a>%s</figure>',
                      $url, $file, $url, $file, $width, $height, $alt, $caption
                    );
                } else {
                    $parsed = sprintf(
                      '<img src="%s/%s" alt="%s" title="%s"/>',
                      $url, $file, $alt, $caption
                    );
                }
                $page['content'] = str_replace($tag, $parsed, $page['content']);
            }
        }

    }
}

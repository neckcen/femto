<?php

/**
 * A plugin for Femto that allows associating a php file with a page.
 *
 * @author Sylvain Didelot
 */
class php {
    /**
     * Add the php header.
     *
     * @param array $header List of headers.
     */
    public function page_before_read_header(&$header) {
        $header['php'] = null;
    }

    /**
     * Include the file set in the php header. If variables are set in $vars
     * they will be automatically substitued in the content.
     *
     * @param array $page Femto page.
     */
    public function page_before_parse_content(&$page) {
        if($page['php']) {
            $file = dirname($page['file']).'/'.$page['php'];
            $vars = array();
            include($file);
            if(!empty($vars)) {
                foreach($vars as $key=>$value) {
                    $key = '%'.$key.'%';
                    $page['content'] = str_replace($key, $value, $page['content']);
                }
            }
        }
    }
}

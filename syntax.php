<?php
/**
 * Filelist Plugin: Lists files matching a given glob pattern.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Frank Schiebel <frank@ua25.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/confutils.php');
require_once(DOKU_INC.'inc/pageutils.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_stafflist extends DokuWiki_Syntax_Plugin {

    var $mediadir;

    function syntax_plugin_stafflist() {
        // load helper functions
        if (!$myhelper =& plugin_load('helper', 'stafflist')) return false;
        global $conf;
        $basedir = $conf['savedir'];
        if (!$myhelper->_path_is_absolute($basedir)) {
            $basedir = DOKU_INC . '/' . $basedir;
        }
        $this->mediadir = $myhelper->_win_path_convert($myhelper->_realpath($basedir.'/media').'/');
    }

    /**
     * return some info
     */
    function getInfo() {
        return array(
            'author' => 'Frank Schiebel',
            'email'  => 'frank@linuxmuster.net',
            'date'   => '2019-10-09',
            'name'   => 'Stafflist Plugin',
            'desc'   => 'Creates a stafflist from a CSV file',
            'url'    => 'https://github.com/OpenSchulportfolio/dokuwiki-plugin-stafflist',
        );
    }

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 222; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{stafflist>.+?\}\}',$mode,'plugin_stafflist');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        // do not allow the syntax in comments
        if (!$this->getConf('allow_in_comments') && isset($_REQUEST['comment']))
        return false;

        $match = substr($match, 2, -2);
        list($type, $match) = split('>', $match, 2);
        list($staffcsv, $flags) = split('&', $match, 2);

        $flags = split('&', $flags);
        $params = array(
            'showfields' => "",
            'headersubst' => "",
        );

        foreach($flags as $flag) {
            list($name, $value) = split('=', $flag);
            $params[trim($name)] = trim($value);
        }

        return array($type, $staffcsv, $params, $title);
    }

    /**
     * Create output
     */
    function render($mode, Doku_Renderer $renderer, $data) {
        global $conf;

        // disable caching
        $renderer->info['cache'] = false;

        // load helper functions
        if (!$myhelper =& plugin_load('helper', 'stafflist')) return false;

        list($type, $staffcsv, $params, $title) = $data;
        if ($mode == 'xhtml') {

            $result = $myhelper->create_stafflist($staffcsv, $params);
            $renderer->doc .= $result;
	    
        }
        return false;
    }
    

}

// vim:ts=4:sw=4:et:enc=utf8:

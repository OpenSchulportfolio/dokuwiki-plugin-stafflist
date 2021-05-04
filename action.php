<?php
/**  
 * Action component for the stafflist plugin
 * @author  Frank Schiebel <frank@talheim.net>
 * 
 */

// No dokuwiki, no output
if (!defined('DOKU_INC')) 
{    
    die();
}

class action_plugin_stafflist extends DokuWiki_Action_Plugin
{
    function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'dw_start');
    }


    function dw_start(&$event, $param)
    {

        global $INPUT;
        global $USERINFO;
        global $conf;
        global $ID;

        //get stafflist_action
        $stafflist_action = $INPUT->get->str('stafflist');
        
        if ($stafflist_action == "get")
        {
            if (!$myhelper =& plugin_load('helper', 'stafflist')) return false;

            // No token neccesary, list is publicly visible
            
            // access control via get token 
            // get accesstoken
            // $tstoken = $INPUT->get->str('stafflist_token');
            // not token, no data

            // if ( $stafflist_token === '' ) exit;
            // get all valid tokens from config
            // $allvalidtokens = confToHash(DOKU_CONF . "stafflist.auth.php");
            // $tokenkey = array_search($stafflist_token, $allvalidtokens);
            // no valid token? exit!
            // if ( ! $tokenkey ) exit;

            $json = $myhelper->getJsonList;
            //print "<pre>";
            print($json);
            //print "</pre>";
        }

        // stop dokuwiki if a stafflist_action was given
        if ($stafflist_action != "" ) 
        {
            $event->preventDefault();
            $event->stopPropagation();
            exit;
        }
        
    }  

}

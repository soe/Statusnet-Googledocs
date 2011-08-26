<?php
if (!defined('STATUSNET')) {
    exit(1);
}

define('GOOGLEDOCS_SERVICE', 10); // @fixme - should check against database?

/**
 * Integration with GoogleDocs. Allow attaching Google Docs files inside StatusNet
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Soe Thiha <soe@soe.im>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class GoogleDocsPlugin extends Plugin {

    const VERSION = '0.1';

    public function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name' => 'GoogleDocs Integration',
            'version' => self::VERSION,
            'author' => 'Soe Thiha',
            'homepage' => 'http://status.net/wiki/Plugin:GoogleDocs',
            'rawdescription' => _m('Integration with GoogleDocs. Allow attaching Google Docs files inside StatusNet'
            )
        );
        return true;
    }

    /**
     * Initializer for the plugin.
     */
    function initialize()
    {
        // Allow the key and secret to be passed in
        // Control panel will override

        if (isset($this->consumer_key)) {
            $key = common_config('googledocs', 'consumer_key');
            if (empty($key)) {
                Config::save('googledocs', 'consumer_key', $this->consumer_key);
            }
        }

        if (isset($this->consumer_secret)) {
            $secret = common_config('googledocs', 'consumer_secret');
            if (empty($secret)) {
                Config::save('googledocs', 'consumer_secret', $this->consumer_secret);
            }
        }

        // check the user's authorization
        if(common_current_user()) {
            // get from the database then set the session
            if(!isset($_SESSION['GOOGLEDOCS_ACCESS_TOKEN'])) {
                $flink = new Foreign_link();
                $flink = $flink->getByUserID(common_current_user()->id, GOOGLEDOCS_SERVICE);
                
                if($flink->credentials)
                    $_SESSION['GOOGLEDOCS_ACCESS_TOKEN'] = $flink->credentials;
                    
                // @fixme verify too?                
            }
        }
    }

    /**
     * Check to see if there is a consumer key and secret defined
     * for Google docs integration.
     *
     * @return boolean result
     */
    static function hasKeys()
    {
        $ckey    = common_config('googledocs', 'consumer_key');
        $csecret = common_config('google', 'consumer_secret');

        if (empty($ckey) && empty($csecret)) {
            $ckey    = common_config('googledocs', 'global_consumer_key');
            $csecret = common_config('googledocs', 'global_consumer_secret');
        }

        if (!empty($ckey) && !empty($csecret)) {
            return true;
        }

        return false;
    }

    /**
     * Add paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        //if (self::hasKeys()) {
            $m->connect('googledocs/authorization',
                array('action' => 'googledocsauthorization'));
            $m->connect('googledocs/get/:fileid', 
                array('action' => 'googledocsget'),
                array('fileid' => '.+'));
            $m->connect('googledocs/download/:fileid/:format', 
                array('action' => 'googledocsdownload'),
                array('fileid' => '.+', 'format' => '[a-z]+'));
            $m->connect('googledocs/list',
                array('action' => 'googledocslist'));                
            $m->connect('settings/googledocs', 
                array('action' => 'googledocssettings'));
        //}

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the Twitter bridge
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls) {
            case 'GoogledocsauthorizationAction':
            case 'GoogledocslistAction':
            case 'GoogledocsgetAction':
            case 'GoogledocsdownloadAction':
                require_once INSTALLDIR . '/plugins/GoogleDocs/lib/GdataOauthClient.php';
                include_once $dir . '/actions/'. strtolower(mb_substr(mb_substr($cls, 0, -6), 10)) .'.php';
                return false;
            default:
                return true;
        }
    }
        
    // @fixme currently positioned in proximity then jQuery magic, needs Event refactor
    // render google docs attach button
    // @fixme for now just use jQuery to render the button
    function onEndShowNoticeForm($noticeForm)
    {
        if(common_current_user())
            $noticeForm->element('div',array('class' => 'google_docs_attach'),_m('Attach Google Docs'));
    }
    
    // @fixme currently positioned in proximity then jQuery magic, needs Event refactor
    // files (filtered for google docs) attached to the notice is displayed as list
    function onEndShowNoticeItem($noticeListItem)
    {
        
        require_once INSTALLDIR . '/plugins/GoogleDocs/classes/googledocsattachmentlist.php';
        $al = new GoogledocsAttachmentList($noticeListItem->notice, $noticeListItem->out);
        $al->show();
    }


    /**
     * Save notice title after notice is saved
     *
     * @param Action $action NewNoticeAction being executed
     * @param Notice $notice Notice that was saved
     *
     * @return boolean hook value
     */
    function onEndNoticeSaveWeb($action, $notice)
    {
        require_once INSTALLDIR . '/plugins/GoogleDocs/classes/googledocsfile.php';
        $docs = $action->arg('googledocs');

        for($i = 0; $i < count($docs); $i++) {
            $attachment =  new GoogledocsFile('', $docs['title'][$i], $docs['id'][$i], $docs['filetype'][$i]);
                
            $attachment->attachToNotice($notice);
        }

        // @fixme exception ?

        return true;
    }

    function onEndLogout($action) {
        unset($_SESSION['GOOGLEDOCS_REQUEST_TOKEN']);
        unset($_SESSION['GOOGLEDOCS_ACCESS_TOKEN']);
    }
    
    function onEndShowScripts($action){
        if(common_current_user()) {
            $action->script($this->path('js/docs.js'));
    
            if(!isset($_SESSION['GOOGLEDOCS_ACCESS_TOKEN'])) $authorize = 'authorize';
            
            $authorizer = '<div id="google_docs_authorizer" class="'.$authorize.'" style="display: none;">'
                            .'<a href="'.common_local_url('').'/googledocs/authorization" class="invite_button">'
                            .'Authenticate'
                            .'</a>'
                        .'</div>';
            $action->raw($authorizer);
            
            // @fixme rewrite in element format?
            $browser = '<div id="google_docs_browser" style="display: none;">'
                            .'<div id="browser_search"><input type="text" id="search_field" value="" /><input type="button" id="search_button" value="Search" /></div>'
                            .'<div style="clear: both;"></div>'
                            .'<div id="browser_files" class="unloaded"><h3>Available Files</h3><div class="wrapper"><div class="loading"></div><ul class="googledocs_list"></ul></div></div>'
                            .'<div id="browser_queue"><h3>Selected Files</h3><div class="wrapper"><ul class="googledocs_list"></ul></div></div>'
                            .'<div style="clear: both;"></div>'
                            .'<span id="load_more" class="enabled">Load more...</span><span class="loading" style="display: none;"></span>'
                            .'<input type="hidden" id="common_local_url" value="'.common_local_url('').'" />'
                            .'<input type="hidden" id="start_index" value="1" />'
                            .'<input type="hidden" id="caller_form_id" value="" />'
                        .'</div>';
            $action->raw($browser);
        }
    }

    function onEndShowStatusNetStyles($action)
    {
        if(common_current_user())
            $action->cssLink($this->path('css/docs.css'));
    }
    
    function getAccessToken($userid)
    {
        // @fixme - quick hack to work around persistent session
        $_SESSION['GOOGLEDOCS_ACCESS_TOKEN'] = False;
        $_SESSION['GOOGLEDOCS_EMAIL'] = False;

        // check the credentials in session first
        if(!$_SESSION['GOOGLEDOCS_ACCESS_TOKEN']) {

            $flink = Foreign_link::getByUserID($userid, GOOGLEDOCS_SERVICE);
            
            if(isset($flink) && method_exists(unserialize($flink->credentials), 'getToken')) {
                $_SESSION['GOOGLEDOCS_ACCESS_TOKEN'] = $flink->credentials;
            }  
        }
        
        $accessToken = unserialize($_SESSION['GOOGLEDOCS_ACCESS_TOKEN']);

        if(!$_SESSION['GOOGLEDOCS_EMAIL']) {
            $consumer = new GdataOauthClient();
            $httpClient = $accessToken->getHttpClient($consumer->getOauthOptions());
            $docsService = new Zend_Gdata_Docs($httpClient, '');  
            
            try {
                // get user's google email 
                $metadata = $docsService->get('https://docs.google.com/feeds/metadata/default?v=3')->getBody();
                preg_match('/<email>(.*)<\/email>/', $metadata, $matches);
                $_SESSION['GOOGLEDOCS_EMAIL'] = $matches[1];
            } catch ( Exception $e ) {
                // @fixme - check this again
                // user token is not valid anymore
                return False;
            }    
        }

        return $accessToken;
    }

}
?>
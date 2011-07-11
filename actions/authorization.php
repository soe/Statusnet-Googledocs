<?php

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for doing OAuth authentication against Google
 *
 * Peforms the OAuth "dance" between StatusNet and Google -- requests a token,
 * authorizes it, and exchanges it for an access token.  It also creates a link
 * (Foreign_link) between the StatusNet user and Twitter user and stores the
 * access token and secret in the link.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Soe Thiha <soe@soe.im>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class GoogledocsauthorizationAction extends Action
{

    var $access_token = null;
    var $verifier     = null;

    /**
     * Initialize class members. Looks for 'oauth_token' parameter.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->signin      = $this->boolean('signin');
        $this->oauth_token = $this->arg('oauth_token');
        $this->verifier    = $this->arg('oauth_verifier');

        return true;
    }

    /**
     * Handler method
     *
     * @param array $args is ignored since it's now passed in in prepare()
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);

        if (common_logged_in()) {
            $user  = common_current_user();
            $flink = Foreign_link::getByUserID($user->id, GOOGLEDOCS_SERVICE);
            
            // If there's already a foreign link record and a foreign user
            // it means the accounts are already linked, and this is unecessary.
            // So go back.
            
            if (isset($flink)) {
                //$fuser = $flink->getForeignUser();
                //if (!empty($fuser)) {
                    //common_redirect(common_local_url('googledocssettings'));
                    // @fixme - maybe verify if credentials are still working
                    // already logged in and authenticated, so redirect back to home page
                    common_redirect(common_local_url(''));
                //}
            }
        }

        // @fixme - if the google docs attachment is only for registered users
        
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            // $this->oauth_token is the access token obtained after authorization
            // if it is empty, we are at the beginning 
            if (empty($this->oauth_token)) {
                $this->authorizeRequestToken();
            } else {
                $this->saveAccessToken();
            }
        }
    }

    /**
     * Asks Google for a request token, and then redirects to Google
     * to authorize it.
     *
     * @return nothing
     */
    function authorizeRequestToken()
    {
        $scopes = array(
            'https://docs.google.com/feeds/'
        );

        $consumer = new GdataOauthClient();
        
        $_SESSION['GOOGLEDOCS_REQUEST_TOKEN'] = serialize($consumer->fetchRequestToken(
            implode(' ', $scopes), common_local_url('googledocsauthorization').'?do=access_token'));
        $auth_link = $consumer->getRedirectUrl();
        
        common_redirect($auth_link);
        
        // @fixme handle for exception
    }

    /**
     * Called when Twitter returns an authorized request token. Exchanges
     * it for an access token and stores it.
     *
     * @return nothing
     */
    function saveAccessToken()
    {
        // Check to make sure Google returned the same request
        // token we sent them
        
        // @fixme is there a better way to call object method?
        $requestToken = unserialize($_SESSION['GOOGLEDOCS_REQUEST_TOKEN']);
        
        if ($requestToken->getToken() != $this->oauth_token) {
            $this->serverError(
                _m('Couldn\'t link your Google account: oauth_token mismatch.')
            );
        }

        $consumer = new GdataOauthClient();
        
        $_SESSION['GOOGLEDOCS_ACCESS_TOKEN'] = serialize($consumer->fetchAccessToken());
        
        $accessToken = unserialize($_SESSION['GOOGLEDOCS_ACCESS_TOKEN']);

        // @fixme handle for exception

        // @fixme now, only for the logged in users
        if (common_logged_in()) {
            // Save the access token in foreign link

            $user = common_current_user();
            $this->saveForeignLink($user->id, '', $_SESSION['GOOGLEDOCS_ACCESS_TOKEN']);
            //save_twitter_user($twitter_user->id, $twitter_user->screen_name);

        } /*else {

            $this->twuid = $twitter_user->id;
            $this->tw_fields = array("screen_name" => $twitter_user->screen_name,
                                     "fullname" => $twitter_user->name);
            $this->access_token = $atok;
            $this->tryLogin();
        }*/

        if (common_logged_in()) {
            common_redirect(common_local_url(''));
        }
    }

    /**
     * Saves a Foreign_link between Google account and local user,
     * which includes the access token and secret.
     *
     * @param int        $user_id StatusNet user ID
     * @param int        $foreign_id  Foreign user ID 
     * @param OAuthToken $access_token   the Zend Oauth access token serialized object
     *
     * @return nothing
     */
    function saveForeignLink($user_id, $foreign_id, $ACCESS_TOKEN)
    {
        $flink = new Foreign_link();

        $flink->user_id = $foreign_id;
        $flink->service = GOOGLEDOCS_SERVICE;

        // delete stale flink, if any
        $result = $flink->find(true);

        if (!empty($result)) {
            $flink->safeDelete();
        }

        $flink->user_id     = $user_id;
        $flink->foreign_id  = $foreign_id;
        $flink->service     = GOOGLEDOCS_SERVICE;

        // serialized Zend Oauth access token object
        $flink->credentials = $ACCESS_TOKEN;
        $flink->created     = common_sql_now();

        // all sync off
        $flink->set_flags(false, false, false, false);

        $flink_id = $flink->insert();

        if (empty($flink_id)) {
            common_log_db_error($flink, 'INSERT', __FILE__);
            $this->serverError(_m('Couldn\'t link your Google account.'));
        }

        return $flink_id;
    }
}

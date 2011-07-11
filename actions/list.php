<?php
if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for listing out Google Docs for selection
 *
 * List out Google Docs for user selection
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Soe Thiha <soe@soe.im>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class GoogledocslistAction extends Action
{

    var $max_result = 30;
    
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
        
        $this->title = $this->arg('q');
        $this->start_index = $this->arg('p');
        
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
            
            // check the credentials in session first
            if(!$_SESSION['GOOGLEDOCS_ACCESS_TOKEN']) {
                $flink = Foreign_link::getByUserID($user->id, GOOGLEDOCS_SERVICE);
                if(isset($flink)) {
                    $_SESSION['GOOGLEDOCS_ACCESS_TOKEN'] = $flink->credentials; 
                }
                
                // check that the credentials are stored correctly
                // @fixme optional, need to verify if the credentials are still working
                if(!method_exists(unserialize($_SESSION['GOOGLEDOCS_ACCESS_TOKEN']), 'getToken')) {
                    // ask the user to authorize
                    $this->askAuthorization();
                    return;
                }
            }

            // @fixme - move the code below into a function
            $accessToken = unserialize($_SESSION['GOOGLEDOCS_ACCESS_TOKEN']);

            $consumer = new GdataOauthClient();
            $httpClient = $accessToken->getHttpClient($consumer->getOauthOptions());
            $docsService = new Zend_Gdata_Docs($httpClient, '');

            // @fixme check for setting default arg
            if(!$this->start_index) $this->start_index = 1;
            
            // Retrieve user's list of Google Docs
            $query = 'https://docs.google.com/feeds/documents/private/full?v=2'
                        .'&max-results='.$this->max_result
                        .'&start-index='.$this->start_index; 
                              
            if($this->title) $query .= '&title='.$this->title;
            
            $docs = $docsService->getDocumentListFeed($query);
            
            $total = intval($docs->getTotalResults()->getText());
            $i = $this->max_result * ($this->start_index - 1);
            
            // check for totalresults against the start_index
            if(ceil($total / $this->max_result) < $this->start_index)
                return;
            
            /*header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending a notice.
            $this->element('title', null, _('Google Docs Listing'));
            $this->elementEnd('head');
            $this->elementStart('body');*/
            
            $output = '';
    
            foreach ($docs as $doc) {
                $doc->id = str_replace('https://docs.google.com/feeds/documents/private/full/', '', $doc->id);
                $_doc = explode('%', $doc->id); 
                                
                $alternateLink = '';
                foreach ($doc->link as $link) {
                    if ($link->getRel() == 'alternate') {
                        $alternateLink = $link->getHref();
                    }
                }
                
                $output .= '<li>';
                $output .= '<input class="'.$i.'" name="doc" type="checkbox" value="'.$doc->id.'" />';
                $output .= '<span class="filetype '.$_doc[0].'"></span>';
                $output .= '<a href="'.$alternateLink.'" title="'.$doc->title.'" target="_new">'.$doc->title.'</a>';
                $output .= '</li>';
                
                $i++;       
            }
            
            // to signal load more button to stop operation
            if($i >= $total) $output .= '<span class="stop_load_more" style="display: none;">stop</span>';
            
            echo $output;
            /*$this->raw($output);
            $this->elementEnd('body');
            $this->elementEnd('html');*/
        }
    }
    
    function askAuthorization()
    {
        echo 'unauthorized';
    }
}
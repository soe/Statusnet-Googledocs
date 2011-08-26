<?php
if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for 'getting' a Google doc
 *
 * Get a Google doc - set ACL then redirect
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Soe Thiha <soe@soe.im>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class GoogledocsgetAction extends Action
{

    var $fileid = null;
    var $file = null;
    var $notice = null;
    var $f2p = null;
    var $curProfile = null;
    
    function prepare($args)
    {
        parent::prepare($args);
        
        // @fixme - maybe it is more efficient to pass fileid directly from redirection
        $this->fileid = $this->trimmed('fileid');        
        $this->file = File::staticGet('url', common_local_url('').'/googledocs/get/'.str_replace(':', '%3A', $this->fileid));

        if (empty($this->file)) {
            $this->clientError(_('No such file.'), 404);
        }
        
        // get f2p for postid
        $this->f2p = File_to_post::staticGet('file_id', $this->file->id);
        
        // get notice to check for scope
        $this->notice = Notice::staticGet('id', $this->f2p->post_id);

        if (empty($this->notice)) {
            // Did we used to have it, and it got deleted?
            $deleted = Deleted_notice::staticGet($id);
            if (!empty($deleted)) {
                // TRANS: Client error displayed trying to show a deleted notice.
                $this->clientError(_('Notice deleted.'), 410);
            } else {
                // TRANS: Client error displayed trying to show a non-existing notice.
                $this->clientError(_('No such notice.'), 404);
            }
        }
    
        $cur = common_current_user();

        if (!empty($cur)) {
            $this->curProfile = $cur->getProfile();
        }

        // checking scope for notice in regard to current user
        if (!$this->notice->inScope($this->curProfile)) {
            // TRANS: Client exception thrown when trying a view a notice the user has no access to.
            throw new ClientException(_('Not available.'), 403);
        }

        return true;
    }

    /**
     * Is this action read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */
    function title()
    {
        return '';
    }

    
    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }


    function showPage()
    {
        if($user = common_current_user()) {
            // get the notice owner's access token
            $flink = Foreign_link::getByUserID($this->notice->profile_id, GOOGLEDOCS_SERVICE);
            
            if(isset($flink) && method_exists(unserialize($flink->credentials), 'getToken')) {
            
                $accessToken = unserialize($flink->credentials);
                $consumer = new GdataOauthClient();
                $httpClient = $accessToken->getHttpClient($consumer->getOauthOptions());
                $docsService = new Zend_Gdata_Docs($httpClient, '');
                echo GoogleDocsPlugin::getAccessToken($user->id);
                print_r($_SESSION);
                if(GoogleDocsPlugin::getAccessToken($user->id)) { 
                    // set ACL for authenticated user
                    $acls = $docsService->get('https://docs.google.com/feeds/acl/private/full/'.$this->fileid.'?v=2')->getBody();
                    // @fixme - do proper xml parsing check
                    preg_match('/'.$_SESSION['GOOGLEDOCS_EMAIL'].'/', $acls, $matches);
                    print_r($matches);
                    
                    if(!$matches) {
                        $access = $this->grantAccess($docsService, $_SESSION['GOOGLEDOCS_EMAIL']);
                    }
                    echo $_SESSION['GOOGLEDOCS_EMAIL'];
                    // then show the google doc view link
                    $url = 'https://docs.google.com/feeds/documents/private/full/'.str_replace(':', '%3A', $this->fileid);
                    $doc = $docsService->getDocumentListEntry($url);

                    $alternateLink = '';
                    foreach ($doc->link as $link) {
                        if ($link->getRel() == 'alternate') {
                            $alternateLink = $link->getHref();
                        }
                    }
                    echo $alternateLink;
                      
                                              
                } else {
                    // show link to download
                    $f = explode(':', $this->fileid);
                    
                    if($f[0] == 'presentation') {
                        $formats = array('xls' => _m('Microsoft Excel Format'),
                                            'csv' => _m('CSV Format'),
                                            'odt' => _m('Open Spreadsheet Format'),
                                            'pdf' => _m('PDF Format'),
                                            'tsv' => _m('Tab Separated Format'),
                                            'rtf' => _m('Rich Format'),
                                            'html' => _m('HTML Format'));
                    } else if($f[0] == 'spreadsheet') {
                        $formats = array('ppt' => _m('Powerpoint Format'),
                                            'pdf' => _m('PDF Format'),
                                            'png' => _m('PNG Image Format'),
                                            'txt' => _m('TXT File'),
                                            'swf' => _m('Flash Format'));
                    } else {
                        $formats = array('doc' => _m('Microsoft Word Format'),
                                            'html' => _m('HTML Format'),
                                            'odt' => _m('Open Document Format'),
                                            'pdf' => _m('PDF Format'),
                                            'png' => _m('PNG Image Format'),
                                            'rtf' => _m('Rich Format'),
                                            'txt' => _m('TXT File'),
                                            'zip' => _m('Zip Archive'));
                    }                        
                                
                    $this->element('p', '', _m('Download the GoogleDocs file. You would not be updated with changes made to the file.'));
                    
                    $this->elementStart('p');
                    foreach($formats as $k => $v) {
                        $this->element('a', array('href'  => common_local_url('').'/googledocs/download/'.$this->fileid.'/'.$k, 'title' => $v, 'class' => 'button download'), _m('Download').' '.$v);
                        $this->raw('<br />');
                    }                    
                    $this->elementEnd('p');
                    
                    // then ask non-authenticated user to authenticate and brief about benefits
                    $this->element('p', '', _m('To get constant access to the GoogleDocs file, please authenticate with your Google account.'));
                    $this->element('p', '', _m('Authenticate button'));
                }

            } else {
                // the owner has no accessToken!
                // @fixme - inform owner?
                $this->clientError(_('No such file.'), 404);
            }
        } else {
            // prompt guest user to login @fixme - should allow guest user to access?
            $this->element('p', '', _m('Please login to access the GoogleDocs file.'));
        }
        
        
    }
    
    // to grant access for google authenticated users
    function grantAccess($service, $email)
    {
        $data = "
            <entry xmlns='http://www.w3.org/2005/Atom' xmlns:gAcl='http://schemas.google.com/acl/2007'>
              <category scheme='http://schemas.google.com/g/2005#kind'     
                term='http://schemas.google.com/acl/2007#accessRule'/>
              <gAcl:role value='writer'/>
              <gAcl:scope type='user' value='".$email."'/>
            </entry>
        ";

        try {
            $access = $service->post(trim($data), 'https://docs.google.com/feeds/acl/private/full/'.$this->fileid);
            //common_log(LOG_INFO, $access);
            return $access;
        } catch (Exception $e) { // @fixme - is it the right way of handling error?
            //common_log(LOG_INFO, $e);
            $this->clientError(_('Error encountered.'), 407);
            return False;
        }
    }
}
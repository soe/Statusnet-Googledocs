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

    function prepare($args)
    {
        parent::prepare($args);
        
        $this->fileid = $this->trimmed('fileid');

        // @fixme check for file stored in the database
        /*if ($id = $this->trimmed('attachment')) {
            $this->attachment = File::staticGet($id);
        }

        if (empty($this->attachment)) {
            // TRANS: Client error displayed trying to get a non-existing attachment.
            $this->clientError(_('No such attachment.'), 404);
            return false;
        }*/
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

        $this->showPage(); exit;
        if(!isset($_SESSION['GOOGLEDOCS_ACCESS_TOKEN'])) {
            // ask for authentication
            $this->showPage();
            
        } else {
            // set ACL then redirect
            echo $this->fileid;
            //common_redirect($this->attachment->url, 303);
        }
    }


    function showContent()
    {
        $this->element('p', '', _m('Coming soon'));
        
        $link = 'https://docs.google.com/feeds/documents/private/full/'.str_replace(':', '%3A0', $this->fileid);
        $this->elementStart('p');
        $this->raw('This should be processing the following Google Docs ID: <div class="code" style="font-family: courier, monospace; font-size: 82%;">'.$link.'</div>');
        $this->elementEnd('p');
    }


    function showPageNoticeBlock()
    {
    }

    function showSections() 
    {

    }
}
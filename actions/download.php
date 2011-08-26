<?php
if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for 'downloading' a Google doc in various format
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
class GoogledocsdownloadAction extends GoogledocsgetAction
{
    function prepare($args)
    {
        parent::prepare($args);
        
        $this->format = $this->trimmed('format');
        
        $flink = Foreign_link::getByUserID($this->notice->profile_id, GOOGLEDOCS_SERVICE);
        
        if(isset($flink) && method_exists(unserialize($flink->credentials), 'getToken')) {
            $accessToken = unserialize($flink->credentials);
        
            $consumer = new GdataOauthClient();
            $httpClient = $accessToken->getHttpClient($consumer->getOauthOptions());
            $docsService = new Zend_Gdata_Docs($httpClient, '');

            $url = 'https://docs.google.com/feeds/documents/private/full/'.str_replace(':', '%3A', $this->fileid);
            $doc = $docsService->getDocumentListEntry($url);

            $f = explode(':', $this->fileid);

            if($f[0] == 'presentation')
                $url = 'https://docs.google.com/feeds/download/presentations/Export?docId='.$f[1].'&exportFormat='.$this->format;
            else if($f[0] == 'spreadsheet')
                $url = 'https://spreadsheets.google.com/feeds/download/spreadsheets/Export?key='.$f[1].'&exportFormat='.$this->format;
            else
                $url = 'https://docs.google.com/feeds/download/documents/Export?docID='.$f[1].'&exportFormat='.$this->format;
  
            $file = $docsService->get($url)->getBody();
            header("Cache-Control: no-cache private");
            header("Content-Description: File Transfer");
            header('Content-disposition: attachment; filename="'.$doc->title.'.'.$this->format.'"');
            // @fixme - corresponding content type
            header("Content-Type: application/text");
            header("Content-Transfer-Encoding: binary");
            header('Content-Length: '. strlen($file));
            echo $file;
            
            return;
        } else {
            // the owner has no accessToken!
            // @fixme - inform owner?
            $this->clientError(_('No such file.'), 404);
            
            return;
        }
        
    }

}
?>
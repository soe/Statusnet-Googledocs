<?php

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

// a wrapper around MediaFile
class GoogledocsFile extends MediaFile
{

    var $filename = null;
    var $fileid = null;
    var $mimetype = null;
    
    function __construct($user = null, $filename = null, $fileid = null, $mimetype = null)
    {
        if ($user == null) {
            $this->user = common_current_user();
        } else {
            $this->user = $user;
        }

        $this->filename   = $filename;
        $this->fileid     = $fileid;
        $this->mimetype   = 'google/'.$mimetype; // @fixme non-standard mimetype!!!
        $this->fileRecord = $this->storeFile();
        // @fixme support thumbnail? for now, NO :)
        $this->thumbnailRecord = '';

        // @fixme still use attachment?
        $this->fileurl = common_local_url('attachment',
                                    array('attachment' => $this->fileRecord->id));

        $this->maybeAddRedir($this->fileRecord->id, $this->fileurl);
        $this->short_fileurl = common_shorten_url($this->fileurl);
        $this->maybeAddRedir($this->fileRecord->id, $this->short_fileurl);
    }
    
    // just delete the database entry
    function delete()
    {
        /*$filepath = File::path($this->filename);
        @unlink($filepath);*/
    }

    // no need to store file for google docs but need to create file entry, and a link
    function storeFile() {

        $file = new File;

        $file->filename = $this->filename;
        $file->title = $this->filename;

        $file->url      = common_local_url('').'/googledocs/get/'.$this->fileid;
        // @fixme - fix common_local_url mapping
        //$file->url      = common_local_url('googledocs/get', array('fileid' => 'fakeid'));
        $file->size     = ''; // @fixme support file size? it is everchanging
        $file->date     = time();
        $file->mimetype = $this->mimetype;     
        $file->protected = 1; // @fixme basic protection

        $file_id = $file->insert();

        if (!$file_id) {
            common_log_db_error($file, "INSERT", __FILE__);
            // TRANS: Client exception thrown when a database error was thrown during a file upload operation.
            throw new ClientException(_('There was a database error while saving your file. Please try again.'));
        }

        return $file;
    }
    
    function rememberFile($file, $short)
    {
        $this->maybeAddRedir($file->id, $short);
    }

    function maybeAddRedir($file_id, $url)
    {
        $file_redir = File_redirection::staticGet('url', $url);

        if (empty($file_redir)) {

            $file_redir = new File_redirection;
            $file_redir->url = $url;
            $file_redir->file_id = $file_id;

            $result = $file_redir->insert();

            if (!$result) {
                common_log_db_error($file_redir, "INSERT", __FILE__);
                // TRANS: Client exception thrown when a database error was thrown during a file upload operation.
                throw new ClientException(_('There was a database error while saving your file. Please try again.'));
            }
        }
    }
    
}

?>
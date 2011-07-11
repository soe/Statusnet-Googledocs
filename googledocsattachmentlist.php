<?php
if (!defined('STATUSNET')) {
    exit(1);
}

// @fixme - copied off from InlineAttachmentList 
// maybe extending the AttachmentList would be more efficient?
class GoogledocsAttachmentList extends InlineAttachmentList
{
    function showListStart()
    {
        $this->out->elementStart('div', array('class' => 'entry-content google_docs_list'));
        $this->out->elementStart('ul');
    }

    function showListEnd()
    {
        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');
    }

    /**
     * returns a new list item for the current attachment
     *
     * @param File $notice the current attachment
     *
     * @return ListItem a list item for displaying the attachment
     */
    function newListItem($attachment)
    {
        return new GoogledocsAttachmentListItem($attachment, $this->out);
    }
}

class GoogledocsAttachmentListItem extends AttachmentListItem
{
    protected $thumb;

    function show()
    {
        //$this->thumb = parent::getThumbInfo();
        //if (!empty($this->thumb)) {
        // a workaround way to just show google files
        if(!(strpos($this->attachment->mimetype, 'google') === False))
            parent::show();
        //}

    }

    function getThumbInfo()
    {
        return ''; //$this->thumb;
    }

    function showLink() {
        $this->out->elementStart('a', $this->linkAttr());
        //$this->showRepresentation();
        $this->out->raw($this->attachment->filename);
        $this->out->elementEnd('a');
    }

    function linkAttr()
    {
        $attr = parent::linkAttr();
        $attr['target'] = '_blank';
        return $attr;
    }
    
    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'googledocs_doc  '.str_replace('google/', '', $this->attachment->mimetype)));
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */
    function showEnd()
    {
        $this->out->elementEnd('li');
    }
}

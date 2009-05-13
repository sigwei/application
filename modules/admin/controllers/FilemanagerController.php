<?php
/**
 * Index Controller for all actions dealing with encryption and signatures
 *
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Application
 * @package     Module_Admin
 * @author      Oliver Marahrens <o.marahrens@tu-harburg.de>
 * @copyright   Copyright (c) 2009, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

class Admin_FilemanagerController extends Zend_Controller_Action
{
    /**
     * Do some initialization on startup of every action
     *
     * @return void
     */
    public function init()
    {
        $this->_redirector = $this->_helper->getHelper('Redirector');
    }

	/**
	 * Just to be there. No actions taken.
	 *
	 * @return void
	 *
	 */
    public function indexAction()
    {
        $this->view->title = $this->view->translate('admin_filemanager_index');

        $data = $this->_request->getPost();

        $gpg = new Opus_GPG();

        if (true === array_key_exists('FileObject', $data))
        {
            $e = null;
            try {
                $gpg->signPublicationFile(unserialize(base64_decode($data['FileObject'])), $data['password']);
            }
            catch (Exception $e) {
                $this->view->actionresult = $e->getMessage();
            }
            if ($e === null) {
                $this->view->actionresult = 'Successfully signed file!';
            }
        }

        $requestData = $this->_request->getParams();

    	$this->view->noFileSelected = false;

    	if (true === array_key_exists('docId', $requestData))
    	{
            $uploadForm = new FileUpload();
            $action_url = $this->view->url(array('controller' => 'filemanager', 'action' => 'index'));
            $uploadForm->setAction($action_url);
            // store uploaded data in application temp dir
            if (true === array_key_exists('submit', $data)) {
                if ($uploadForm->isValid($data) === true) {
                    // This works only from Zend 1.7 on
                    // $upload = $uploadForm->getTransferAdapter();
                    $upload = new Zend_File_Transfer_Adapter_Http();
                    $files = $upload->getFileInfo();
                    // TODO: Validate document id, error message on fail
                    $documentId = $requestData['docId'];
                    $document = new Opus_Document($documentId);

                    // save each file
                    foreach ($files as $file) {
                        /* TODO: Uncaught exception 'Zend_File_Transfer_Exception' with message '"fileupload" not found by file transfer adapter
                        * if (!$upload->isValid($file)) {
                        *    $this->view->message = 'Upload failed: Not a valid file!';
                        *    break;
                        * }
                        */
                        $docfile = $document->addFile();
                        $docfile->setDocumentId($document->getId());
                        $docfile->setLabel($uploadForm->getValue('comment'));
                        $docfile->setLanguage($uploadForm->getValue('language'));
                        $docfile->setPathName($file['name']);
                        $docfile->setMimeType($file['type']);
                        $docfile->setTempFile($file['tmp_name']);
                        $docfile->setFromPost($file);
                    }
                    $e = null;
                    try {
                        $document->store();
                    }
                    catch (Exception $e) {
                        $this->view->actionresult = 'Upload NOT succussful - please check write permissions for file directory!';
                    }
                    if ($e === null) {
                        $this->view->actionresult = 'Upload succussful!';
                    }

                    // reset input values fo re-displaying
                    $uploadForm->reset();
                    // re-insert document id
                    $uploadForm->DocumentId->setValue($document->getId());
                }
                else {
                    // invalid form, populate with transmitted data
                    $uploadForm->populate($data);
                    $this->view->form = $uploadForm;
                }

            }
            $this->view->uploadform = $uploadForm;
    	    try {
    	        $doc = new Opus_Document($requestData['docId']);
    	        $this->view->files = array();
                $this->view->verifyResult = array();

                $index = 0;

                foreach ($doc->getFile() as $file)
                {
                    $form = new SignatureForm();
                    $form->FileObject->setValue(base64_encode(serialize($file)));
                    $form->setAction($this->view->url(array('module' => 'admin', 'controller' => 'filemanager', 'action' => 'index', 'docId' => $requestData['docId']), null, true));

                    $this->view->files[$index]['path'] = $file->getPathName();
                    $this->view->files[$index]['form'] = $form;

                    try {
                        $this->view->verifyResult[$file->getPathName()] = $gpg->verifyPublicationFile($file);
                    }
                    catch (Exception $e) {
                        $this->view->verifyResult[$file->getPathName()] = array(array($e->getMessage()));
                    }
                    $index++;
                }
                if ($index === 0) {
                    $this->view->actionresult = $this->view->translate('admin_filemanager_nofiles');
                }
    	    }
    	    catch (Exception $e) {
    	    	$this->view->noFileSelected = true;
    	    }
    	}
    	else
    	{
    		$this->view->noFileSelected = true;
    	}
    }
}

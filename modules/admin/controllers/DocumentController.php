<?php
/*
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
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2008-2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

/**
 * Controller for showing and editing a document in the administration.
 */
class Admin_DocumentController extends Controller_Action {

    // TODO move to documenthelper (or configuration file)
    private $sections = array(
        'general',
        'titles',
        'abstracts',
        'persons',
        'dates',
        'identifiers',
        'references',
        'licences',
        'subjects',
        'collections',
        'thesis',
        'other',
        'patents',
        'notes',
        'enrichments'
    );

    private $sectionModel = array(
        'titles' => 'Opus_Title',
        'abstracts' => 'Opus_TitleAbstract',
        'identifiers' => 'Opus_Identifier',
        'references' => 'Opus_References',
        'subjects' => 'Opus_Subject',
        'patents' => 'Opus_Patent',
        'notes' => 'Opus_Note',
        'enrichments' => 'Opus_Enrichment'
    );

    private $sectionField = array(
        'persons' => 'Person'
    );

    /**
     * Returns a filtered representation of the document.
     *
     * @param  Opus_Document  $document The document to be filtered.
     * @return Opus_Model_Filter The filtered document.
     */
    private function __createFilter(Opus_Document $document, $page = null) {
        $filter = new Opus_Model_Filter();
        $filter->setModel($document);
        $blacklist = array('Collection', 'IdentifierOpus3', 'Source', 'File',
            'ServerState', 'ServerDatePublished', 'ServerDateModified',
            'ServerDateUnlocking', 'Type', 'PublicationState');
        $filter->setBlacklist($blacklist);
        // $filter->setSortOrder($type->getAdminFormSortOrder());
        return $filter;
    }

    public function indexAction() {
        $id = $this->getRequest()->getParam('id');

        if (!empty($id) && is_numeric($id)) {
            $model = new Opus_Document($id);

            $filter = new Opus_Model_Filter();
            $filter->setModel($model);
            $blacklist = array('PublicationState');
            $filter->setBlacklist($blacklist);

            $this->view->document = $model;
            $this->view->entry = $filter->toArray();
            $this->view->objectId = $id;

            $this->view->overviewHelper = new Admin_Model_DocumentHelper($model);

            if (!empty($model)) {
                $this->view->docHelper = new Review_Model_DocumentAdapter(
                        $this->view, $model);
            }

            $this->prepareActionLinks($this->view->docHelper);

            $this->prepareEditLinks();

            return $model;
        }
        else {
            // missing or bad parameter => go back to main page
            $this->_redirectTo('index', null, 'documents', 'admin');
        }
    }

    public function editAction() {
        $id = $this->getRequest()->getParam('id');

        $section = $this->getRequest()->getParam('section');

        if (!empty($section) && !empty($id) && is_numeric($id)) {
            $model = new Opus_Document($id);

            $this->view->docHelper = new Review_Model_DocumentAdapter($this->view, $model);
            $this->view->addForm = $this->getAddForm($model, $section);
            $this->view->editForm = $this->getEditForm($model, $section);

            return $this->renderScript('document/edit' /* . ucfirst($section) */ . '.phtml');
        }

        $this->_redirectTo('index');
    }

    /**
     * Create new model and add to document.
     */
    public function createAction() {
        $id = $this->getRequest()->getParam('id');
        $section = $this->getRequest()->getParam('section');

        if ($this->getRequest()->isPost()) {
            $postData = $this->getRequest()->getPost();

            $document = new Opus_Document($id);

            foreach ($postData as $modelClass => $fields) {
                $model = new $modelClass;
                foreach ($fields as $name => $value) {
                    // TODO filter buttons
                    $field = $model->getField($name);
                    if (!empty($field)) {
                        $field->setValue($value);
                    }
                }

                // TODO implement for other models
                $document->addIdentifier($model);

                $document->store();
            }

            $this->_redirectTo('edit', null, 'document', 'admin', array(
                'id' => $id,
                'section' => $section
            ));
        }
        else {
            // TODO What if there is no $id?
            $this->_redirectTo('index', null, 'document', 'admin', array(
                'id' => $id
            ));
        }
    }

    public function updateAction() {
        $id = $this->getRequest()->getParam('id');
        $section = $this->getRequest()->getParam('section');

        if ($this->getRequest()->isPost()) {
            $postData = $this->getRequest()->getPost();

            // TODO get models from document and update
            // TODO see form builder on finding models (just by index?)
        }
        else {
            
        }
    }

    /**
     * Prepares URLs for action links, e.g frontdoor, delete, publish.
     *
     *
     */
    public function prepareActionLinks($docHelper) {
        $actions = array();
        
        $action = array();
        $action['label'] = 'admin_documents_open_frontdoor';
        $action['url'] = $docHelper->getUrlFrontdoor();
        $actions['frontdoor'] = $action;
        
        // TODO should always be shown, or?
        if ($docHelper->hasFiles()) {
            $action = array();
            $action['label'] = 'admin_document_files';
            $action['url'] = $docHelper->getUrlFileManager();
            $actions['files'] = $action;
        }

        // TODO implement docHelper method
        $action = array();
        $action['label'] = 'admin_document_access';
        $action['url'] = $docHelper->getUrlAccessManager();
        $actions['access'] = $action;
        
        if ($docHelper->getDocState() === 'unpublished') {
            $action = array();
            $action['label'] = 'admin_doc_delete';
            $action['url'] = $docHelper->getUrlDelete();
            $actions['delete'] = $action;
            
            $action = array();
            $action['label'] = 'admin_documents_publish';
            $action['url'] = $docHelper->getUrlPublish();
            $actions['publish'] = $action;
        }
        elseif ($docHelper->getDocState() === 'published') {
            $action = array();
            $action['label'] = 'admin_doc_delete';
            $action['url'] = $docHelper->getUrlDelete();
            $actions['delete'] = $action;

            $action = array();
            $action['label'] = 'admin_documents_unpublish';
            $action['url'] = $docHelper->getUrlUnpublish();
            $actions['unpublish'] = $action;
        }
        elseif ($this->docHelper->getDocState() === 'deleted') {
            $action = array();
            $action['label'] = 'admin_doc_undelete';
            $action['url'] = $docHelper->getUrlPublish();
            $actions['publish'] = $action;

            $action = array();
            $action['label'] = 'admin_doc_delete_permanent';
            $action['url'] = $docHelper->getUrlPermanentDelete();
            $actions['permanentDelete'] = $action;
        }

        $this->view->actions = $actions;

        return $actions;
    }

    public function prepareEditLinks() {
        $editUrls = array();
        $editLabels = array();

        foreach ($this->sections as $section) {
            $editUrls[$section] = $this->view->url(array(
                'module' => 'admin',
                'controller' => 'document',
                'action' => 'edit',
                'section' => $section
            ), 'default', false);
            $editLabels[$section] = $this->view->translate('admin_document_edit_section');
        }

        $this->view->editUrls = $editUrls;
        $this->view->editLabels = $editLabels;
    }

    public function getAddForm($model, $section) {
        $form = null;

        $id = $model->getId();

        $includedFields = Admin_Model_DocumentHelper::getFieldNamesForGroup($section);

        if (isset($this->sectionModel[$section])) {
            $sectionModel = $this->sectionModel[$section];
        }
        if (isset($this->sectionField[$section])) {
            $sectionField = $this->sectionField[$section];
        }

        if (!empty($sectionModel)) {
            $addForm = new Admin_Form_Model($sectionModel);
        }
        elseif (!empty($sectionField)) {
            $temp = new Opus_Document();
            $field = $temp->getField($sectionField);
            $addForm = new Admin_Form_Model($temp->getField($sectionField));
        }
        else {
            $addForm = null;
        }

        if (!empty($addForm)) {
            $hiddenDocId = new Zend_Form_Element_Hidden('docid');
            $hiddenDocId->setValue($id);

            $addForm->addElement($hiddenDocId);

            $submit = new Zend_Form_Element_Submit('submit_add');
            $submit->setLabel('Add'); // TODO

            $addForm->addElement($submit);

            $addForm->removeDecorator('Fieldset');

            $form = new Zend_Form('AddMetadata');

            $addUrl = $this->view->url(array(
                'action' => 'create',
                'id' => $id,
                'section' => $section
            ));
            $form->setAction($addUrl);
            
            if (!empty($sectionModel)) {
                $form->addSubForm($addForm, $sectionModel);
            }
            elseif (!empty($field)) {
                $form->addSubForm($addForm, $field->getValueModelClass());
            }
            else {
                // TODO take care of this case
            }
        }

        return $form;
    }

    public function getEditForm($model, $section) {
        $includedFields = Admin_Model_DocumentHelper::getFieldNamesForGroup($section);

        $form = new Zend_Form('edit');

        switch ($section) {
            case 'general':
            case 'misc':
            case 'other':
            case 'dates':
                $subform = new Admin_Form_Model($model, $includedFields);
                $subform->populateFromModel($model);
                $form->addSubForm($subform, 'general');
                break;

            default:
                foreach ($includedFields as $index => $fieldName) {
                    $field = $model->getField($fieldName);

                    $values = $field->getValue();

                    if (is_array($values)) {
                        foreach ($values as $index2 => $value) {
                            $subform = new Admin_Form_Model($field);
                            $subform->populateFromModel($value);
                            $form->addSubForm($subform, $index . '-' . $value->getId());
                        }
                    }
                }
                break;
        }
        
        $updateUrl = $this->view->url(array(
            'action' => 'update',
            'id' => $model->getId(),
            'section'=> $section
        ));

        $form->setAction($updateUrl);

        $submit = new Zend_Form_Element_Submit('save');
        $submit->setLabel('Save');
        $form->addElement($submit);

        $cancel = new Zend_Form_Element_Submit('cancel');
        $cancel->setLabel('Cancel');
        $form->addElement($cancel);

        $reset = new Zend_Form_Element_Reset('reset');
        $reset->setLabel('Reset');
        $form->addElement($reset);
        
        return $form;
    }

}

?>

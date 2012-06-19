<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the North Rhine-Westphalian Library Service Center,
 * the Cooperative Library Network Berlin-Brandenburg, the Saarland University
 * and State Library, the Saxon State Library - Dresden State and University
 * Library, the Bielefeld University Library and the University Library of
 * Hamburg University of Technology with funding from the German Research
 * Foundation and the European Regional Development Fund.
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
 * @author      Henning Gerhardt (henning.gerhardt@slub-dresden.de)
 * @author      Oliver Marahrens <o.marahrens@tu-harburg.de>
 * @copyright   Copyright (c) 2009, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

/**
 * Administrative work with document metadata.
 */
class Admin_DocumentsController extends Controller_CRUDAction {

    /**
     * The class of the model being administrated.
     *
     * @var Opus_Model_Abstract
     */
    protected $_modelclass = 'Opus_Document';

    protected $sortingOptions = array('id', 'title', 'author',
        'publicationDate', 'docType');

    protected $docOptions = array(/* 'all', */ 'unpublished', 'inprogress', 'audited', 'published', 'restricted', 'deleted');

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
            'Type', 'PublicationState');
        $filter->setBlacklist($blacklist);
        // $filter->setSortOrder($type->getAdminFormSortOrder());
        return $filter;
    }

    public function init() {
        parent::init();

        $config = Zend_Registry::get("Zend_Config");

        if (isset($config->admin->documents->linkToAuthorSearch)) {
            $this->view->linkToAuthorSearch = $config->admin->documents->linkToAuthorSearch;
        }
        else {
            $this->view->linkToAuthorSearch = 0;
        }
    }

    /**
     * Display documents (all or filtered by state)
     *
     * @return void
     */
    public function indexAction() {
    	$this->view->title = $this->view->translate('admin_documents_index');

        $this->_prepareDocStateLinks();

        $url_call_id = array(
            'module' => 'admin',
            'controller' => 'document',
            'action' => 'index'
        );
        $this->view->url_call_id = $this->view->url($url_call_id, 'default', true);

        $this->_prepareSortingLinks();

        $data = $this->_request->getParams();
        $filter = $this->_getParam("filter");
        $this->view->filter = $filter;
        $data = $this->_request->getParams();

        $page = 1;
        if (array_key_exists('page', $data)) {
            // set page if requested
            $page = $data['page'];
        }

        $collectionId = null;
        if (array_key_exists('collectionid', $data)) {
            $collectionId = $data['collectionid'];
        }

        $seriesId = null;
        if (array_key_exists('seriesid', $data)) {
            $seriesId = $data['seriesid'];
        }

        // Default Ordering...
        $sort_reverse = '0';
        if (true === array_key_exists('sort_reverse', $data)) {
           $sort_reverse = $data['sort_reverse'];
        }
        $this->view->sort_reverse = $sort_reverse;
        $this->view->sortDirection = ($sort_reverse) ? 'descending' : 'ascending';

        $config = Zend_Registry::get('Zend_Config');

        $state = 'unpublished';

        if (true === array_key_exists('state', $data)) {
            $state = $data['state'];
        }
        else if (isset($config->admin->documents->defaultview)) {
            $state = $config->admin->documents->defaultview;
        }

        if (!empty($state) && !in_array($state, $this->docOptions)) {
            $state = 'unpublished';
        }

        $this->view->state = $state;

        $sort_order = 'id';
        if (true === array_key_exists('sort_order', $data)) {
            $sort_order = $data['sort_order'];
        }

        $this->view->sort_order = $sort_order;

        if (!empty($collectionId)) {
            $collection = new Opus_Collection($collectionId);
            $result = $collection->getDocumentIds();
            $this->view->collection = $collection;
            if ($collection->isRoot()) {
                $collectionRoleName = 'default_collection_role_' . $collection->getRole()->getDisplayName();
                $this->view->collectionName = $this->view->translate($collectionRoleName);
                if ($this->view->collectionName == $collectionRoleName) {
                    $this->view->collectionName = $collection->getRole()->getDisplayName();
                }
            }
            else {
                $this->view->collectionName = $collection->getDisplayName();
            }
        }
        else if (!empty($seriesId)) {
            $series = new Opus_Series($seriesId);
            $this->view->series = $series;
            $result = $series->getDocumentIdsSortedBySortKey();
        }
        else {
            $result = $this->_helper->documents($sort_order, $sort_reverse, $state);
        }

        $paginator = Zend_Paginator::factory($result);
        if (array_key_exists('hitsPerPage', $data)) {
        	if ($data['hitsPerPage'] === '0') {
        	    $hitsPerPage = '10000';
        	}
            else {
            	$hitsPerPage = $data['hitsPerPage'];
            }
            $paginator->setItemCountPerPage($hitsPerPage);
        }
        $page = 1;
        if (array_key_exists('page', $data)) {
            // paginator
            $page = $data['page'];
        }
        $paginator->setCurrentPageNumber($page);
        $this->view->paginator = $paginator;
    }

    protected function _prepareDocStateLinks() {
        $registers = array();

        foreach ($this->docOptions as $name) {
            $params = array('module' => 'admin', 'controller'=>'documents', 'action'=>'index');
            if ($name !== 'all') {
                $params['state'] = $name;
            }
            $url = $this->view->url($params, null, true);
            $registers[$name] = $url;
        }

        $this->view->registers = $registers;
    }

    protected function _prepareSortingLinks() {
        $sortingLinks = array();

        foreach ($this->sortingOptions as $name) {
            $params = array(
                'module' => 'admin',
                'controller' => 'documents',
                'action' => 'index',
                'sort_order' => $name
            );
            $sortUrl = $this->view->url($params, 'default', false);
            $sortingLinks[$name] = $sortUrl;
        }

        $this->view->sortingLinks = $sortingLinks;

        $directionLinks = array();

        $directionLinks['ascending'] = $this->view->url(array('sort_reverse' => '0'), 'default', false);
        $directionLinks['descending'] = $this->view->url(array('sort_reverse' => '1'), 'default', false);

        $this->view->directionLinks = $directionLinks;
    }

}

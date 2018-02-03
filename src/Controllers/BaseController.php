<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 24-1-18
 * Time: 16:55
 */

namespace Djc\Phalcon\Controllers;

use Djc\Phalcon\Migrations\DatabaseInstaller;
use Djc\Phalcon\Models\BaseModel;
use Phalcon\Exception;
use Phalcon\Mvc\Controller;

class BaseController extends Controller
{

    // Protected parameters, set in initialize or onConstruct function
    /**
     * @var array $_headers Read all headers into the var for later use
     */
    protected $_headers = [];
    /**
     * @var BaseModel $_model constructed Model via initialize construction
     */
    protected $_model;
    /**
     * @var array $_postFields Read all postFields into this var for use in several functions
     */
    protected $_postFields = [];

    // Protected parameters, set in several functions
    protected $_filters = [];
    protected $_filter;

    protected $_store = [];
    protected $_responseArray = ['success' => false, 'data' => [], 'total' => 0, 'errrorMsg' => '', 'readTranslations' => false];


    public function initialize() {
        // Disable the views in the controller. Just return a json-formatted string
        $this->view->disable();

        try {
            // Read headers and check access
            $this->_headers = $this->request->getHeaders();
            if ($this->checkAccess() && !$this->request->get('checkAccess', null, false)) {
                if ($this->_headers['Authorization'] !== 'Bearer ' . $this->session->authToken) {
                    error_log('No Correct token supplied ');
                    exit(-1);
                }
            }

            // Check if database is installed
            $connection = $this->getDI()->get('db');
            if (!$connection->tableExists('baseUsers')) {
                // Install database with common modules
                $installer = new DatabaseInstaller();
                $installer->setModules($this->config->modules);
                if (!$installer->installDatabase()) {
                    throw new Exception('Database cannot be installed, call support');
                }
            }

            if (!$this->checkAccess()) {
                throw new \Exception('No access allowed to this function');
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw new Exception($e->getMessage());
        }

        $this->afterInitialize();

    }

    protected function afterInitialize() {
        if (isset($this->_postFields['order'])) {
            $this->_model->orderField = $this->_postFields['order']['field'];
            $this->_model->orderDirection = $this->_postFields['order']['direction'];
        }
    }

    protected function checkAccess() {
        return false;
    }

    public function makeFilter() {

    }

    public function storeAction() {

    }

    public function beforeStoreAction() {

    }

    public function afterStoreAction() {

    }

    public function dropdownAction() {

    }

    public function afterDropDownAction() {

    }

    public function createAction() {

    }

    public function afterCreateAction() {

    }

    public function updateAction() {

    }

    public function beforeUpdateAction() {

    }

    public function afterUpdateAction() {

    }

    public function deleteAction() {

    }

    public function afterDeleteAction() {

    }

    public function restoreAction() {

    }

    public function beforeRestoreAction() {

    }
}
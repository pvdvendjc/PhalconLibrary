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
use Djc\Phalcon\Utils;
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
     * @var \Djc\Phalcon\Models\BaseModel $_model constructed Model via initialize construction
     */
    protected $_model;
    /**
     * @var array $_postFields Read all postFields into this var for use in several functions
     */
    protected $_postFields = [];

    /**
     * @var \Djc\Phalcon\Services\AclService $_aclService
     */
    protected $_aclService;

    // Protected parameters, set in several functions
    protected $_filters = [];
    protected $_filter;
    protected $_runAsAdmin = false;
    protected $_store = [];
    protected $_responseArray = ['success' => false, 'data' => [], 'total' => 0, 'errorMsg' => '', 'readTranslations' => false];

    public $dateFormat = 'd-M-Y';
    public $timeFormat = 'H:i';
    public $dateTimeFormat;
    public $userLanguage = 'nl';

    public function initialize()
    {
        // Disable the views in the controller. Just return a json-formatted string
        $this->view->disable();

        $this->dateTimeFormat = $this->dateFormat . ' ' . $this->timeFormat;

        try {
            // Read headers and check access
            $this->_headers = $this->request->getHeaders();
            if ($this->checkAccess() && !$this->request->get('checkAccess', null, false)) {
                if ($this->_headers['Authorization'] !== 'Bearer ' . $this->session->authToken) {
                    throw new Exception('No correct token supplied');
                }
            }

            // Check if database is installed
            $connection = $this->getDI()->get('db');
            if (!$connection->tableExists('migrations')) {
                // Install database with common modules
                $installer = new DatabaseInstaller();
                $installer->setModules($this->config->modules);
                if (!$installer->installDatabase()) {
                    throw new Exception('Database cannot be installed, call support');
                }
            }

            if ($this->_runAsAdmin) {
                $this->loginAdmin();
            }

            // read parameters from request
            if ($this->request->isGet() || $this->request->isDelete()) {
                $this->_postFields = $this->request->get();
            }
            if ($this->request->isPost() || $this->request->isPut()) {
                $this->_postFields = $this->request->getJsonRawBody(true);
            }

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (array_key_exists('order', $this->_postFields)) {
            $this->_model->orderField = $this->_postFields['order']['field'];
            $this->_model->orderDirection = $this->_postFields['order']['direction'];
        }

        if (array_key_exists('listFields', $this->_postFields)) {
            $this->_model->setListFields($this->_postFields['listFields']);
        }

        if (array_key_exists('filters', $this->_postFields)) {
            $this->_filters = json_decode($this->_postFields['filters'], true);
        }

        $this->afterInitialize();
    }

    /**
     * Run this function after initializing the controller for extra initialization functions
     *
     */
    protected function afterInitialize()
    {

    }

    /**
     * Must be overriden in own project otherwise function won't work
     */
    protected function loginAdmin()
    {
        throw new Exception('Function LoginAdmin not implemented in application');
    }

    /**
     * Create function for checking access to several menu-paths
     * Override this function in local implementations and return true otherwise an Exception is thrown
     *
     * @return bool
     */
    protected function checkAccess()
    {
        return false;
    }

    public function makeFilter()
    {
        $bindArray = [];
        $filterString = '';
        foreach ($this->_filters as $filter) {
            if (strlen($filterString) > 0 && array_key_exists('whereClause', $filter)) {
                $filterString .= ' ' . strtoupper($filter['whereClause']) . ' ';
            } elseif (strlen($filterString) > 0) {
                $filterString .= ' AND ';
            }
            $addValue = true;
            $filterString .= $filter['field'];
            switch ($filter['operator']) {
                case 'eq':
                    $filterString .= '=';
                    break;
                case 'ne':
                    $filterString .= '<>';
                    break;
                case 'ge':
                    $filterString .= '>=';
                    break;
                case 'gt':
                    $filterString .= '>';
                    break;
                case 'le':
                    $filterString .= '<=';
                    break;
                case 'lt':
                    $filterString .= '<';
                    break;
                case 'IN':
                    $filterString .= ' IN({' . $filter['field'] . ':array})';
                    $addValue = false;
                    break;
            }
            if ($addValue !== false) {
                $filterString .= ':' . $filter['field'] . ':';
            }
            $bindArray[$filter['field']] = $filter['value'];
        }
        $this->_filter = [$filterString, 'bind' => $bindArray, 'order' => $this->_model->orderField . ' ' . $this->_model->orderDirection];
    }

    /**
     * Format Records (specially dateTimeFields) and get all related fields (if in model)
     *
     * @param array $records
     * @param boolean $getRelated
     * @return array
     */
    private function _formatRecords($records, $getRelated = true)
    {
        $returnRecords = [];
        foreach ($records as $recordKey => $record) {
            if (array_key_exists('returnRaw', $this->_postFields) && $this->_postFields['returnRaw'] == true) {
                foreach ($this->_model->getDateTimeFields() as $dateTimeField) {
                    $record->$dateTimeField = date($this->dateTimeFormat, $record->$dateTimeField);
                }
                foreach ($this->_model->getDateFields() as $dateField) {
                    $record->$dateField = date($this->dateFormat, $record->$dateField);
                }
            }
            foreach ($this->_model->getBoolFields() as $boolField) {
                $record->$boolField = $record->$boolField === '0' ? false : true;
            }
            $dataRecord = $this->_getDataRecord($record, $this->_model->getListFields($getRelated));
            $returnRecords[$recordKey] = $dataRecord;
        }
        return $returnRecords;
    }

    /**
     * Get all fields and related fields that are in the list
     *
     * @param BaseModel $record
     * @param array $fields
     * @return \stdClass
     */
    private function _getDataRecord($record, $fields)
    {
        $dataRecord = new \stdClass();
        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                if ($record->$key) {
                    $subRecord = $this->_getDataRecord($record->$key, $field);
                    $dataRecord->$key = $subRecord;
                }
            } else {
                if (method_exists($record, $field)) {
                    $dataRecord->$field = $record->$field();
                } elseif (in_array($field, $record->getJsonFields())) {
                    $dataRecord->$field = json_decode($record->$field);
                } else {
                    $dataRecord->$field = $record->$field;
                }
            }
        }
        return $dataRecord;
    }

    /**
     * Get the gridstore for displaying data, also a single record can be retrieved but that action is better to be done with the loadAction()
     *
     * @return string
     */
    public function storeAction()
    {
        if (!$this->beforeStoreAction($this->_responseArray, $this->_postFields)) {
            $this->_responseArray['success'] = false;
            if (strlen($this->_responseArray['errorMsg']) === 0) {
                $this->_responseArray['errorMsg'] = Utils::t('errorBeforeStore');
            }
        } else {
            if (array_key_exists('getRelated', $this->_postFields)) {
                $getRelated = $this->_postFields['getRelated'];
            } else {
                $getRelated = false;
            }
            $this->makeFilter();

            $recordStore = $this->_model->find($this->_filter);
            $store = $this->_formatRecords($recordStore, $getRelated);
            if (!$this->afterStoreAction($this->_responseArray, $this->_postFields, $store)) {
                $this->_responseArray['success'] = false;
                if (strlen($this->_responseArray['errorMsg']) === 0) {
                    $this->_responseArray['errorMsg'] = Utils::t('errorBeforeStore');
                }
            } else {
                $this->_responseArray['data']['records'] = $store;
                $this->_responseArray['data']['recordCount'] = count($store);
                $this->_responseArray['success'] = true;
            }
        }
        return json_encode($this->_responseArray);
    }

    /**
     * Set default/extra params to this storeAction
     * Add values to the response array
     * If this function returns false the storeAction will be broken and returns to the calling function
     *
     * @param $response
     * @param $params
     * @return bool
     */
    public function beforeStoreAction(&$response, &$params)
    {
        return true;
    }

    /**
     * Set extra values in the store (extra fields or default records)
     * Add values to the response array
     * If this function returns false the storeAction will be broken and returns to the calling function
     *
     * @param $response
     * @param $params
     * @param $store
     * @return bool
     */
    public function afterStoreAction(&$response, &$params, &$store)
    {
        return true;
    }

    /**
     * Load One record (for editing) and send also the remoteCombo preview values
     * @return string
     */
    public function loadAction()
    {
        $pkField = $this->_model->primaryKey;
        $record = $this->_model->findByPk($this->_postFields[$pkField], $pkField);
        $remoteRecord = $this->_getDataRecord($record, $this->_model->getListFields());
        $this->_responseArray['data'] = ['record' => $record, 'displayRecord' => $remoteRecord];
        $this->_responseArray['success'] = true;
        return json_encode($this->_responseArray);
    }

    public function dropDownAction()
    {
        $this->makeFilter();
        $recordStore = $this->_model->find($this->_filter);
        if (array_key_exists('valueField', $this->_postFields)) {
            $valueField = $this->_postFields['valueField'];
        } else {
            $valueField = $this->_model->primaryKey;
        }
        $labelFields = json_decode($this->_postFields['labelFields'], true);
        if (array_key_exists('labelSeparator', $this->_postFields)) {
            $labelSeparator = $this->_postFields['labelSeparator'];
        } else {
            $labelSeparator = '-';
        }
        $dataRecords = [];
        if ($this->_postFields['useSelectValue'] === 'Y') {
            $dataRecords[] = ['value' => '', 'label' => Utils::t('useSelectValue')];
        }
        foreach ($recordStore as $record) {
            $dataRecord = ['value' => $record->{$valueField}];
            $label = '';
            for ($i = 1; $i <= count($labelFields); $i++) {
                $labelField = $labelFields[$i - 1];
                $label .= $record->{$labelField};
                if ($i < count($labelFields)) {
                    $label .= ' ' . $labelSeparator . ' ';
                }
            }
            $dataRecord['label'] = $label;
            $dataRecords[] = $dataRecord;
        }
        if (!$this->afterDropDownAction($this->_responseArray, $this->_postFields, $dataRecords)) {
            $this->_responseArray['success'] = false;
            if (strlen($this->_responseArray['errorMsg']) === 0) {
                $this->_responseArray['errorMsg'] = Utils::t('errorBeforeStore');
            }
        } else {
            $this->_responseArray['data']['records'] = $dataRecords;
            $this->_responseArray['data']['recordCount'] = count($dataRecords);
            $this->_responseArray['success'] = true;
        }

        return json_encode($this->_responseArray);
    }


    public function afterDropDownAction(&$reponse, &$params, &$store)
    {
        return true;
    }

    public function createAction()
    {
        $aclField = $this->_model->aclField;
        $this->_postFields[$this->_model->primaryKey] = $this->_model->getUUID();
        $this->_model->formatFields($this->_postFields);
        if ($aclField) {
            $user = $this->session->get('user');
            $this->_postFields[$aclField] = $this->_aclService->newAclItem($this->_model->getSource(), $user->id);
            $this->_aclService->newUserAcl($this->_postFields[$aclField], $user->id, 100);
        }
        if ($this->beforeSaveAction($this->_responseArray)) {
            $saveSuccess = $this->_model->save($this->_postFields);
            if (!$saveSuccess) {
                $this->_responseArray['success'] = false;
                $this->_responseArray['errorMsg'] = Utils::t('errorCreateRecord');
            } else {
                $this->_responseArray['success'] = true;
                if (!$this->afterCreateAction($this->_responseArray)) {
                    $this->_responseArray['success'] = false;
                    if (strlen($this->_responseArray['errorMsg']) === 0) {
                        $this->_responseArray['errorMsg'] = Utils::t('errorAfterCreate');
                    }
                } else {
                    $this->_responseArray['newId'] = $this->_model->id;
                    if ($aclField === false) {
                        $this->_responseArray['newAclId'] = false;
                    } else {
                        $this->_responseArray['newAclId'] = $this->_model->$aclField;
                    }
                }
            }
        }

        return json_encode($this->_responseArray);
    }

    public function beforeSaveAction(&$response)
    {
        return true;
    }

    public function afterCreateAction(&$response)
    {
        return true;
    }

    public function updateAction()
    {
        $pkField = $this->_model->primaryKey;
        $record = $this->_model->findByPk($this->_postFields[$pkField], $pkField);
        $this->_model->formatFields($this->_postFields);
        if ($this->beforeSaveAction($this->_responseArray)) {
            $record->update($this->_postFields);
            if ($record->save()) {
                $this->_responseArray['success'] = true;
            } else {
                $this->_responseArray['errorMsg'] = Utils::t('updateError');
            }
        }
        echo json_encode($this->_responseArray);
    }

    public function beforeUpdateAction(&$response, &$params)
    {
        return true;
    }

    public function afterUpdateAction(&$response, &$params)
    {
        return true;
    }

    public function deleteAction()
    {
        $pkField = $this->_model->primaryKey;
        $record = $this->_model->findByPk($this->_postFields[$pkField], $pkField);
        if ($record->delete()) {
            $this->_responseArray['success'] = true;
        } else {
            $this->_responseArray['errorMsg'] = Utils::t('deleteError');
        }
        echo json_encode($this->_responseArray);
    }

    public function afterDeleteAction()
    {

    }

    public function restoreAction()
    {
        $record = $this->_model->findByPk($this->_postFields[$this->_model->primaryKey]);
        $record->softDeleted = 0;
        if ($record->save()) {
            $this->_responseArray['success'] = true;
        } else {
            $this->_responseArray['errorMsg'] = Utils::t('restoreError');
        }
        echo json_encode($this->_responseArray);
    }

    public function beforeRestoreAction()
    {

    }

    public function keepaliveAction()
    {
        return json_encode(array('success' => true, 'message' => 'KAL called', 'sessionMaxLifeTime' => ini_get('session.gc_maxlifetime')));
    }

}
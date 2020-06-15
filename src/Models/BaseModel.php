<?php

/**
 * BaseModel is het base for all Models in this application
 * In this model are all general functions defined for creating, updating and deleting records
 * Per model is the softDelete function installable
 *
 * For all tableNames use the next camelCase rule -> modulenameTablename
 *
 */

namespace Djc\Phalcon\Models;

use Phalcon\Db\Column;
use Phalcon\Db\Reference;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;

class BaseModel extends Model
{

    // Boolean vars for used columns
    protected $_modifiers = true;
    protected $_softDeletes = true;
    protected $_timeStamps = true;
    public static $_cacheAble = false;

    // Arrays for output to frontend
    protected $_dateFields = [];
    protected $_dateTimeFields = [];
    protected $_listFields = [];
    protected $_relatedFields = [];
    protected $_jsonFields = [];
    protected $_boolFields = [];

    // Read session for use in Several models
    public $session;

    // General settings for retrieving the data from the model, can be overruled in the controller
    public $orderField = 'id';
    public $orderDirection = 'ASC';
    public $aclField = 'aclItemId';
    public $primaryKey = 'id';
    public $setModified = true;
    public $cacheAble = false;
    public $hasModSequence = false;

    // General fields
    public $id;
    public $createdAt = 0;
    public $creatorId = '';
    public $modifiedAt = 0;
    public $modifierId = '';
    public $softDeleted = 0;

    /**
     * Initialize the model
     */
    public function initialize()
    {
        $di = new FactoryDefault();
        $this->session = $di->getDefault()->get('session');
        // Add behavior to tables in case of softDeletes
        if ($this->_softDeletes) {
            $softDelete = new SoftDelete(['field' => 'softDeleted', 'value' => 1]);
            $this->addBehavior($softDelete);
        }

    }

    /**
     * Run before initialization and after construction of the ModelClass
     */
    public function onConstruct()
    {
        $di = new FactoryDefault();
        $this->session = $di->getDefault()->get('session');
        $connection = $this->getReadConnection();
        if ($connection->tableExists($this->getSource())) {
            $metaData = $this->getModelsMetaData();
            $this->_listFields = array_merge($metaData->getAttributes($this), $this->_relatedFields);
        } else {
            $this->_listFields = [];
        }
    }

    /**
     * Manipulate fields after fetching them
     */
    public function afterFetch()
    {
        $this->softDeleted = boolval($this->softDeleted);
    }

    /**
     * Set Timestamp and Modifier fields before validation
     */
    public function beforeValidation()
    {
        $di = new FactoryDefault();
        $this->session = $di->getDefault()->get('session');
        if ($this->_timeStamps) {
            if ($this->createdAt == 0) {
                $this->createdAt = time();
                $this->softDeleted = 0;
            }
            if ($this->setModified)
                $this->modifiedAt = time();
        }
        $curUser = $this->session->get('user', false);
        if ($curUser !== false) {
            $currentUserId = $curUser->id;
            if ($this->_modifiers) {
                if ($this->creatorId == '') {
                    $this->creatorId = $currentUserId;
                }
                if ($this->setModified)
                    $this->modifierId = $currentUserId;
            }
        }

    }

    /**
     * Get a UUID for the ID of a new record
     *
     * @return string
     * @throws \Phalcon\Security\Exception
     */
    public function getUUID()
    {
        $random = new Random();
        return $random->uuid();
    }

    /**
     * Extend the tabledefinition with the general columns
     *
     * @param $tableName
     * @param $definition
     * @return mixed
     */
    public function getGeneralDefinition($tableName, $definition)
    {
        $columns = $definition['columns'];
        $foreignKeys = isset($definition['references']) ? $definition['references'] : [];

        if ($this->hasModSequence) {
            $columns[] = new Column('highModSeq', array(
                    'type' => Column::TYPE_INTEGER,
                    'notNull' => true,
                    'size' => 11,
                    'default' => 0
                )
            );
        }

        if ($this->_timeStamps) {
            $columns[] = new Column('createdAt', array(
                'type' => Column::TYPE_BIGINTEGER,
                'notNull' => true,
                'size' => 11,
            ));
            $columns[] = new Column('modifiedAt', array(
                'type' => Column::TYPE_BIGINTEGER,
                'notNull' => true,
                'default' => 0,
                'size' => 11,
            ));
        }
        if ($this->_softDeletes) {
            $columns[] = new Column('softDeleted', array(
                'type' => Column::TYPE_BOOLEAN,
                'notNull' => false,
                'default' => 0
            ));
        }
        if ($this->_modifiers) {
            $columns[] = new Column('creatorId', array(
                'type' => Column::TYPE_CHAR,
                'size' => 38,
                'notNull' => false,
            ));
            if ($this->getSource() !== 'baseUsers') {
                $foreignKeys[] = new Reference($tableName . 'creatorRef', array(
                    'referencedTable' => 'baseUsers',
                    'columns' => array('creatorId'),
                    'referencedColumns' => array('id')
                ));
            }
            $columns[] = new Column('modifierId', array(
                'type' => Column::TYPE_CHAR,
                'size' => 38,
                'notNull' => false,
            ));
            if ($this->getSource() !== 'baseUsers') {
                $foreignKeys[] = new Reference($tableName . 'modifierRef', array(
                    'referencedTable' => 'baseUsers',
                    'columns' => array('modifierId'),
                    'referencedColumns' => array('id')
                ));
            }
        }

        $definition['columns'] = $columns;
        $definition['references'] = $foreignKeys;
        return $definition;
    }

    /**
     * Get the public property softDeletes of the called class
     * @return boolean
     */
    public static function useSoftDeletes()
    {
        $class = get_called_class();
        $classObject = new $class();
        $classObject->initialize();
        return $classObject->_softDeletes;
    }

    /**
     * @inheritdoc
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Model\ResultsetInterface
     */
    public static function myFind($parameters = null): Model\ResultsetInterface
    {
        $parameters = self::softDeleteFetch($parameters);
        return parent::find($parameters);
//        $records = self::getCache($parameters);
//        if ($records === null) {
//            return parent::find($parameters);
//        } else {
//            return $records;
//        }
    }

    public static function getCache(&$parameters) {
        return null;
        $class = get_called_class();
        $source = (new $class)->getSource();
        $records = null;
        if ((new $class)->cacheAble) {
            $parameters = self::cachedFetch($parameters, $source);
            $di = new FactoryDefault();
            $cache = $di->getDefault()->get('modelsCache');
            $key = $source . self::_createKey($parameters);
            $records = $cache->get($key);
        }
        return $records;
    }

    /**
     * Find only the deleted records. Check first if solftdeletes are used
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Model\ResultsetInterface
     */
    public static function findDeleted($parameters = null): Model\ResultsetInterface
    {
        $parameters = self::softDeleteFetch($parameters, 1);
        return parent::find($parameters);
    }

    /**
     * Find all records, including deleted.
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Model\ResultsetInterface
     */

    public static function findAll($parameters = null): Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * @inheritdoc
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return \Djc\Phalcon\Models\BaseModel
     */
    public static function myFindFirst($parameters = null)
    {
        $parameters = self::softDeleteFetch($parameters);
        return parent::findFirst($parameters);
    }

    /**
     * Find first off the deleted records.
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Model\ResultsetInterface
     */
    public static function findFirstDeleted($parameters = null)
    {
        $parameters = self::softDeleteFetch($parameters, 1);
        return parent::findFirst($parameters);
    }

    /**
     * Find first record, including deleted.
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Model\ResultsetInterface
     */

    public static function findFirstAll($parameters = null)
    {
        return parent::findFirst($parameters);
    }

    public static function findByPk($pk, $pkField)
    {
        $parameters = [$pkField . '=:pk:', 'bind' => ['pk' => $pk]];
        return parent::findFirst($parameters);
    }

    public function restore()
    {
        $this->softDeleted = 0;
        return $this->save();
    }

    /**
     * @inheritdoc
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return mixed
     */
    public static function count($parameters = null)
    {
        $parameters = self::softDeleteFetch($parameters);
        return parent::count($parameters);
    }

    /**
     * @access protected
     * @static
     * @param array|string $parameters Query parameters
     * @return mixed
     */
    public static function softDeleteFetch($parameters = null, $value = 0)
    {

        if (call_user_func([get_called_class(), 'useSoftDeletes']) === false) {
            return $parameters;
        }

        $deletedField = 'softDeleted';

        if ($parameters === null) {
            $parameters = $deletedField . ' = ' . $value;
        } elseif (is_array($parameters) === false && strpos($parameters, $deletedField) === false) {
            if (strlen($parameters) === 0) {
                $parameters = '1';
            }
            $parameters .= ' AND ' . $deletedField . ' = ' . $value;
        } elseif (is_array($parameters) === true) {
            if (isset($parameters[0]) === true && strpos($parameters[0], $deletedField) === false) {
                if (strlen($parameters[0]) === 0) {
                    $parameters[0] = '1 = 1';
                }
                $parameters[0] .= ' AND ' . $deletedField . ' = ' . $value;
            } elseif (isset($parameters['conditions']) === true && strpos($parameters['conditions'], $deletedField) === false) {
                if (strlen($parameters['conditions']) === 0) {
                    $parameters['conditions'] = '1 = 1';
                }
                $parameters['conditions'] .= ' AND ' . $deletedField . ' = ' . $value;
            }
        }

        return $parameters;
    }

    public static function cachedFetch($parameters = null, $source = '') {
        $key = $source . self::_createKey($parameters);

        $di = new FactoryDefault();
        $cache = $di->getDefault()->get('modelsCache');

        if ($parameters === null) {
            $parameters = ['cache' => ['key' => $key]];
        } elseif (is_string($parameters)) {
            $parameters = ['cache' => ['key' => $key], $parameters];
        } elseif (is_array($parameters)) {
            $parameters['cache'] = ['key' => $key];
        }

        return $parameters;
    }

    protected static function _createKey($parameters)
    {
        $uniqueKey = array();
        foreach ($parameters as $key => $value) {
            if (is_scalar($value)) {
                $uniqueKey[] = $key . ':' . $value;
            } else {
                if (is_array($value)) {
                    $uniqueKey[] = $key . ':[' . self::_createKey($value) .']';
                }
            }
        }
        return join(',', $uniqueKey);
    }

    public function setListFields($fields)
    {
        $this->_listFields = $fields;
    }

    public function getListFields()
    {
        return $this->_listFields;
    }

    public function setDateTimeField($fields)
    {
        if (is_array($fields)) {
            $this->_dateTimeFields = array_merge($this->_dateTimeFields, $fields);
        } else {
            array_push($this->_dateTimeFields, $fields);
        }
    }

    public function getDateTimeFields()
    {
        return $this->_dateTimeFields;
    }

    public function setDateField($fields)
    {
        if (is_array($fields)) {
            $this->_dateFields = array_merge($this->_dateFields, $fields);
        } else {
            array_push($this->_dateFields, $fields);
        }
    }

    public function getDateFields()
    {
        return $this->_dateFields;
    }

    public function getJsonFields()
    {
        return $this->_jsonFields;
    }

    public function getBoolFields()
    {
        return $this->_boolFields;
    }

    /**
     * Format the posted fields to match with database
     * 
     * @param $postFields
     */
    public function formatFields(&$postFields)
    {
        foreach ($this->_dateFields as $dateField) {
            if (array_key_exists($dateField, $postFields)) {
                $postFields[$dateField] = strtotime($postFields[$dateField]);
            }
        }
        foreach ($this->_dateTimeFields as $dateField) {
            if (array_key_exists($dateField, $postFields)) {
                $postFields[$dateField] = strtotime($postFields[$dateField]);
            }
        }
        foreach ($this->_jsonFields as $jsonField) {
            if (array_key_exists($jsonField, $postFields)) {
                $postFields[$jsonField] = json_encode($postFields[$jsonField]);
            }
        }
        foreach ($this->_boolFields as $boolField) {
            if (array_key_exists($boolField, $postFields)) {
                $postFields[$boolField] = $postFields[$boolField] ? 1 : 0;
            }
        }
    }
}

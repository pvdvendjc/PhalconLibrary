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

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Phalcon\Db\Column;
use Phalcon\Db\Reference;

class BaseModel extends Model
{

    // Boolean vars for used columns
    protected $_modifiers = true;
    protected $_softDeletes = true;
    protected $_timeStamps = true;

    // Arrays for output to frontend
    protected $_dateFields = [];
    protected $_dateTimeFields = [];
    protected $_listFields = [];
    protected $_relatedFields = [];

    // Read session for use in Several models
    protected $_session;

    // General settings for retrieving the data from the model, can be overruled in the controller
    public $orderField = 'id';
    public $orderDirection = 'ASC';
    public $aclField = 'aclItemId';

    // General fields
    public $id;
    public $createdAt = 0;
    public $creatorId = '';
    public $modifiedAt = 0;
    public $modifierId = '';
    public $softDeleted = 0;

    public function initialize()
    {
        // Add behavior to tables in case of softDeletes
        if ($this->_softDeletes) {
            $softDelete = new SoftDelete(['field' => 'softDeleted', 'value' => 1]);
            $this->addBehavior($softDelete);
        }
    }

    public function onConstruct()
    {
        $di = new FactoryDefault();
        $this->_session = $di->getDefault()->get('session');
        if ($this->_modifiers) {
            $this->_relatedFields['creator'] = ['fullName'];
            $this->_relatedFields['modifier'] = ['fullName'];
            $this->_relatedFields[] = 'createTime';
            $this->_relatedFields[] = 'modifyTime';
        }
        $connection = $this->getReadConnection();
        if ($connection->tableExists($this->getSource())) {
            $metaData = $this->getModelsMetaData();
            $this->_listFields = array_merge($metaData->getAttributes($this), $this->_relatedFields);
        } else {
            $this->_listFields = array();
        }
    }

    public function beforeValidation() {
        $di = $this->getDI();
        $this->_session = $di->getShared('session');
        if ($this->_timeStamps) {
            if ($this->createdAt == 0) {
                $this->createdAt = time();
            }
            $this->modifiedAt = time();
        }
        $currentUserId = $this->_session->get('currentUserId', false);
        if ($currentUserId !== false) {
            if ($this->_modifiers) {
                if ($this->creatorId == '') {
                    $this->creatorId = $currentUserId;
                }
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
    public function getGeneralDefinition($tableName, $definition) {
        $columns = $definition['columns'];
        $foreignKeys = isset($definition['references']) ? $definition['references'] : [];

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
//            $foreignKeys[] = new Reference($tableName . 'creatorRef', array(
//                'referencedTable' => 'baseUsers',
//                'columns' => array('creatorId'),
//                'referencedColumns' => array('id')
//            ));
            $columns[] = new Column('modifierId', array(
                'type' => Column::TYPE_CHAR,
                'size' => 38,
                'notNull' => false,
            ));
//            $foreignKeys[] = new Reference($tableName . 'modifierRef', array(
//                'referencedTable' => 'baseUsers',
//                'columns' => array('modifierId'),
//                'referencedColumns' => array('id')
//            ));
        }

        $definition['columns'] = $columns;
        $definition['references'] = $foreignKeys;
        return $definition;
    }

    /**
     * Get the createTime well formatted according to usersettings
     *
     * @return false|string
     */
    public function createTime()
    {
        return date($this->_session->userSettings['dateFormat'] . ' ' . $this->_session->userSettings['timeFormat'], $this->createdAt);
    }

    /**
     * Get the modifyTime well formatted according to usersettings
     *
     * @return false|string
     */
    public function modifyTime()
    {
        return date($this->_session->userSettings['dateFormat'] . ' ' . $this->_session->userSettings['timeFormat'], $this->modifiedAt);
    }

    /**
     * Get the public property softDeletes of the called class
     * @return boolean
     */
    public static function useSoftDeletes()
    {
        $class = get_called_class();
        $classObject = new $class();
        return $classObject->_softDeletes;
    }

    /**
     * @inheritdoc
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function find($parameters = null)
    {
        $parameters = self::softDeleteFetch($parameters);
        return parent::find($parameters);
    }

    /**
     * Find only the deleted records. Check first if solftdeletes are used
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findDeleted($parameters = null) {
        $parameters = self::softDeleteFetch($parameters, 1);
        return parent::find($parameters);
    }

    /**
     * Find all records, including deleted.
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Phalcon\Mvc\Model\ResultsetInterface
     */

    public static function findAll($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * @inheritdoc
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Phalcon\Mvc\Model
     */
    public static function findFirst($parameters = null)
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
     * @return Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findFirstDeleted($parameters = null) {
        $parameters = self::softDeleteFetch($parameters, 1);
        return parent::findFirst($parameters);
    }

    /**
     * Find first record, including deleted.
     *
     * @access public
     * @static
     * @param array|string $parameters Query parameters
     * @return Phalcon\Mvc\Model\ResultsetInterface
     */

    public static function findFirstAll($parameters = null) {
        return parent::findFirst($parameters);
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
                    $parameters[0] = '1';
                }
                $parameters[0] .= ' AND ' . $deletedField . ' = ' . $value;
            } elseif (isset($parameters['conditions']) === true && strpos($parameters['conditions'], $deletedField) === false) {
                if (strlen($parameters['conditions']) === 0) {
                    $parameters['conditions'] = '1';
                }
                $parameters['conditions'] .= ' AND ' . $deletedField . ' = ' . $value;
            }
        }

        return $parameters;
    }


}
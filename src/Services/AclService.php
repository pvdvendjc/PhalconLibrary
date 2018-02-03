<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 30-1-18
 * Time: 17:01
 */

namespace Djc\Phalcon\Services;

use Horecatools\Modules\Base\Models\Acl;
use Horecatools\Modules\Base\Models\AclItem;
use Phalcon\Security\Exception;
use Phalcon\Db\Exception as DbException;

class AclService
{
    /**
     * @var \Djc\Phalcon\Models\BaseModel
     */
    protected $_aclModel;
    /**
     * @var \Djc\Phalcon\Models\BaseModel
     */
    protected $_aclItemModel;

    /**
     * AclService constructor.
     * @param \Djc\Phalcon\Models\BaseModel $aclModel
     * @param \Djc\Phalcon\Models\BaseModel $aclItemModel
     */
    public function __construct($aclModel, $aclItemModel)
    {
        $this->_aclModel = $aclModel;
        $this->_aclItemModel = $aclItemModel;
    }

    /**
     * @param string $table TableName
     * @param uuid $ownerId Id of the owner of this item
     * @param string $aclField
     * @return uuid
     * @throws \Phalcon\Db\Exception
     */
    public function newAclItem($table, $ownerId, $aclField = 'aclItemId')
    {

        try {
            $aclItem = $this->_aclItemModel->reset();
            $aclItem->id = $aclItem->getUUID();
            $aclItem->tableName = $table;
            $aclItem->modelId = $ownerId;
            $aclItem->fieldName = $aclField;
            $aclItem->save();
        } catch (Exception $e) {
            throw new DbException($e->getMessage());
        }
        return $aclItem->id;
    }

    /**
     * @param uuid $aclItemId
     * @param uuid $userId
     * @param integer $level
     * @return uuid
     * @throws \Phalcon\Db\Exception
     */
    public function newUserAcl($aclItemId, $userId, $level)
    {
        try {
            $acl = $this->_aclModel->reset();
            $acl->id = $acl->getUUID();
            $acl->aclItemId = $aclItemId;
            $acl->userId = $userId;
            $acl->groupId = '';
            $acl->aclLevel = $level;
            $acl->save();
        } catch (Exception $e) {
            throw new DbException($e->getMessage());
        }
        return $acl->id;
    }

    /**
     * @param uuid $aclItemId
     * @param uuid $groupId
     * @param integer $level
     * @return uuid
     * @throws \Phalcon\Db\Exception
     */
    public function newGroupAcl($aclItemId, $groupId, $level)
    {
        try {
            $acl = $this->_aclModel->reset();
            $acl->id = $acl->getUUID();
            $acl->aclItemId = $aclItemId;
            $acl->userId = '';
            $acl->groupId = $groupId;
            $acl->aclLevel = $level;
            $acl->save();
        } catch (Exception $e) {
            throw new DbException($e->getMessage());
        }
        return $acl->id;
    }

    /**
     * @param uuid $aclId
     * @param integer $level
     * @return boolean
     * @throws \Phalcon\Db\Exception
     */
    public function updateAcl($aclId, $level)
    {
        try {
            $acl = $this->_aclModel->findFirst('id = ' . $aclId);
            $acl->aclLevel = $level;
            $acl->save();
        } catch (\Exception $e) {
            throw new DbException($e->getMessage());
        }
        return true;
    }
}
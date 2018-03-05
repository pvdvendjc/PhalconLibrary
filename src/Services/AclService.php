<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 30-1-18
 * Time: 17:01
 */

namespace Djc\Phalcon\Services;

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
        $aclItemClass = $this->_aclItemModel;
        try {
            $aclItem = new $aclItemClass();
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
        $aclClass = $this->_aclModel;
        try {
            $acl = new $aclClass();
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
        $aclClass = $this->_aclModel;
        try {
            $acl = new $aclClass();
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
        $aclClass = $this->_aclModel;
        try {
            $acl = new $aclClass();
            $acl = $acl->findFirst('id = ' . $aclId);
            $acl->aclLevel = $level;
            $acl->save();
        } catch (\Exception $e) {
            throw new DbException($e->getMessage());
        }
        return true;
    }

    /**
     * @param uuid $aclItemId
     * @param integer $level
     * @param uuid $userId
     * @param uuid $groupId
     * @return boolean
     * @throws \Phalcon\Db\Exception
     */
    public function updateAclItem($aclItemId, $level, $userId = '', $groupId = '')
    {
        $aclClass = $this->_aclModel;
        try {
            $acl = new $aclClass();
            $acl = $acl->findFirst(['aclItemId = :aclItemId: AND userId = :userId: AND groupId = :groupId:', 'bind' => [
                'aclItemId' => $aclItemId,
                'userId' => $userId,
                'groupId' => $groupId
            ]]);
            $acl->aclLevel = $level;
            $acl->save();
        } catch (\Exception $e) {
            throw new DbException($e->getMessage());
        }
        return true;
    }

    /**
     * Give the highest aclLevel for this alcItemID
     * If not found give 0
     *
     * @param $userId
     * @param $groupIDs
     * @param $aclItemId
     * @return int
     *
     */
    public function aclLevel($userId, $groupIDs, $aclItemId)
    {
        $params = ['aclItemId = :aclItemId: AND (userId = :userId: OR groupId IN({groupIds:array})',
            'bind' => [
                'aclItemId' => $aclItemId,
                'userId' => $userId,
                'groupIds' => $groupIDs
            ],
            'order' => 'level DESC'];

        $highestAcl = $this->_aclModel->findFirst($params);

        if ($highestAcl === false) {
            $level = 0;
        } else {
            $level = $highestAcl->level;
        }
        return $level;
    }

    /**
     * Revoke the rights from a specific user of group from this aclItem
     *
     * @param uuid $aclItemId
     * @param uuid $userId
     * @param uuid $groupId
     * @return boolean
     */
    public function revokeAclItem($aclItemId, $userId = '', $groupId = '') {
        $params = ['aclItemId = :aclItemId: AND userId = :userId: AND groupId = :groupId:', 'bind' => [
            'aclItemId' => $aclItemId,
            'userId' => $userId,
            'groupId' => $groupId
        ]];

        $acl = $this->_aclModel->findFirst($params);
        return $acl->delete();
    }

}
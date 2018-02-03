<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 27-1-18
 * Time: 19:04
 */

namespace Djc\Phalcon\Models;


class Migration extends BaseModel
{
    public $module;
    public $version;
    public $class;
    public $table;
    public $migrationRun;

    public function initialize()
    {
        $this->setSource('migrations');
        $this->_modifiers = false;
        $this->_timeStamps = false;
        $this->_softDeletes = false;
        parent::initialize();
    }

    public function onConstruct()
    {
        $this->_modifiers = false;
        $this->_timeStamps = false;
        $this->_softDeletes = false;
        parent::onConstruct();
    }
}
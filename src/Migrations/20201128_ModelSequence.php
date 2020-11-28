<?php

use Djc\Phalcon\Migrations\DatabaseInstaller;

use Phalcon\Db\Column;
use Phalcon\Db\Index;

class ModelSequence_20201128 extends DatabaseInstaller
{
    public $version = 20201128;
    public $firstVersion = true;
    public $module = '';
    public $model = \Djc\Phalcon\Models\ModelSequence::class;

    public function morph() {
        $modelClass = $this->model;
        $model = new $modelClass();
        $tableName = $model->getSource();
        $this->morphTable($tableName, $model, [
            'columns' => [
                new Column('id', [
                    'type' => Column::TYPE_CHAR,
                    'size' => 38,
                    'notNull' => true,
                    'first' => true
                ]),
                new Column('tableName', [
                    'type' => Column::TYPE_VARCHAR,
                    'size' => 100,
                    'notNull' => true,
                    'default' => '',
                    'after' => 'id'
                ]),
                new Column('highModSequence', [
                    'type' => Column::TYPE_INTEGER,
                    'size' => 11,
                    'notNull' => true,
                    'after' => 'tableName'
                ])
            ],
            'indexes' => [
                new Index('PRIMARY', ['id'])
            ]
        ]);

        return ['module' => $this->module, 'table' => $tableName, 'className' => get_class($this), 'version' => $this->version];

    }
}
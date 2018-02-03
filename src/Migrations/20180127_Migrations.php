<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 27-1-18
 * Time: 23:59
 */

use Djc\Phalcon\Migrations\DatabaseInstaller;

use Phalcon\Db\Column;
use Phalcon\Db\Index;

class Migrations_20180127 extends DatabaseInstaller
{
    public $version = 20180127;
    public $module = '';
    public $firstVersion = true;
    public $model = 'Djc\Phalcon\Models\Migration';

    public function morph() {
        $modelClass = $this->model;
        $migration = new $modelClass();
        $tableName = $migration->getSource();
        $this->morphTable($tableName, $migration, [
            'columns' => [
                new Column('id', [
                    'type' => Column::TYPE_CHAR,
                    'size' => 38,
                    'notNull' => true,
                    'first' => true
                ]),
                new Column('module', [
                    'type' => Column::TYPE_VARCHAR,
                    'size' => 50,
                    'notNull' => true,
                    'default' => '',
                    'after' => 'id'
                ]),
                new Column('version', [
                    'type' => Column::TYPE_INTEGER,
                    'size' => 11,
                    'notNull' => true,
                    'after' => 'module'
                ]),
                new Column('class', [
                    'type' => Column::TYPE_VARCHAR,
                    'size' => 1024,
                    'notNull' => true,
                    'after' => 'version'
                ]),
                new Column('table', [
                    'type' => Column::TYPE_VARCHAR,
                    'size' => 255,
                    'notNull' => true,
                    'after' => 'file'
                ]),
                new Column('migrationRun', [
                    'type' => Column::TYPE_INTEGER,
                    'size'=> 11,
                    'notNull' => true,
                    'after' => 'table'
                ])
            ],
            'indexes' => [
                new Index('PRIMARY', ['id'])
            ]
        ]);

        return ['module' => $this->module, 'table' => $tableName, 'className' => get_class($this), 'version' => $this->version];
    }

    public function up() {
        return true;
    }

    public function down() {
        $this->_connection->dropTable('migrations');
    }
}
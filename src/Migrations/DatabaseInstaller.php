<?php
/**
 * Default database installer for tables in multi-module and multi-installation module
 *
 *
 */

namespace Djc\Phalcon\Migrations;

use Djc\Phalcon\Models\Migration;
use Phalcon\Di;
use SalesApp\Modules\Users\Models\User;

use Phalcon\Di\FactoryDefault;
use Phalcon\Exception;
use Phalcon\Db\Exception as dbException;

class DatabaseInstaller
{
    protected $_connection;
    protected $_modules;
    protected $_session;
    protected $_currentModule;
    protected $_userModel;

    public $firstVersion = false;
    public $module = '';

    public function __construct($userModel = null)
    {
        set_time_limit(1000);   // needed for larger tables
        $di = new FactoryDefault();
        try {
            $this->_connection = $di->getDefault()->get('db');
            $this->_session = $di->getDefault()->get('session');
            $this->_userModel = $userModel;
        } catch (Exception $ex) {
            error_log($ex->getMessage());
        }
    }

    /**
     * Set the modules array
     *
     * @param $modules
     */
    public function setModules($modules)
    {
        $this->_modules = $modules;
    }

    /**
     * Install the tables for each module
     *
     */
    public function installDatabase()
    {
        try {
            $migratesBase = $this->getMigrations('');
            $this->runMigrations($migratesBase, 1);
//            if (!$this->_session->has('user')) {
//                $admin = $this->_userModel->findFirst(['userName = :userName:', 'bind' => ['userName' => 'admin']]);
//                $this->_session->set('user', $admin);
//                $this->_session->set('userSettings', $admin->getSettings());
//            }
            $migrates = [];
            foreach ($this->_modules as $module) {
                $migrates = array_merge($migrates, $this->getMigrations(ucfirst($module)));
            }
            ksort($migrates);
            foreach ($migrates as $key => $migrate) {
                error_log($key);
            }
            $this->runMigrations($migrates, 2);
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return false;
        }
        return true;
    }

    public function updateDatabase()
    {
        try {
            if (!$this->_session->has('user')) {
                $admin = $this->_userModel->findFirst(['userName = :userName:', 'bind' => ['userName' => 'admin']]);
                $this->_session->set('user', $admin);
                $this->_session->set('userSettings', $admin->getSettings());
            }
            $maxMigrationRun = Migration::maximum(['column' => 'migrationRun']) + 1;
            error_log('Migration run number -> ' . $maxMigrationRun);
            foreach ($this->_modules as $module) {
                $migrates = $this->getMigrations(ucfirst($module));
                $this->runMigrations($migrates, $maxMigrationRun);
                error_log($module . ' is updated/installed');
            }
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return false;
        }
        return true;
    }

    public function rollbackDatabase()
    {
        try {
            // get Migrations from the database from previous run
            $maxMigrationRun = Migration::maximum(['column' => 'migrationRun']);
            $migrations = Migration::find(['migrationRun = :migrationRun:', 'bind' => ['migrationRun' => $maxMigrationRun]]);
            foreach ($migrations as $migration) {
                $module = $migration->module;
                $table = $migration->table;
                $className = $migration->class;
                if ($module === '') {
                    $path = APP_PATH . '/common/migrations/';
                } else {
                    $path = APP_PATH . '/modules/' . ucfirst($module) . '/database/migrations/';
                }
                $fileName = $path . $migration->version . '_' . substr($className, 0, -9) . '.php';
                include $fileName;
                if (!class_exists($className)) {
                    throw new Exception('Migration class cannot be found ' . $className . ' at ' . $fileName);
                }
                $migrate = new $className();
                $migrate->down();
                if (!$migrate->firstVersion) {
                    $prevMigration = Migration::findFirst(['migrationRun < :maxMigrationRun: AND table = :table:', 'bind' => [
                        'maxMigrationRun' => $maxMigrationRun,
                        'table' => $table
                    ], 'order' => 'migrationRun DESC']);
                    $module = $prevMigration->module;
                    $className = $prevMigration->class;
                    if ($module === '') {
                        $path = APP_PATH . '/common/migrations/';
                    } else {
                        $path = APP_PATH . '/modules/' . ucfirst($module) . '/database/migrations/';
                    }
                    $fileName = $path . $prevMigration->version . '_' . substr($className, 0, -9) . '.php';
                    include $fileName;
                    if (!class_exists($className)) {
                        throw new Exception('Migration class cannot be found ' . $className . ' at ' . $fileName);
                    }
                    $migrate = new $className();
                    $migrate->morph();
                }
                $migration->delete();
            }
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            return false;
        }
    }

    /**
     *  Run up() functions after morphing the table
     */
    public function up()
    {
        return false;
    }

    /**
     * Set the morpholigic of the table to the database
     *
     * @param $tableName
     * @param $model
     * @param $definition
     * @throws dbException
     */
    public function morphTable($tableName, $model, $definition)
    {

        $tableExists = $this->_connection->tableExists($tableName);
        $tableSchema = null;

        $definition = $model->getGeneralDefinition($tableName, $definition);

        if (isset($definition['columns'])) {
            if (count($definition['columns']) === 0) {
                throw new dbException('Table must have at least one column');
            }

            $fields = [];

            foreach ($definition['columns'] as $column) {
                if (!is_object($column)) {
                    throw new dbException('Table must have at least one column');
                }
                $fields[$column->getName()] = $column;
                if (empty($tableSchema)) {
                    $tableSchema = $column->getSchemaName();
                }
            }
            if ($tableExists) {
                $currentFields = [];
                $description = $this->_connection->describeColumns($tableName);
                foreach ($description as $currentField) {
                    $currentFields[$currentField->getName()] = $currentField;
                }

                foreach ($fields as $fieldName => $column) {
                    if (!isset($currentFields[$fieldName])) {
                        $this->_connection->addColumn($tableName, $tableSchema, $column);
                    } else {
                        $changed = false;

                        if ($currentFields[$fieldName]->getType() !== $column->getType()) {
                            $changed = true;
                        }
                        if ($currentFields[$fieldName]->getSize() !== $column->getSize()) {
                            $changed = true;
                        }
                        if ($currentFields[$fieldName]->isNotNull() !== $column->isNotNull()) {
                            $changed = true;
                        }
                        if ($currentFields[$fieldName]->getDefault() !== $column->getDefault()) {
                            $changed = true;
                        }

                        if ($changed) {
                            $this->_connection->modifyColumn($tableName, $tableSchema, $column);
                        }
                    }
                }

                foreach ($currentFields as $fieldName => $column) {
                    if (!isset($fields[$fieldName])) {
                        $this->_connection->dropColumn($tableName, $tableSchema, $fieldName);
                    }
                }
            } else {
                $this->_connection->createTable($tableName, $tableSchema, $definition);
                if (method_exists($this, 'afterCreateTable')) {
                    $this->afterCreateTable();
                }
            }
        }

        if (isset($definition['references'])) {
            if ($tableExists) {
                $references = [];
                foreach ($definition['references'] as $tableReference) {
                    $references[$tableReference->getName()] = $tableReference;
                }

                $localReferences = [];
                $activeReferences = $this->_connection->describeReferences($tableName);
                foreach ($activeReferences as $activeReference) {
                    $localReferences[$activeReference->getName()] = [
                        'referencedTable' => $activeReference->getReferencedTable(),
                        "referencedSchema" => $activeReference->getReferencedSchema(),
                        'columns' => $activeReference->getColumns(),
                        'referencedColumns' => $activeReference->getReferencedColumns(),
                    ];
                }

                foreach ($definition['references'] as $tableReference) {
                    if (!isset($localReferences[$tableReference->getName()])) {
                        $this->_connection->addForeignKey(
                            $tableName,
                            $tableReference->getSchemaName(),
                            $tableReference
                        );
                    } else {
                        $changed = false;
                        if ($tableReference->getReferencedTable() != $localReferences[$tableReference->getName()]['referencedTable']
                        ) {
                            $changed = true;
                        }

                        if ($changed == false) {
                            if (count($tableReference->getColumns()) != count($localReferences[$tableReference->getName()]['columns'])) {
                                $changed = true;
                            }
                        }

                        if ($changed == false) {
                            if (count($tableReference->getReferencedColumns()) != count($localReferences[$tableReference->getName()]['referencedColumns'])) {
                                $changed = true;
                            }
                        }
                        if ($changed == false) {
                            foreach ($tableReference->getColumns() as $columnName) {
                                if (!in_array($columnName, $localReferences[$tableReference->getName()]['columns'])) {
                                    $changed = true;
                                    break;
                                }
                            }
                        }
                        if ($changed == false) {
                            foreach ($tableReference->getReferencedColumns() as $columnName) {
                                if (!in_array($columnName, $localReferences[$tableReference->getName()]['referencedColumns'])) {
                                    $changed = true;
                                    break;
                                }
                            }
                        }

                        if ($changed == true) {
                            $this->_connection->dropForeignKey(
                                $tableName,
                                $tableReference->getSchemaName(),
                                $tableReference->getName()
                            );
                            $this->_connection->addForeignKey(
                                $tableName,
                                $tableReference->getSchemaName(),
                                $tableReference
                            );
                        }
                    }
                }

                foreach ($localReferences as $referenceName => $reference) {
                    if (!isset($references[$referenceName])) {
                        $this->_connection->dropForeignKey($tableName, null, $referenceName);
                    }
                }
            }
        }

        if (isset($definition['indexes'])) {
            if ($tableExists == true) {
                $indexes = [];
                foreach ($definition['indexes'] as $tableIndex) {
                    $indexes[$tableIndex->getName()] = $tableIndex;
                }

                $localIndexes = [];
                $actualIndexes = $this->_connection->describeIndexes($tableName);
                foreach ($actualIndexes as $actualIndex) {
                    $localIndexes[$actualIndex->getName()] = $actualIndex->getColumns();
                }

                foreach ($definition['indexes'] as $tableIndex) {
                    if (!isset($localIndexes[$tableIndex->getName()])) {
                        if ($tableIndex->getName() == 'PRIMARY') {
                            $this->_connection->addPrimaryKey($tableName, $tableSchema, $tableIndex);
                        } else {
                            $this->_connection->addIndex($tableName, $tableSchema, $tableIndex);
                        }
                    } else {
                        $changed = false;
                        if (count($tableIndex->getColumns()) != count($localIndexes[$tableIndex->getName()])) {
                            $changed = true;
                        } else {
                            foreach ($tableIndex->getColumns() as $columnName) {
                                if (!in_array($columnName, $localIndexes[$tableIndex->getName()])) {
                                    $changed = true;
                                    break;
                                }
                            }
                        }
                        if ($changed == true) {
                            if ($tableIndex->getName() == 'PRIMARY') {
                                $this->_connection->dropPrimaryKey($tableName, $tableSchema);
                                $this->_connection->addPrimaryKey(
                                    $tableName,
                                    $tableSchema,
                                    $tableIndex
                                );
                            } else {
                                $this->_connection->dropIndex(
                                    $tableName,
                                    $tableSchema,
                                    $tableIndex->getName()
                                );
                                $this->_connection->addIndex($tableName, $tableSchema, $tableIndex);
                            }
                        }
                    }
                }
                foreach ($localIndexes as $indexName => $indexColumns) {
                    if (!isset($indexes[$indexName])) {
                        //$this->_connection->dropIndex($tableName, null, $indexName);
                    }
                }
            }
        }
    }

    public function setModule($module)
    {
        $this->_currentModule = $module;
    }

    public function setVersion($version)
    {
        $this->_version = $version;
    }

    public function getMigrations($module)
    {
        $migrates = [];
        if ($module === '') {
            $path = __DIR__ . '/';
        } else {
            $path = APP_PATH . '/modules/' . $module . '/database/migrations/';
        }

        $migrations = glob($path . '*.php');    // Glob sorts alphabetically by nature, if version is 00000001 this will always be first executed
        foreach ($migrations as $migration) {
            // check if current version is less then versionNumber in
            $fileName = str_replace($path, '', $migration);
            $version = substr($fileName, 0, 8);
            if ((int)$version > 0) {
                $className = substr($fileName, 9, -4) . '_' . $version;
                require_once $path . $fileName;
                if (!class_exists($className)) {
                    throw new Exception('Migration class cannot be found ' . $className . ' at ' . $path . $fileName);
                }
                $migrate = new $className();
                $migrates[$fileName] = $migrate;
            }
        }

        return $migrates;
    }

    public function getTableName()
    {
        $modelClass = $this->model;
        $model = new $modelClass();
        return $model->getSource();
    }

    public function runMigrations($migrations, $runNumber = 1)
    {
        foreach ($migrations as $migration) {
            if ($migration->getTableName() === 'migrations' && $runNumber === 1) {
                $migrationRecord = false;
            } else {
                // Check if migration is already in the database
                $migrationRecord = Migration::findFirst(['version = :version: AND table = :table:', 'bind' => [
                    'version' => $migration->version,
                    'table' => $migration->getTableName()
                ]]);
            }
            if (!$migrationRecord) {
                $response = $migration->morph();
                $di = Di::getDefault();
                $modelsMetadata = $di->getShared('modelsMetadata');
                $modelsMetadata->reset();
                $migration->up();
                $migrationRecord = new Migration();
                $migrationRecord->id = $migrationRecord->getUUID();
                $migrationRecord->module = $response['module'];
                $migrationRecord->version = $response['version'];
                $migrationRecord->table = $response['table'];
                $migrationRecord->class = $response['className'];
                $migrationRecord->migrationRun = $runNumber;
                $migrationRecord->save();
            }
        }
    }
}
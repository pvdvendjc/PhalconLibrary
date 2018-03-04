<?php

namespace Djc\Phalcon\Db;

/**
 * Djc\Phalcon\Db\DbListener.
 */
class DbListener
{
    public function beforeQuery($event, $connection)
    {
        if ($connection->debug == true) {
            echo "<hr>\n(pdo-{$connection->getType()}) : {$connection->getSQLStatement()}\n<hr>\n";
        }
    }
}

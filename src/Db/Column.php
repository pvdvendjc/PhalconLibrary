<?php

namespace Djc\Phalcon\Db;

class Column extends \Phalcon\Db\Column
{
    public function __construct(string $name, array $definition)
    {
        if (!array_key_exists('notNull', $definition)) {
            $definition['notNull'] = false;
        }
        parent::__construct($name, $definition);
    }
}
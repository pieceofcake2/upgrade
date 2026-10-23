<?php

use Cake\Model\Datasource\Database\Sqlite;

class DATABASE_CONFIG
{
    public $default = [
        'datasource' => Sqlite::class,
        'persistent' => false,
        'database' => ':memory:',
    ];
}

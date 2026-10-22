<?php

use Cake\Model\Datasource\Database\Mysql;
use Cake\Model\Datasource\Database\Sqlite;

class DATABASE_CONFIG
{
    public $default = [
        'datasource' => Mysql::class,
        'persistent' => false,
        'host' => 'localhost',
    ];

    public $test = [
        'datasource' => Sqlite::class,
        'database' => ':memory:',
    ];
}

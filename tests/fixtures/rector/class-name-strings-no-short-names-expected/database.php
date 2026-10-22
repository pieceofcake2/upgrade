<?php
class DATABASE_CONFIG
{
    public $default = [
        'datasource' => 'Database/Mysql',
        'persistent' => false,
        'host' => 'localhost',
    ];

    public $test = [
        'datasource' => 'Database/Sqlite',
        'database' => ':memory:',
    ];
}

<?php

use Cake\Model\CakeSchema;

class AppSchema extends CakeSchema
{
    public $posts = [
        'id' => ['type' => 'integer', 'null' => false, 'key' => 'primary'],
    ];
}

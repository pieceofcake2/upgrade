<?php

use Cake\Model\CakeSchema;

class AppSchema extends CakeSchema
{
    public $products = [
        'id' => ['type' => 'integer', 'null' => false, 'key' => 'primary'],
        'price' => ['type' => 'integer', 'null' => false],
    ];
}

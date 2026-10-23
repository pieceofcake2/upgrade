<?php
class ProductFixture extends CakeTestFixture
{
    public $fields = [
        'id' => ['type' => 'integer', 'key' => 'primary'],
        'price' => ['type' => 'integer'],
    ];

    public $records = [['id' => 1, 'price' => 1200]];
}

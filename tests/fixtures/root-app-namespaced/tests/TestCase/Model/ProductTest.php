<?php

namespace App\Test\TestCase\Model;

use App\Model\Product;
use Cake\TestSuite\CakeTestCase;
use Cake\Utility\ClassRegistry;

class ProductTest extends CakeTestCase
{
    public $fixtures = ['app.product'];

    public function testFind()
    {
        $this->assertNotEmpty(ClassRegistry::init(Product::class)->find('all'));
    }
}

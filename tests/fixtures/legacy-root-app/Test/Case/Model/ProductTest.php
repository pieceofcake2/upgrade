<?php
App::uses('Product', 'Model');

class ProductTest extends CakeTestCase
{
    public $fixtures = ['app.product'];

    public function testFind()
    {
        $this->assertNotEmpty(ClassRegistry::init('Product')->find('all'));
    }
}

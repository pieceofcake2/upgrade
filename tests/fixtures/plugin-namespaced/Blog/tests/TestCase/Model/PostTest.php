<?php

namespace Blog\Test\TestCase\Model;

use Blog\Model\Post;
use Cake\TestSuite\CakeTestCase;
use Cake\Utility\ClassRegistry;

class PostTest extends CakeTestCase
{
    public $fixtures = ['plugin.blog.post'];

    public function testFind()
    {
        $Post = ClassRegistry::init(Post::class);
        $this->assertInstanceOf(Post::class, $Post);
    }
}

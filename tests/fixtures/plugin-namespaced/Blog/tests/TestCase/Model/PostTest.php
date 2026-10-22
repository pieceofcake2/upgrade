<?php

namespace Blog\Test\TestCase\Model;

use Cake\TestSuite\CakeTestCase;
use Cake\Utility\ClassRegistry;
use Blog\Model\Post;

class PostTest extends CakeTestCase
{
    public $fixtures = ['plugin.blog.post'];

    public function testFind()
    {
        $Post = ClassRegistry::init(Post::class);
        $this->assertInstanceOf(Post::class, $Post);
    }
}

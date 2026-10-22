<?php
App::uses('Post', 'Blog.Model');

class PostTest extends CakeTestCase
{
    public $fixtures = ['plugin.blog.post'];

    public function testFind()
    {
        $Post = ClassRegistry::init('Blog.Post');
        $this->assertInstanceOf('Post', $Post);
    }
}
